<?php

declare(strict_types=1);

/**
 * Compare receipt IDs in two MySQL databases without changing either one.
 *
 * Examples:
 *   php migration_checks/compare_receipt_ids.php --old-db=swimming_academy_old
 *   php migration_checks/compare_receipt_ids.php --old-db=old_db --new-db=swimming_academy --output=/tmp/missing_receipts.csv
 *
 * Connection options may be supplied for databases on different servers:
 *   --old-host=... --old-port=3306 --old-user=... --old-pass=...
 *   --new-host=... --new-port=3306 --new-user=... --new-pass=...
 *
 * If an option is omitted, the normal application database configuration is
 * used. The script performs SELECT queries only.
 */

require __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$options = getopt('', [
    'old-db:', 'old-host::', 'old-port::', 'old-user::', 'old-pass::',
    'new-db::', 'new-host::', 'new-port::', 'new-user::', 'new-pass::',
    'output::', 'help',
]);

if (isset($options['help']) || empty($options['old-db'])) {
    echo "Usage: php migration_checks/compare_receipt_ids.php --old-db=OLD_DATABASE [options]\n\n";
    echo "Options:\n";
    echo "  --new-db=DATABASE      New database (default: " . DB_NAME . ")\n";
    echo "  --output=FILE.csv      Write missing old receipt IDs to a CSV file\n";
    echo "  --old-host/--old-port/--old-user/--old-pass\n";
    echo "  --new-host/--new-port/--new-user/--new-pass\n";
    exit(isset($options['help']) ? 0 : 1);
}

/** @return array{host: string, port: int, db: string, user: string, pass: string} */
function connectionSettings(array $options, string $prefix, string $defaultDatabase): array
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

/** @return array<int, true> */
function receiptIds(PDO $pdo): array
{
    $ids = [];
    $stmt = $pdo->query('SELECT id FROM receipts');
    while ($row = $stmt->fetch()) {
        $ids[(int) $row['id']] = true;
    }

    return $ids;
}

$oldSettings = connectionSettings($options, 'old', '');
$newSettings = connectionSettings($options, 'new', DB_NAME);

try {
    $oldIds = receiptIds(connect($oldSettings));
    $newIds = receiptIds(connect($newSettings));
} catch (PDOException $e) {
    fwrite(STDERR, 'Database comparison failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$missingInNew = array_keys(array_diff_key($oldIds, $newIds));
sort($missingInNew, SORT_NUMERIC);

// Standard output is intentionally machine-friendly: it contains only IDs
// present in the old database and absent from the new database, one per line.
foreach ($missingInNew as $id) {
    echo $id . PHP_EOL;
}

if (!empty($options['output'])) {
    $output = (string) $options['output'];
    $handle = @fopen($output, 'w');
    if ($handle === false) {
        fwrite(STDERR, "Could not write CSV file: {$output}\n");
        exit(1);
    }

    fputcsv($handle, ['receipt_id', 'exists_in_new_database']);
    foreach ($missingInNew as $id) {
        fputcsv($handle, [$id, 'no']);
    }
    fclose($handle);
}
