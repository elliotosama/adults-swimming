<?php

declare(strict_types=1);

/**
 * Remove new receipt_edited audit rows that duplicate an edit already stored in
 * the same old receipt's edit_history JSON. The edit_history audit row itself
 * and all other event types are preserved.
 *
 * Safe by default: without --apply this prints the duplicate audit-row IDs.
 *
 * Examples:
 *   php migration_checks/remove_duplicate_receipt_audit_logs.php --old-db=OLD_DATABASE
 *   php migration_checks/remove_duplicate_receipt_audit_logs.php --old-db=OLD_DATABASE --apply --report
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
    echo "Usage: php migration_checks/remove_duplicate_receipt_audit_logs.php --old-db=OLD_DATABASE [--apply] [options]\n";
    echo "\nWithout --apply, the script only lists duplicate new audit-log IDs.\n";
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

/** Normalize JSON object key order while retaining chronological array order. */
function normalizeJson(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    foreach ($value as $key => $item) {
        $value[$key] = normalizeJson($item);
    }
    if (!array_is_list($value)) {
        ksort($value, SORT_STRING);
    }
    return $value;
}

/**
 * Make an exact, safely comparable representation of a log value. JSON values
 * are semantically normalized; plain-text summaries retain their content.
 */
function comparableValue(mixed $value): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return 'text:';
    }

    try {
        $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        return 'json:' . json_encode(
            normalizeJson($decoded),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    } catch (JsonException) {
        return 'text:' . $text;
    }
}

/** @return list<string> */
function editHistoryValues(mixed $history): array
{
    if ($history === null || trim((string) $history) === '') {
        return [];
    }

    try {
        $entries = json_decode((string) $history, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }
    if (!is_array($entries)) {
        return [];
    }

    $values = [];
    foreach ($entries as $entry) {
        if (!is_array($entry) || !isset($entry['changes']) || !is_array($entry['changes'])) {
            continue;
        }

        $changes = $entry['changes'];
        $summary = trim((string) ($changes['summary'] ?? ''));
        // Old activities save the summary when it exists; blank summaries are
        // saved as the complete changes object.
        $values[] = comparableValue($summary !== '' ? $summary : $changes);
    }

    return $values;
}

/** @param list<array{id: int, receipt_id: int, changed_at: string}> $duplicates */
function writeReport(string $path, array $duplicates): void
{
    $handle = fopen($path, 'wb');
    if ($handle === false) {
        throw new RuntimeException("Unable to write report: $path");
    }

    fputcsv($handle, ['audit_log_id', 'receipt_id', 'changed_at', 'action']);
    foreach ($duplicates as $row) {
        fputcsv($handle, [$row['id'], $row['receipt_id'], $row['changed_at'], 'delete_duplicate']);
    }
    fclose($handle);
}

try {
    $oldPdo = connect(settings($options, 'old', ''));
    $newPdo = connect(settings($options, 'new', DB_NAME));

    $oldRows = $oldPdo->query('SELECT id, edit_history FROM receipts')->fetchAll();
    $newRows = $newPdo->query(
        "SELECT id, receipt_id, changed_at, new_value
         FROM receipt_audit_log
         WHERE field_name = 'receipt_edited'
         ORDER BY id"
    )->fetchAll();
} catch (Throwable $e) {
    fwrite(STDERR, 'Database preparation failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/** @var array<int, array<string, true>> $oldEditValues */
$oldEditValues = [];
foreach ($oldRows as $row) {
    $values = editHistoryValues($row['edit_history']);
    if ($values !== []) {
        $oldEditValues[(int) $row['id']] = array_fill_keys($values, true);
    }
}

/** @var list<array{id: int, receipt_id: int, changed_at: string}> $duplicates */
$duplicates = [];
foreach ($newRows as $row) {
    $receiptId = (int) $row['receipt_id'];
    $value = comparableValue($row['new_value']);
    if (isset($oldEditValues[$receiptId][$value])) {
        $duplicates[] = [
            'id' => (int) $row['id'],
            'receipt_id' => $receiptId,
            'changed_at' => (string) $row['changed_at'],
        ];
    }
}

foreach ($duplicates as $row) {
    echo $row['id'] . "\t" . $row['receipt_id'] . PHP_EOL;
}
echo 'Duplicate receipt_edited audit rows: ' . count($duplicates) . PHP_EOL;

if (isset($options['report'])) {
    $reportPath = $options['report'] === false || $options['report'] === ''
        ? __DIR__ . '/duplicate_receipt_audit_logs_report.csv'
        : (string) $options['report'];
    try {
        writeReport($reportPath, $duplicates);
        echo "Report written: $reportPath\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'Report failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

if (!isset($options['apply'])) {
    echo "Dry run only. Re-run with --apply to remove these rows.\n";
    exit(0);
}

try {
    $delete = $newPdo->prepare("DELETE FROM receipt_audit_log WHERE id = :id AND field_name = 'receipt_edited'");
    $newPdo->beginTransaction();
    foreach ($duplicates as $row) {
        $delete->execute([':id' => $row['id']]);
    }
    $newPdo->commit();
} catch (Throwable $e) {
    if ($newPdo->inTransaction()) {
        $newPdo->rollBack();
    }
    fwrite(STDERR, 'Duplicate removal failed; all changes were rolled back: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo 'Removed duplicate receipt_edited audit rows: ' . count($duplicates) . PHP_EOL;
