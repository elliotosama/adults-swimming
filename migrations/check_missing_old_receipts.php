<?php

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$targetDb = $argv[1] ?? DB_NAME;
$oldDump = dirname(__DIR__) . '/swimming_academy_old.sql';

function readDumpUtf8(string $path): string
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("Cannot read {$path}");
    }

    if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
        $converted = iconv('UTF-16', 'UTF-8//IGNORE', $raw);
        if ($converted === false) {
            throw new RuntimeException('Failed to convert old dump from UTF-16 to UTF-8');
        }

        return $converted;
    }

    return $raw;
}

function eachValueTuple(string $values, callable $callback): void
{
    $length = strlen($values);
    $inString = false;
    $escaped = false;
    $depth = 0;
    $buffer = '';

    for ($i = 0; $i < $length; $i++) {
        $ch = $values[$i];

        if ($depth === 0) {
            if ($ch === '(') {
                $depth = 1;
                $buffer = '';
            }
            continue;
        }

        if ($inString) {
            if ($escaped) {
                $escaped = false;
            } elseif ($ch === '\\') {
                $escaped = true;
            } elseif ($ch === "'") {
                $inString = false;
            }
            $buffer .= $ch;
            continue;
        }

        if ($ch === "'") {
            $inString = true;
            $buffer .= $ch;
            continue;
        }

        if ($ch === '(') {
            $depth++;
            $buffer .= $ch;
            continue;
        }

        if ($ch === ')') {
            $depth--;
            if ($depth === 0) {
                $callback(str_getcsv($buffer, ',', "'", '\\'));
            } else {
                $buffer .= $ch;
            }
            continue;
        }

        $buffer .= $ch;
    }
}

function eachOldReceiptRow(string $sql, callable $callback): void
{
    $marker = 'INSERT INTO `receipts` VALUES ';
    $offset = 0;
    $found = false;

    while (($start = strpos($sql, $marker, $offset)) !== false) {
        $found = true;
        $start += strlen($marker);
        $end = strpos($sql, ';', $start);
        if ($end === false) {
            throw new RuntimeException('Could not find end of receipts INSERT');
        }

        eachValueTuple(substr($sql, $start, $end - $start), $callback);
        $offset = $end + 1;
    }

    if (!$found) {
        throw new RuntimeException('No receipts INSERT found in old dump');
    }
}

function cleanDumpValue(?string $value): ?string
{
    if ($value === null || strtoupper($value) === 'NULL' || $value === '') {
        return null;
    }

    return $value;
}

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

$existingIds = [];
foreach ($pdo->query('SELECT id FROM receipts') as $row) {
    $existingIds[(int) $row['id']] = true;
}

$oldTotal = 0;
$oldIds = [];
$missing = [];
$oldByMonth = [];
$missingByMonth = [];
$missingByStatus = [];

eachOldReceiptRow(readDumpUtf8($oldDump), function (array $row) use (&$oldTotal, &$oldIds, &$missing, &$oldByMonth, &$missingByMonth, &$missingByStatus, $existingIds): void {
    $id = (int) ($row[0] ?? 0);
    $createdAt = cleanDumpValue($row[16] ?? null);
    $createdDate = $createdAt ? substr($createdAt, 0, 10) : 'missing-date';
    $month = $createdDate !== 'missing-date' ? substr($createdDate, 0, 7) : 'missing-date';
    $status = cleanDumpValue($row[15] ?? null) ?? 'missing-status';

    $oldTotal++;
    if ($id) {
        $oldIds[$id] = true;
    }
    $oldByMonth[$month] = ($oldByMonth[$month] ?? 0) + 1;

    if ($id && !isset($existingIds[$id])) {
        $missing[] = [
            'id' => $id,
            'created_at' => $createdDate,
            'client_name' => cleanDumpValue($row[2] ?? null) ?? '',
            'status' => $status,
        ];
        $missingByMonth[$month] = ($missingByMonth[$month] ?? 0) + 1;
        $missingByStatus[$status] = ($missingByStatus[$status] ?? 0) + 1;
    }
});

ksort($oldByMonth);
ksort($missingByMonth);
arsort($missingByStatus);

echo 'Old dump receipts: ' . $oldTotal . PHP_EOL;
echo 'Migrated receipts: ' . count($existingIds) . PHP_EOL;
echo 'Missing old receipt IDs: ' . count($missing) . PHP_EOL;

$extraIds = array_diff_key($existingIds, $oldIds);
ksort($extraIds);
echo 'Migrated IDs not in old dump: ' . count($extraIds) . PHP_EOL;

echo PHP_EOL . "Missing by month:\n";
foreach ($missingByMonth as $month => $count) {
    echo "{$month}: {$count}\n";
}

echo PHP_EOL . "Missing by status:\n";
foreach ($missingByStatus as $status => $count) {
    echo "{$status}: {$count}\n";
}

echo PHP_EOL . "First missing IDs:\n";
foreach (array_slice($missing, 0, 30) as $row) {
    echo "{$row['id']} | {$row['created_at']} | {$row['status']} | {$row['client_name']}\n";
}

if ($extraIds) {
    echo PHP_EOL . "First migrated IDs not in old dump:\n";
    $extraStmt = $pdo->prepare(
        'SELECT id, created_at, receipt_status
         FROM receipts
         WHERE id = ?'
    );

    foreach (array_slice(array_keys($extraIds), 0, 30) as $id) {
        $extraStmt->execute([$id]);
        $row = $extraStmt->fetch();
        if ($row) {
            echo "{$row['id']} | {$row['created_at']} | {$row['receipt_status']}\n";
        }
    }
}
