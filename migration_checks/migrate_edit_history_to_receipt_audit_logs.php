<?php

declare(strict_types=1);

/**
 * Migrate receipt edit history from the OLD database into the NEW
 * receipt_audit_log table.
 *
 * This script:
 * 1. Deletes all audit logs before 2026-07-10.
 * 2. Disables foreign key checks.
 * 3. Reads receipts.edit_history from the old database.
 * 4. Stores the JSON exactly as-is in new_value.
 * 5. Inserts only:
 *      - receipt_id
 *      - new_value
 *      - changed_at
 * 6. Re-enables foreign key checks.
 */

const OLD_DB = [
    'host' => '127.0.0.1',
    'name' => 'swimmingacademy',
    'user' => 'root',
    'pass' => '',
];

const NEW_DB = [
    'host' => '127.0.0.1',
    'name' => 'swimming_academy',
    'user' => 'root',
    'pass' => '',
];

function connect(array $db): PDO
{
    return new PDO(
        "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
        $db['user'],
        $db['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

$old = connect(OLD_DB);
$new = connect(NEW_DB);

echo "Disabling foreign key checks...\n";
$new->exec("SET FOREIGN_KEY_CHECKS = 0");

try {

    echo "Deleting old audit logs before 2026-07-10...\n";

    $deleted = $new->exec("
        DELETE
        FROM receipt_audit_log
        WHERE changed_at < '2026-07-10'
    ");

    echo "Deleted {$deleted} row(s).\n";

    $select = $old->query("
        SELECT
            id,
            created_at,
            edit_history
        FROM receipts
        ORDER BY id
    ");

    $insert = $new->prepare("
        INSERT INTO receipt_audit_log
        (
            receipt_id,
            new_value,
            changed_at
        )
        VALUES
        (
            :receipt_id,
            :new_value,
            :changed_at
        )
    ");

    $inserted = 0;
    $skipped = 0;
    $invalid = 0;

    $new->beginTransaction();

    while ($row = $select->fetch(PDO::FETCH_ASSOC)) {

        $history = trim((string)$row['edit_history']);

        if (
            $history === '' ||
            strtolower($history) === 'null' ||
            $history === '[]'
        ) {
            $skipped++;
            continue;
        }

        json_decode($history);

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "Invalid JSON for receipt {$row['id']}\n";
            $invalid++;
            continue;
        }

        $insert->bindValue(
            ':receipt_id',
            (int)$row['id'],
            PDO::PARAM_INT
        );

        // Store the JSON EXACTLY as it exists in the old database.
        $insert->bindValue(
            ':new_value',
            $history,
            PDO::PARAM_STR
        );

        $insert->bindValue(
            ':changed_at',
            substr((string)$row['created_at'], 0, 19),
            PDO::PARAM_STR
        );

        $insert->execute();

        $inserted++;

        if ($inserted % 500 === 0) {
            echo "Inserted {$inserted} records...\n";
        }
    }

    $new->commit();

    echo "Re-enabling foreign key checks...\n";
    $new->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo PHP_EOL;
    echo "=============================\n";
    echo "Migration completed.\n";
    echo "=============================\n";
    echo "Inserted : {$inserted}\n";
    echo "Skipped  : {$skipped}\n";
    echo "Invalid  : {$invalid}\n";

} catch (Throwable $e) {

    if ($new->inTransaction()) {
        $new->rollBack();
    }

    // Always re-enable foreign keys
    try {
        $new->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (Throwable $ignored) {
    }

    die("Migration failed: " . $e->getMessage() . PHP_EOL);
}