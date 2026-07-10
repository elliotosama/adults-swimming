<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$targetDb = $argv[1] ?? 'swimming_academy_migrated';
$dryRun = in_array('--dry-run', $argv, true);

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, $targetDb),
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

function hasMojibakeMarker(string $value): bool
{
    return preg_match('/[╪┘┌├┬]/u', $value) === 1;
}

function hasArabic(string $value): bool
{
    return preg_match('/[\x{0600}-\x{06FF}]/u', $value) === 1;
}

function repairMojibake(string $value): string
{
    if (!hasMojibakeMarker($value) || hasArabic($value)) {
        return $value;
    }

    $current = $value;
    for ($i = 0; $i < 3; $i++) {
        if (!hasMojibakeMarker($current)) {
            break;
        }

        $bytes = @iconv('UTF-8', 'CP437//IGNORE', $current);
        if ($bytes === false || $bytes === '') {
            break;
        }

        if (!mb_check_encoding($bytes, 'UTF-8')) {
            break;
        }

        if ($bytes === $current) {
            break;
        }

        $current = $bytes;
    }

    return $current;
}

$pkStmt = $pdo->prepare(
    "SELECT k.TABLE_NAME, k.COLUMN_NAME
     FROM information_schema.KEY_COLUMN_USAGE k
     JOIN information_schema.TABLE_CONSTRAINTS tc
       ON tc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
      AND tc.TABLE_NAME = k.TABLE_NAME
      AND tc.CONSTRAINT_NAME = k.CONSTRAINT_NAME
     WHERE k.TABLE_SCHEMA = DATABASE()
       AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
     ORDER BY k.TABLE_NAME, k.ORDINAL_POSITION"
);
$pkStmt->execute();

$primaryKeys = [];
foreach ($pkStmt as $row) {
    $primaryKeys[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
}

$columnStmt = $pdo->query(
    "SELECT TABLE_NAME, COLUMN_NAME
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext')
     ORDER BY TABLE_NAME, ORDINAL_POSITION"
);

$columns = $columnStmt->fetchAll();
$totalChanged = 0;

foreach ($columns as $column) {
    $table = $column['TABLE_NAME'];
    $columnName = $column['COLUMN_NAME'];
    $pk = $primaryKeys[$table] ?? [];

    if (count($pk) !== 1) {
        continue;
    }

    $pkColumn = $pk[0];
    $select = $pdo->query(
        "SELECT `$pkColumn` AS pk_value, `$columnName` AS text_value
         FROM `$table`
         WHERE `$columnName` IS NOT NULL
           AND (`$columnName` LIKE '%╪%' OR `$columnName` LIKE '%┘%' OR `$columnName` LIKE '%┌%' OR `$columnName` LIKE '%├%' OR `$columnName` LIKE '%┬%')"
    );

    $update = $pdo->prepare(
        "UPDATE `$table`
         SET `$columnName` = :new_value
         WHERE `$pkColumn` = :pk_value"
    );

    $changed = 0;
    foreach ($select as $row) {
        $old = (string)$row['text_value'];
        $new = repairMojibake($old);
        if ($new === $old) {
            continue;
        }

        $changed++;
        if (!$dryRun) {
            $update->execute([
                ':new_value' => $new,
                ':pk_value' => $row['pk_value'],
            ]);
        }
    }

    if ($changed > 0) {
        $totalChanged += $changed;
        echo sprintf("%s.%s: %d rows %s\n", $table, $columnName, $changed, $dryRun ? 'would change' : 'changed');
    }
}

echo sprintf("Total rows %s: %d\n", $dryRun ? 'that would change' : 'changed', $totalChanged);
