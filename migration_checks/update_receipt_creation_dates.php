<?php

require __DIR__ . '/../config/database.php';

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    DB_HOST,
    DB_PORT,
    DB_NAME,
    DB_CHARSET
);

$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

try {
    $pdo->beginTransaction();

    $updated = $pdo->exec("
        UPDATE receipts
        SET created_at = ADDDATE(created_at, INTERVAL 1 DAY)
    ");

    $pdo->commit();

    echo "Updated {$updated} receipts successfully." . PHP_EOL;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die($e->getMessage());
}