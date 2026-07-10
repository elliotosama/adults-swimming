<?php

declare(strict_types=1);

$path = dirname(__DIR__) . '/swimming_academy_migrated.sql';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--file=')) {
        $path = substr($arg, 7);
    }
}

$sql = file_get_contents($path);
if ($sql === false) {
    throw new RuntimeException("Cannot read {$path}");
}

$changes = [
    'receipts' => 0,
    'transactions' => 0,
];

function clean_dump_insert_blocks(string $sql, string $table, int $fieldIndex, int &$changed): string
{
    $pattern = '/INSERT\s+INTO\s+`' . preg_quote($table, '/') . '`\s+VALUES\s*/i';
    $offset = 0;
    $out = '';

    while (preg_match($pattern, $sql, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $start = $match[0][1];
        $valuesStart = $start + strlen($match[0][0]);
        $end = strpos($sql, ';', $valuesStart);
        if ($end === false) {
            throw new RuntimeException("Unterminated {$table} INSERT block");
        }

        $out .= substr($sql, $offset, $valuesStart - $offset);
        $out .= clean_dump_values(substr($sql, $valuesStart, $end - $valuesStart), $fieldIndex, $changed);
        $out .= ';';
        $offset = $end + 1;
    }

    return $out . substr($sql, $offset);
}

function clean_dump_values(string $values, int $fieldIndex, int &$changed): string
{
    $length = strlen($values);
    $inString = false;
    $escaped = false;
    $depth = 0;
    $tuple = '';
    $out = '';

    for ($i = 0; $i < $length; $i++) {
        $ch = $values[$i];

        if ($depth === 0) {
            if ($ch === '(') {
                $depth = 1;
                $tuple = '(';
            } else {
                $out .= $ch;
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
            $tuple .= $ch;
            continue;
        }

        if ($ch === "'") {
            $inString = true;
            $tuple .= $ch;
            continue;
        }

        if ($ch === '(') {
            $depth++;
            $tuple .= $ch;
            continue;
        }

        if ($ch === ')') {
            $depth--;
            $tuple .= $ch;
            if ($depth === 0) {
                $out .= clean_dump_tuple($tuple, $fieldIndex, $changed);
                $tuple = '';
            }
            continue;
        }

        $tuple .= $ch;
    }

    return $out . $tuple;
}

function clean_dump_tuple(string $tuple, int $fieldIndex, int &$changed): string
{
    $inner = substr($tuple, 1, -1);
    $fields = split_dump_fields($inner);

    if (!array_key_exists($fieldIndex, $fields)) {
        return $tuple;
    }

    $before = $fields[$fieldIndex];
    $fields[$fieldIndex] = clean_dump_sql_string($fields[$fieldIndex]);
    if ($fields[$fieldIndex] !== $before) {
        $changed++;
    }

    return '(' . implode(',', $fields) . ')';
}

function split_dump_fields(string $inner): array
{
    $fields = [];
    $field = '';
    $inString = false;
    $escaped = false;
    $length = strlen($inner);

    for ($i = 0; $i < $length; $i++) {
        $ch = $inner[$i];

        if ($inString) {
            if ($escaped) {
                $escaped = false;
            } elseif ($ch === '\\') {
                $escaped = true;
            } elseif ($ch === "'") {
                $inString = false;
            }
            $field .= $ch;
            continue;
        }

        if ($ch === "'") {
            $inString = true;
            $field .= $ch;
            continue;
        }

        if ($ch === ',') {
            $fields[] = $field;
            $field = '';
            continue;
        }

        $field .= $ch;
    }

    $fields[] = $field;

    return $fields;
}

function clean_dump_sql_string(string $field): string
{
    if (!preg_match("/^'(.*)'$/s", $field, $match)) {
        return $field;
    }

    $value = $match[1];
    $cleaned = preg_replace(
        [
            '~uploads\\\\\\\\~i',
            '~uploads\\\\/~i',
            '~uploads/~i',
        ],
        '',
        $value
    );

    return $cleaned === $value ? $field : "'" . $cleaned . "'";
}

$sql = clean_dump_insert_blocks($sql, 'receipts', 15, $changes['receipts']);
$sql = clean_dump_insert_blocks($sql, 'transactions', 6, $changes['transactions']);

if (file_put_contents($path, $sql) === false) {
    throw new RuntimeException("Cannot write {$path}");
}

echo 'Receipt pdf_path values changed: ' . $changes['receipts'] . PHP_EOL;
echo 'Transaction attachment values changed: ' . $changes['transactions'] . PHP_EOL;
