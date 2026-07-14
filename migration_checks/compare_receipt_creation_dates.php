<?php

declare(strict_types=1);

/**
 * Print receipt IDs whose created_at date in the old database is different
 * from the receipt with the same ID in the new database. A missing receipt in
 * the new database is also treated as a mismatch.
 *
 * Example:
 *   php migration_checks/compare_receipt_creation_dates.php --old-db=old_database
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
    'help',
]);

if (isset($options['help']) || empty($options['old-db'])) {
    echo "Usage: php migration_checks/compare_receipt_creation_dates.php --old-db=OLD_DATABASE [options]\n";
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
        $dates[(int) $row['id']] = $row['created_at'] !== null
            ? substr((string) $row['created_at'], 0, 10)
            : null;
    }

    return $dates;
}

try {
    $oldDates = receiptDates(connect(settings($options, 'old', '')));
    $newDates = receiptDates(connect(settings($options, 'new', DB_NAME)));
} catch (PDOException $e) {
    fwrite(STDERR, 'Database comparison failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

ksort($oldDates, SORT_NUMERIC);
foreach ($oldDates as $id => $oldDate) {
    if (!array_key_exists($id, $newDates) || $newDates[$id] !== $oldDate) {
        echo $id . PHP_EOL;
    }
}

