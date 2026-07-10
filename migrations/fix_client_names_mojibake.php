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

function clientHasMojibakeMarker(string $value): bool
{
    return preg_match('/[╪┘┌├┬]/u', $value) === 1;
}

function clientHasArabic(string $value): bool
{
    return preg_match('/[\x{0600}-\x{06FF}]/u', $value) === 1;
}

function repairClientMojibake(string $value): string
{
    if (!clientHasMojibakeMarker($value) || clientHasArabic($value)) {
        return $value;
    }

    $current = $value;
    for ($i = 0; $i < 3; $i++) {
        if (!clientHasMojibakeMarker($current)) {
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
    "SELECT id, client_name
     FROM clients
     WHERE client_name LIKE '%╪%'
        OR client_name LIKE '%┘%'
        OR client_name LIKE '%┌%'
        OR client_name LIKE '%├%'
        OR client_name LIKE '%┬%'
     ORDER BY id"
);

$update = $pdo->prepare(
    "UPDATE clients
     SET client_name = :client_name
     WHERE id = :id"
);

$changed = 0;
foreach ($select as $row) {
    $old = (string) $row['client_name'];
    $new = repairClientMojibake($old);
    if ($new === $old) {
        continue;
    }

    $changed++;
    echo $row['id'] . ' | ' . $old . ' => ' . $new . PHP_EOL;

    if (!$dryRun) {
        $update->execute([
            ':client_name' => $new,
            ':id' => $row['id'],
        ]);
    }
}

echo sprintf("Client names %s: %d\n", $dryRun ? 'that would change' : 'changed', $changed);
