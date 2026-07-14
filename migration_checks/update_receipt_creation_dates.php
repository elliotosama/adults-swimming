<?php

declare(strict_types=1);

/**
 * Synchronize receipt created_at dates from the old database into the new one,
 * matching receipts by their identical ID.
 *
 * The old receipts.created_at column is a timestamp and the new column is a
 * DATE, so this script deliberately copies only the YYYY-MM-DD portion.
 *
 * Safe by default: without --apply it only reports what would change.
 *
 * Examples:
 *   php migration_checks/update_receipt_creation_dates.php --old-db=OLD_DATABASE
 *   php migration_checks/update_receipt_creation_dates.php --old-db=OLD_DATABASE --apply
 *
 * Optional connection overrides support databases on different servers:
 *   --old-host=... --old-port=3306 --old-user=... --old-pass=...
 *   --new-db=... --new-host=... --new-port=3306 --new-user=... --new-pass=...
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
    echo "Usage: php migration_checks/update_receipt_creation_dates.php --old-db=OLD_DATABASE [--apply] [options]\n";
    echo "\nWithout --apply, the script is a dry run and does not modify the new database.\n";
    exit(isset($options['help']) ? 0 : 1);
}

/** @return array{host: string, port: int, db: string, user: string, pass: string} */
function settings(array $options, string $prefix, string $defaultDatabase): array
{
    return [
        'host' => (string) ($options["{$prefix}-host"] ?? DB_HOST),
        'port' => (int) ($options["{$prefix}-port"] ?? DB_PORT),
        'db'   => (string) ($options["{$prefix}-db"] ?? $defaultDatabase),
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

/** @return array<int, string|null> */
function receiptDates(PDO $pdo): array
{
    $dates = [];
    $stmt = $pdo->query('SELECT id, created_at FROM receipts');
    while ($row = $stmt->fetch()) {
        $dates[(int) $row['id']] = $row['created_at'] === null
            ? null
            : substr((string) $row['created_at'], 0, 10);
    }

    return $dates;
}

/** @param array<int, array{old_date: string|null, new_date: string|null}> $changes */
function writeReport(string $path, array $changes, array $missing, bool $apply): void
{
    $handle = fopen($path, 'wb');
    if ($handle === false) {
        throw new RuntimeException("Unable to write report: $path");
    }

    fputcsv($handle, ['receipt_id', 'old_created_at', 'new_created_at', 'action']);
    foreach ($changes as $id => $dates) {
        fputcsv($handle, [$id, $dates['old_date'], $dates['new_date'], $apply ? 'updated' : 'would_update']);
    }
    foreach ($missing as $id => $oldDate) {
        fputcsv($handle, [$id, $oldDate, null, 'missing_in_new_database']);
    }
    fclose($handle);
}

try {
    $oldPdo = connect(settings($options, 'old', ''));
    $newPdo = connect(settings($options, 'new', DB_NAME));
    $oldDates = receiptDates($oldPdo);
    $newDates = receiptDates($newPdo);
} catch (Throwable $e) {
    fwrite(STDERR, 'Database preparation failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$changes = [];
$missing = [];
foreach ($oldDates as $id => $oldDate) {
    if (!array_key_exists($id, $newDates)) {
        $missing[$id] = $oldDate;
        continue;
    }

    if ($newDates[$id] !== $oldDate) {
        $changes[$id] = ['old_date' => $oldDate, 'new_date' => $newDates[$id]];
    }
}
ksort($changes, SORT_NUMERIC);
ksort($missing, SORT_NUMERIC);

if (isset($options['report'])) {
    $reportPath = $options['report'] === false || $options['report'] === ''
        ? __DIR__ . '/receipt_creation_date_sync_report.csv'
        : (string) $options['report'];
    try {
        writeReport($reportPath, $changes, $missing, isset($options['apply']));
        echo "Report written: $reportPath\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'Report failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

echo 'Receipts in old database: ' . count($oldDates) . PHP_EOL;
echo 'Dates to update: ' . count($changes) . PHP_EOL;
echo 'Missing in new database (not updated): ' . count($missing) . PHP_EOL;

if (!isset($options['apply'])) {
    echo "Dry run only. Re-run with --apply to update the new database.\n";
    exit(0);
}

if ($changes === []) {
    echo "Nothing to update.\n";
    exit(0);
}

try {
    $update = $newPdo->prepare(
        'UPDATE receipts SET created_at = :created_at WHERE id = :id AND NOT (created_at <=> :created_at_match)'
    );

    $newPdo->beginTransaction();
    $updated = 0;
    foreach ($changes as $id => $dates) {
        $update->execute([
            ':created_at' => $dates['old_date'],
            ':id' => $id,
            ':created_at_match' => $dates['old_date'],
        ]);
        $updated += $update->rowCount();
    }
    $newPdo->commit();
} catch (Throwable $e) {
    if ($newPdo->inTransaction()) {
        $newPdo->rollBack();
    }
    fwrite(STDERR, 'Update failed; all changes were rolled back: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Updated receipt creation dates: $updated\n";
echo "Re-run compare_receipt_creation_dates.php to verify the result.\n";
