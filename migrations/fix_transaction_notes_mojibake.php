<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$targetDb = $argv[1] ?? DB_NAME;
$dryRun = in_array('--dry-run', $argv, true);

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, $targetDb, DB_CHARSET),
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

function transactionNotesHasMojibakeMarker(string $value): bool
{
    return preg_match('/[╪┘┌├┬]/u', $value) === 1;
}

function transactionNotesHasArabic(string $value): bool
{
    return preg_match('/[\x{0600}-\x{06FF}]/u', $value) === 1;
}

function repairTransactionNotesMojibake(string $value): string
{
    if (!transactionNotesHasMojibakeMarker($value) || transactionNotesHasArabic($value)) {
        return $value;
    }

    $current = $value;
    for ($i = 0; $i < 3; $i++) {
        if (!transactionNotesHasMojibakeMarker($current)) {
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

$select = $pdo->query(
    "SELECT id, notes
     FROM transactions
     WHERE notes IS NOT NULL
       AND (notes LIKE '%╪%'
            OR notes LIKE '%┘%'
            OR notes LIKE '%┌%'
            OR notes LIKE '%├%'
            OR notes LIKE '%┬%')
     ORDER BY id"
);

$update = $pdo->prepare(
    "UPDATE transactions
     SET notes = :notes
     WHERE id = :id"
);

$changed = 0;
foreach ($select as $row) {
    $old = (string) $row['notes'];
    $new = repairTransactionNotesMojibake($old);
    if ($new === $old) {
        continue;
    }

    $changed++;
    echo $row['id'] . ' | ' . $old . ' => ' . $new . PHP_EOL;

    if (!$dryRun) {
        $update->execute([
            ':notes' => $new,
            ':id' => $row['id'],
        ]);
    }
}

echo sprintf("Transaction notes %s: %d\n", $dryRun ? 'that would change' : 'changed', $changed);
