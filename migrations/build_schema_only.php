<?php

declare(strict_types=1);

$source = $argv[1] ?? __DIR__ . '/../swimming_academy.sql';
$target = $argv[2] ?? __DIR__ . '/swimming_academy_schema_only.sql';

if (!is_file($source)) {
    fwrite(STDERR, "Source dump not found: {$source}" . PHP_EOL);
    exit(1);
}

$in = fopen($source, 'rb');
if ($in === false) {
    fwrite(STDERR, "Cannot open source dump: {$source}" . PHP_EOL);
    exit(1);
}

$out = fopen($target, 'wb');
if ($out === false) {
    fclose($in);
    fwrite(STDERR, "Cannot write schema output: {$target}" . PHP_EOL);
    exit(1);
}

$skippingData = false;
$currentTable = null;

while (($line = fgets($in)) !== false) {
    if (preg_match('/^-- Dumping data for table /', $line)) {
        $skippingData = true;
        continue;
    }

    if ($skippingData && preg_match('/^-- Table structure for table /', $line)) {
        $skippingData = false;
    }

    if ($skippingData && preg_match('/^\/\*!40103 SET TIME_ZONE=@OLD_TIME_ZONE \*\//', $line)) {
        $skippingData = false;
    }

    if ($skippingData) {
        continue;
    }

    if (preg_match('/@OLD_|@saved_cs_client|OLD_NOTE_VERBOSITY/', $line)) {
        continue;
    }

    if (preg_match('/^CREATE TABLE `([^`]+)`/', $line, $matches)) {
        $currentTable = $matches[1];
    }

    if (preg_match('/^\)\s*$/', $line)) {
        $line = ");" . PHP_EOL;
        $currentTable = null;
    } elseif (preg_match('/^\) /', $line)) {
        if ($currentTable === 'captains') {
            $line = preg_replace('/ AUTO_INCREMENT=\d+/', '', $line);
        } else {
            $line = preg_replace('/ AUTO_INCREMENT=\d+/', ' AUTO_INCREMENT=1', $line);
        }
        $currentTable = null;
    } else {
        $line = preg_replace('/ AUTO_INCREMENT=\d+/', ' AUTO_INCREMENT=1', $line);
    }

    // Preserve old captain ids like "c-1" instead of coercing them to integers.
    if ($currentTable === 'captains' && preg_match('/^\s+`id` int\(11\) NOT NULL AUTO_INCREMENT,/', $line)) {
        $line = str_replace('`id` int(11) NOT NULL AUTO_INCREMENT', '`id` varchar(10) NOT NULL', $line);
    }

    if (preg_match('/^\s+`captain_id` int\(11\) NOT NULL,/', $line)) {
        $line = str_replace('`captain_id` int(11)', '`captain_id` varchar(10)', $line);
    }

    fwrite($out, $line);
}

fclose($in);
fclose($out);

echo "Wrote schema-only dump to {$target}" . PHP_EOL;
