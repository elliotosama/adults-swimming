<?php

declare(strict_types=1);

$files = array_slice($argv, 1);
if (!$files) {
    $files = glob(dirname(__DIR__) . '/*.sql') ?: [];
}

function readSqlFile(string $path): string
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("Cannot read {$path}");
    }

    if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
        $converted = iconv('UTF-16', 'UTF-8//IGNORE', $raw);
        if ($converted === false) {
            throw new RuntimeException("Cannot convert {$path} from UTF-16");
        }

        return $converted;
    }

    return $raw;
}

function countTuples(string $values): int
{
    $length = strlen($values);
    $inString = false;
    $escaped = false;
    $depth = 0;
    $count = 0;

    for ($i = 0; $i < $length; $i++) {
        $ch = $values[$i];

        if ($depth === 0) {
            if ($ch === '(') {
                $depth = 1;
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
            continue;
        }

        if ($ch === "'") {
            $inString = true;
            continue;
        }

        if ($ch === '(') {
            $depth++;
            continue;
        }

        if ($ch === ')') {
            $depth--;
            if ($depth === 0) {
                $count++;
            }
        }
    }

    return $count;
}

foreach ($files as $file) {
    $sql = readSqlFile($file);
    $pattern = '/INSERT\s+INTO\s+`receipts`(?:\s*\([^;]*?\))?\s+VALUES\s*/i';
    $offset = 0;
    $blocks = 0;
    $rows = 0;

    while (preg_match($pattern, $sql, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $blocks++;
        $start = $match[0][1] + strlen($match[0][0]);
        $end = strpos($sql, ';', $start);
        if ($end === false) {
            throw new RuntimeException("Unterminated receipts INSERT in {$file}");
        }

        $rows += countTuples(substr($sql, $start, $end - $start));
        $offset = $end + 1;
    }

    echo basename($file) . " receipts_insert_blocks={$blocks} receipt_rows={$rows}" . PHP_EOL;
}
