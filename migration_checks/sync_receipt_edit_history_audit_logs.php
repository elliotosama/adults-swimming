<?php

declare(strict_types=1);

/**
 * Rebuild new receipt_audit_log edit_history rows from old receipts.edit_history.
 * Only rows with field_name = 'edit_history' are inserted, updated, or deleted.
 * Other receipt audit-log records are never changed.
 *
 * Safe by default: without --apply it reports the planned changes only.
 *
 * Examples:
 *   php migration_checks/sync_receipt_edit_history_audit_logs.php --old-db=OLD_DATABASE
 *   php migration_checks/sync_receipt_edit_history_audit_logs.php --old-db=OLD_DATABASE --apply
 */

require __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$options = getopt('', [
    'old-db:', 'old-host::', 'old-port::', 'old-user::', 'old-pass::',
    'new-db::', 'new-host::', 'new-port::', 'new-user::', 'new-pass::',
    'apply', 'report::', 'help',
]);

if (isset($options['help']) || empty($options['old-db'])) {
    echo "Usage: php migration_checks/sync_receipt_edit_history_audit_logs.php --old-db=OLD_DATABASE [--apply] [options]\n";
    echo "\nWithout --apply, the script is a dry run and does not modify the new database.\n";
    exit(isset($options['help']) ? 0 : 1);
}

/** @return array{host: string, port: int, db: string, user: string, pass: string} */
function settings(array $options, string $prefix, string $defaultDatabase): array
{
    return [
        'host' => (string) ($options["{$prefix}-host"] ?? DB_HOST),
        'port' => (int) ($options["{$prefix}-port"] ?? DB_PORT),
        'db' => (string) ($options["{$prefix}-db"] ?? $defaultDatabase),
        'user' => (string) ($options["{$prefix}-user"] ?? DB_USER),
        'pass' => (string) ($options["{$prefix}-pass"] ?? DB_PASS),
    ];
}

function connect(array $settings): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $settings['host'],
        $settings['port'],
        $settings['db'],
        DB_CHARSET
    );

    return new PDO($dsn, $settings['user'], $settings['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/** @return 'empty'|'valid'|'invalid' */
function historyState(mixed $history): string
{
    if ($history === null || trim((string) $history) === '') {
        return 'empty';
    }

    try {
        $decoded = json_decode((string) $history, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return 'invalid';
    }

    return is_array($decoded) && $decoded === [] ? 'empty' : 'valid';
}

/** @param list<array{receipt_id: int, action: string, detail: string}> $report */
function writeReport(string $path, array $report): void
{
    $handle = fopen($path, 'wb');
    if ($handle === false) {
        throw new RuntimeException("Unable to write report: $path");
    }

    fputcsv($handle, ['receipt_id', 'action', 'detail']);
    foreach ($report as $row) {
        fputcsv($handle, [$row['receipt_id'], $row['action'], $row['detail']]);
    }
    fclose($handle);
}

try {
    $oldPdo = connect(settings($options, 'old', ''));
    $newPdo = connect(settings($options, 'new', DB_NAME));

    $oldRows = $oldPdo->query(
        'SELECT id, creator_id, creator_role, created_at, last_edit, edit_history FROM receipts'
    )->fetchAll();
    $newReceiptIds = array_flip(array_map(
        static fn(array $row): int => (int) $row['id'],
        $newPdo->query('SELECT id FROM receipts')->fetchAll()
    ));
    $newUsers = [];
    foreach ($newPdo->query('SELECT id, role FROM users')->fetchAll() as $user) {
        $newUsers[(int) $user['id']] = (string) $user['role'];
    }
    $existingRows = $newPdo->query(
        "SELECT id, receipt_id FROM receipt_audit_log WHERE field_name = 'edit_history' ORDER BY id"
    )->fetchAll();
} catch (Throwable $e) {
    fwrite(STDERR, 'Database preparation failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/** @var array<int, list<int>> $existingByReceipt */
$existingByReceipt = [];
foreach ($existingRows as $row) {
    $existingByReceipt[(int) $row['receipt_id']][] = (int) $row['id'];
}

/** @var list<array{receipt_id: int, action: string, detail: string}> $report */
$report = [];
/** @var array<int, array{changed_by: int, changed_at: string, role: string, new_value: string}> $inserts */
$inserts = [];
/** @var list<int> $deleteReceiptIds */
$deleteReceiptIds = [];
$oldReceiptIds = [];

foreach ($oldRows as $row) {
    $receiptId = (int) $row['id'];
    $oldReceiptIds[$receiptId] = true;
    $state = historyState($row['edit_history']);
    $currentCount = count($existingByReceipt[$receiptId] ?? []);

    if (!isset($newReceiptIds[$receiptId])) {
        if ($state !== 'empty' || $currentCount > 0) {
            $report[] = ['receipt_id' => $receiptId, 'action' => 'skipped', 'detail' => 'receipt_missing_in_new_database'];
        }
        continue;
    }

    if ($state === 'invalid') {
        $report[] = ['receipt_id' => $receiptId, 'action' => 'skipped', 'detail' => 'invalid_old_edit_history_json'];
        continue;
    }

    if ($state === 'empty') {
        if ($currentCount > 0) {
            $deleteReceiptIds[] = $receiptId;
            $report[] = ['receipt_id' => $receiptId, 'action' => 'delete', 'detail' => "$currentCount obsolete edit_history row(s)"];
        }
        continue;
    }

    $creatorId = $row['creator_id'] === null ? 1 : (int) $row['creator_id'];
    if (!isset($newUsers[$creatorId])) {
        $report[] = ['receipt_id' => $receiptId, 'action' => 'skipped', 'detail' => "creator_id $creatorId is missing in new users table"];
        continue;
    }

    $changedAt = (string) ($row['last_edit'] ?: $row['created_at']);
    $role = $newUsers[$creatorId] !== '' ? $newUsers[$creatorId] : (string) ($row['creator_role'] ?: 'admin');
    $inserts[$receiptId] = [
        'changed_by' => $creatorId,
        'changed_at' => $changedAt,
        'role' => $role,
        'new_value' => (string) $row['edit_history'],
    ];
    $deleteReceiptIds[] = $receiptId;
    $report[] = [
        'receipt_id' => $receiptId,
        'action' => $currentCount === 0 ? 'insert' : 'replace',
        'detail' => $currentCount === 0 ? 'missing edit_history row' : "$currentCount existing edit_history row(s)",
    ];
}

// Do not leave edit_history rows for receipts that no longer exist in the old database.
foreach ($existingByReceipt as $receiptId => $ids) {
    if (!isset($oldReceiptIds[$receiptId])) {
        $deleteReceiptIds[] = $receiptId;
        $report[] = ['receipt_id' => $receiptId, 'action' => 'delete', 'detail' => 'receipt_missing_in_old_database'];
    }
}

$deleteReceiptIds = array_values(array_unique($deleteReceiptIds));
sort($deleteReceiptIds, SORT_NUMERIC);
usort($report, static fn(array $a, array $b): int => $a['receipt_id'] <=> $b['receipt_id']);

if (isset($options['report'])) {
    $reportPath = $options['report'] === false || $options['report'] === ''
        ? __DIR__ . '/receipt_edit_history_sync_report.csv'
        : (string) $options['report'];
    try {
        writeReport($reportPath, $report);
        echo "Report written: $reportPath\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'Report failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

echo 'Old receipts checked: ' . count($oldRows) . PHP_EOL;
echo 'Edit-history rows to delete: ' . count($deleteReceiptIds) . PHP_EOL;
echo 'Edit-history rows to insert: ' . count($inserts) . PHP_EOL;
echo 'Skipped receipts: ' . count(array_filter($report, static fn(array $row): bool => $row['action'] === 'skipped')) . PHP_EOL;

if (!isset($options['apply'])) {
    echo "Dry run only. Re-run with --apply to synchronize the new database.\n";
    exit(0);
}

try {
    $delete = $newPdo->prepare("DELETE FROM receipt_audit_log WHERE receipt_id = :receipt_id AND field_name = 'edit_history'");
    $insert = $newPdo->prepare(
        "INSERT INTO receipt_audit_log
            (receipt_id, changed_by, changed_at, role, field_name, old_value, new_value)
         VALUES
            (:receipt_id, :changed_by, :changed_at, :role, 'edit_history', NULL, :new_value)"
    );

    $newPdo->beginTransaction();
    foreach ($deleteReceiptIds as $receiptId) {
        $delete->execute([':receipt_id' => $receiptId]);
    }
    foreach ($inserts as $receiptId => $row) {
        $insert->execute([':receipt_id' => $receiptId] + $row);
    }
    $newPdo->commit();
} catch (Throwable $e) {
    if ($newPdo->inTransaction()) {
        $newPdo->rollBack();
    }
    fwrite(STDERR, 'Synchronization failed; all changes were rolled back: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Synchronization complete. Re-run compare_receipt_edit_history.php to verify it.\n";
