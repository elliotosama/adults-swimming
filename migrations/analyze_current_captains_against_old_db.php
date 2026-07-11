<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

function norm_captain_name(string $name): string {
    $name = trim(mb_strtolower($name, 'UTF-8'));
    return str_replace(
        [' ', "\t", "\r", "\n", 'ـ', 'أ', 'إ', 'آ', 'ة', 'ى', 'ؤ', 'ئ'],
        ['',  '',   '',   '',   '',  'ا', 'ا', 'ا', 'ه', 'ي', 'و', 'ي'],
        $name
    );
}

function split_sql_tuples(string $values): array {
    $tuples = [];
    $buf = '';
    $depth = 0;
    $inQuote = false;
    $len = strlen($values);

    for ($i = 0; $i < $len; $i++) {
        $ch = $values[$i];
        $next = $values[$i + 1] ?? '';

        if ($ch === "'" && $next === "'") {
            $buf .= "''";
            $i++;
            continue;
        }

        if ($ch === "'" && ($i === 0 || $values[$i - 1] !== '\\')) {
            $inQuote = !$inQuote;
        }

        if (!$inQuote && $ch === '(') {
            $depth++;
            if ($depth === 1) {
                $buf = '';
                continue;
            }
        }

        if (!$inQuote && $ch === ')') {
            $depth--;
            if ($depth === 0) {
                $tuples[] = $buf;
                $buf = '';
                continue;
            }
        }

        if ($depth > 0) {
            $buf .= $ch;
        }
    }

    return $tuples;
}

function parse_sql_tuple(string $tuple): array {
    $fields = str_getcsv($tuple, ',', "'", "\\");
    return array_map(static function ($value) {
        $value = trim((string) $value);
        if (strcasecmp($value, 'NULL') === 0) {
            return null;
        }
        return str_replace(["\\'", "''"], ["'", "'"], $value);
    }, $fields);
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}

$oldSql = file_get_contents(dirname(__DIR__) . '/old_db.sql');
if (!preg_match('/INSERT INTO `captains` VALUES\s*(.*?);/s', $oldSql, $match)) {
    fwrite(STDERR, "Could not find old_db captains insert.\n");
    exit(1);
}

$oldByKey = [];
foreach (split_sql_tuples($match[1]) as $tuple) {
    $fields = parse_sql_tuple($tuple);
    $id = (string) ($fields[0] ?? '');
    $name = (string) ($fields[1] ?? '');
    $key = norm_captain_name($name);
    $oldByKey[$key][] = [
        'id' => $id,
        'name' => $name,
        'phone' => $fields[2] ?? null,
        'created_at' => $fields[3] ?? null,
        'branches' => $fields[4] ?? null,
    ];
}

$db = get_db();
$rows = $db->query("
    SELECT c.id, c.captain_name,
           COALESCE(r.receipts_count, 0) AS receipts_count
    FROM captains c
    LEFT JOIN (
        SELECT captain_id, COUNT(*) AS receipts_count
        FROM receipts
        GROUP BY captain_id
    ) r ON r.captain_id = c.id
    ORDER BY CAST(SUBSTRING(c.id, 3) AS UNSIGNED), c.id
")->fetchAll(PDO::FETCH_ASSOC);

$matched = 0;
$ambiguous = 0;
$unmatched = 0;

foreach ($rows as $row) {
    $id = (string) $row['id'];
    $key = norm_captain_name((string) $row['captain_name']);
    $matches = $oldByKey[$key] ?? [];
    if (count($matches) === 1) {
        $matched++;
        if ($id !== $matches[0]['id']) {
            echo "MATCH\t{$id}\t{$row['captain_name']}\told={$matches[0]['id']}\t{$matches[0]['name']}\treceipts={$row['receipts_count']}" . PHP_EOL;
        }
    } elseif (count($matches) > 1) {
        $ambiguous++;
        echo "AMBIG\t{$id}\t{$row['captain_name']}\told=" . implode(',', array_column($matches, 'id')) . "\treceipts={$row['receipts_count']}" . PHP_EOL;
    } else {
        $unmatched++;
        echo "NO_MATCH\t{$id}\t{$row['captain_name']}\treceipts={$row['receipts_count']}" . PHP_EOL;
    }
}

fwrite(STDERR, "matched={$matched} ambiguous={$ambiguous} unmatched={$unmatched}\n");
