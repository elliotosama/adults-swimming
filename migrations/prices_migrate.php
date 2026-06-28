<?php
/**
 * Migration script: old prices/items table -> new prices table
 *
 * ASSUMPTIONS:
 *  - OLD ids are preserved as the new ids (same primary key values).
 *  - OLD item_name -> NEW description
 *  - OLD price (decimal(10,2)) -> NEW price (decimal(8,2)), copied as-is
 *    (you confirmed values won't realistically exceed 999,999.99).
 *  - OLD created_at (timestamp) -> NEW created_at (date), date portion only.
 *  - NEW updated_at has no old equivalent -> set to the same date as created_at.
 *    (Change to NULL below if you'd rather leave it empty.)
 *  - NEW number_of_sessions -> NULL
 *  - NEW country_id -> NULL
 *  - NEW visible -> 1 for all migrated rows
 *
 * CONFIGURE BELOW:
 *  - DB connection details
 *  - Name of your OLD table
 */

// ----------------------------------------------------------------------
// CONFIG
// ----------------------------------------------------------------------
$DB_HOST = '127.0.0.1';
$DB_NAME = 'your_database';
$DB_USER = 'your_user';
$DB_PASS = 'your_password';

// Name of the OLD table (rename if different)
$OLD_PRICES_TABLE = 'old_prices';

// Name of the NEW table (per the schema you posted)
$NEW_PRICES_TABLE = 'prices';

// ----------------------------------------------------------------------
// CONNECT
// ----------------------------------------------------------------------
try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

$warnings = [];
$pdo->beginTransaction();

try {
    // ------------------------------------------------------------------
    // Migrate prices (old -> new), preserving ids
    // ------------------------------------------------------------------
    $oldRows = $pdo->query("
        SELECT id, item_name, price, created_at
        FROM `{$OLD_PRICES_TABLE}`
    ")->fetchAll();

    $insertPrice = $pdo->prepare("
        INSERT INTO `{$NEW_PRICES_TABLE}`
            (id, description, price, created_at, updated_at, visible, number_of_sessions, country_id)
        VALUES
            (:id, :description, :price, :created_at, :updated_at, 1, NULL, NULL)
    ");

    $migrated = 0;

    foreach ($oldRows as $row) {
        // created_at is a timestamp in the old table; keep just the date part
        $createdAtDate = $row['created_at'] ? date('Y-m-d', strtotime($row['created_at'])) : null;

        // updated_at has no old equivalent - default to the same date as created_at
        $updatedAtDate = $createdAtDate;

        $insertPrice->execute([
            ':id'          => $row['id'],
            ':description' => $row['item_name'],
            ':price'       => $row['price'],
            ':created_at'  => $createdAtDate,
            ':updated_at'  => $updatedAtDate,
        ]);
        $migrated++;
    }

    echo "Migrated {$migrated} price record(s).\n";

    $pdo->commit();
    echo "Migration completed successfully.\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    die("Migration FAILED, all changes rolled back: " . $e->getMessage() . "\n");
}

// ------------------------------------------------------------------
// Report any warnings
// ------------------------------------------------------------------
if (!empty($warnings)) {
    echo "\n--- Warnings ---\n";
    foreach ($warnings as $w) {
        echo $w . "\n";
    }
}
