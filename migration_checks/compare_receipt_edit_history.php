<?php

declare(strict_types=1);

/**
 * Print receipt IDs whose old receipts.edit_history does not match the new
 * receipt_audit_log entry/entries stored under field_name = 'edit_history'.
 *
 * JSON is compared semantically: whitespace and object-key order do not cause
 * a mismatch, while array order and actual values still matter.
 *
 * Example:
 *   php migration_checks/compare_receipt_edit_history.php --old-db=OLD_DATABASE
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
    'verbose', 'help',
]);

if (isset($options['help']) || empty($options['old-db'])) {
    echo "Usage: php migration_checks/compare_receipt_edit_history.php --old-db=OLD_DATABASE [--verbose] [options]\n";
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

/**
 * Recursively sort JSON object keys. JSON arrays deliberately retain their
 * original order because each edit is part of a chronological history.
 */
function normalizeJsonValue(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    foreach ($value as $key => $item) {
        $value[$key] = normalizeJsonValue($item);
    }

    if (!array_is_list($value)) {
        ksort($value, SORT_STRING);
    }

    return $value;
}

/** @return array{state: 'empty'|'valid'|'invalid', value: string|null} */
function normalizeEditHistory(mixed $history): array
{
    if ($history === null || trim((string) $history) === '') {
        return ['state' => 'empty', 'value' => null];
    }

    try {
        $decoded = json_decode((string) $history, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return ['state' => 'invalid', 'value' => (string) $history];
    }

    // An empty old history should not have an edit_history audit row.
    if (is_array($decoded) && $decoded === []) {
        return ['state' => 'empty', 'value' => null];
    }

    return [
        'state' => 'valid',
        'value' => json_encode(
            normalizeJsonValue($decoded),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        ),
    ];
}

try {
    $oldPdo = connect(settings($options, 'old', ''));
    $newPdo = connect(settings($options, 'new', DB_NAME));

    $oldRows = $oldPdo->query('SELECT id, edit_history FROM receipts')->fetchAll();
    $newRows = $newPdo->query(
        "SELECT receipt_id, new_value FROM receipt_audit_log WHERE field_name = 'edit_history'"
    )->fetchAll();
} catch (Throwable $e) {
    fwrite(STDERR, 'Database comparison failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/** @var array<int, list<array{state: 'empty'|'valid'|'invalid', value: string|null}>> $newHistories */
$newHistories = [];
foreach ($newRows as $row) {
    $newHistories[(int) $row['receipt_id']][] = normalizeEditHistory($row['new_value']);
}

$mismatches = [];
foreach ($oldRows as $row) {
    $receiptId = (int) $row['id'];
    $oldHistory = normalizeEditHistory($row['edit_history']);
    $newHistoryRows = $newHistories[$receiptId] ?? [];

    $matches = false;
    if ($oldHistory['state'] === 'empty') {
        $matches = count($newHistoryRows) === 0;
    } elseif ($oldHistory['state'] === 'valid') {
        $matches = count($newHistoryRows) === 1
            && $newHistoryRows[0]['state'] === 'valid'
            && $newHistoryRows[0]['value'] === $oldHistory['value'];
    }

    if (!$matches) {
        $mismatches[$receiptId] = sprintf(
            'old_%s,new_rows=%d',
            $oldHistory['state'],
            count($newHistoryRows)
        );
    }
}

// Audit rows with no corresponding old receipt must also be investigated.
$oldIds = array_flip(array_map(static fn(array $row): int => (int) $row['id'], $oldRows));
foreach ($newHistories as $receiptId => $rows) {
    if (!isset($oldIds[$receiptId])) {
        $mismatches[$receiptId] = 'missing_in_old_database';
    }
}

ksort($mismatches, SORT_NUMERIC);
foreach ($mismatches as $receiptId => $reason) {
    echo isset($options['verbose']) ? "$receiptId\t$reason\n" : "$receiptId\n";
}

fwrite(STDERR, sprintf(
    "Old receipts checked: %d; new edit_history audit rows: %d; mismatches: %d\n",
    count($oldRows),
    count($newRows),
    count($mismatches)
));
