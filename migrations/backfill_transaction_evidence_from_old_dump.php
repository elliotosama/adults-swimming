<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/database.php';

$apply = in_array('--apply', $argv, true);
$oldDump = dirname(__DIR__) . '/swimming_academy_old.sql';
$fromId = 0;
$toId = PHP_INT_MAX;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--old-dump=')) {
        $oldDump = substr($arg, 11);
    } elseif (str_starts_with($arg, '--from-id=')) {
        $fromId = (int) substr($arg, 10);
    } elseif (str_starts_with($arg, '--to-id=')) {
        $toId = (int) substr($arg, 8);
    }
}

function backfill_clean_value(?string $value): ?string
{
    if ($value === null || strtoupper($value) === 'NULL' || $value === '') {
        return null;
    }

    return $value;
}

function backfill_read_dump_utf8(string $path): string
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("Cannot read {$path}");
    }

    if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
        $converted = iconv('UTF-16', 'UTF-8//IGNORE', $raw);
        if ($converted === false) {
            throw new RuntimeException("Failed to convert {$path} from UTF-16 to UTF-8");
        }

        return $converted;
    }

    return $raw;
}

function backfill_each_value_tuple(string $values, callable $callback): void
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

function backfill_load_old_evidence(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $sql = backfill_read_dump_utf8($path);
    $pattern = '/INSERT\s+INTO\s+`receipts`\s+VALUES\s*/i';
    $offset = 0;
    $evidenceByReceipt = [];

    while (preg_match($pattern, $sql, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $start = $match[0][1] + strlen($match[0][0]);
        $end = strpos($sql, ';', $start);
        if ($end === false) {
            throw new RuntimeException('Unterminated receipts INSERT in old dump');
        }

        backfill_each_value_tuple(substr($sql, $start, $end - $start), function (array $row) use (&$evidenceByReceipt): void {
            $receiptId = (int) ($row[0] ?? 0);
            $attachment = backfill_clean_value($row[18] ?? null);
            if ($receiptId > 0 && $attachment !== null && $attachment !== '[]') {
                $evidenceByReceipt[$receiptId] = $attachment;
            }
        });

        $offset = $end + 1;
    }

    return $evidenceByReceipt;
}

$evidenceByReceipt = array_filter(
    backfill_load_old_evidence($oldDump),
    static fn (string $attachment, int $receiptId): bool => $receiptId >= $fromId && $receiptId <= $toId,
    ARRAY_FILTER_USE_BOTH
);

echo $apply ? "Applying evidence backfill...\n" : "Dry run only. Use --apply to write changes.\n";
echo "ID range: {$fromId} - {$toId}\n";
echo 'Old evidence records loaded: ' . count($evidenceByReceipt) . PHP_EOL;

if (!$evidenceByReceipt) {
    exit(0);
}

$db = get_db();
$select = $db->prepare("
    SELECT id, receipt_id
    FROM transactions
    WHERE receipt_id = ?
      AND (attachment IS NULL OR attachment = '' OR attachment = '[]')
      AND type = 'payment'
");
$update = $db->prepare('UPDATE transactions SET attachment = ? WHERE id = ?');

$checked = 0;
$wouldUpdate = 0;

if ($apply) {
    $db->beginTransaction();
}

try {
    foreach ($evidenceByReceipt as $receiptId => $attachment) {
        $select->execute([$receiptId]);
        $rows = $select->fetchAll(PDO::FETCH_ASSOC);
        $checked += count($rows);

        foreach ($rows as $row) {
            $wouldUpdate++;
            if ($apply) {
                $update->execute([$attachment, $row['id']]);
            }
        }
    }

    if ($apply) {
        $db->commit();
    }
} catch (Throwable $e) {
    if ($apply && $db->inTransaction()) {
        $db->rollBack();
    }
    throw $e;
}

echo "Blank transaction evidence rows found: {$checked}\n";
echo ($apply ? 'Updated rows: ' : 'Rows that would update: ') . $wouldUpdate . PHP_EOL;
