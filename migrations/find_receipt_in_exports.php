<?php

declare(strict_types=1);

$needle = $argv[1] ?? '26070465';
$files = array_slice($argv, 2);
if (!$files) {
    $files = glob(dirname(__DIR__) . '/../Downloads/receipts*.xlsx') ?: [];
}

function readSharedStrings(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false) {
        return [];
    }

    $reader = new XMLReader();
    $reader->XML($xml);
    $strings = [];
    $current = '';
    $inItem = false;

    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
            $inItem = true;
            $current = '';
        } elseif ($inItem && $reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't') {
            $current .= $reader->readString();
        } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si') {
            $strings[] = $current;
            $inItem = false;
        }
    }

    return $strings;
}

function columnIndex(string $cellRef): int
{
    preg_match('/^[A-Z]+/', $cellRef, $matches);
    $letters = $matches[0] ?? '';
    $index = 0;
    for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
        $index = $index * 26 + (ord($letters[$i]) - 64);
    }

    return $index - 1;
}

function scanSheetXml(string $xml, array $sharedStrings, string $needle): array
{
    $reader = new XMLReader();
    $reader->XML($xml);

    $rowNumber = 0;
    $rows = 0;
    $matches = [];
    $row = [];

    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row') {
            $rows++;
            $rowNumber = (int) ($reader->getAttribute('r') ?: $rows);
            $row = [];
        } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'c') {
            $cellRef = $reader->getAttribute('r') ?: '';
            $type = $reader->getAttribute('t') ?: '';
            $idx = columnIndex($cellRef);
            $value = '';

            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'v') {
                    $value = $reader->readString();
                } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'is') {
                    $value = $reader->readString();
                } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'c') {
                    break;
                }
            }

            if ($type === 's') {
                $value = $sharedStrings[(int) $value] ?? $value;
            }

            $row[$idx] = $value;
        } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'row') {
            ksort($row);
            $line = implode("\t", $row);
            if (str_contains($line, $needle)) {
                $matches[] = ['row' => $rowNumber, 'line' => $line];
            }
        }
    }

    return ['rows' => $rows, 'matches' => $matches];
}

foreach ($files as $file) {
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        continue;
    }

    $sharedStrings = readSharedStrings($zip);
    $totalRows = 0;
    $matches = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
            continue;
        }

        $xml = $zip->getFromName($name);
        if ($xml === false) {
            continue;
        }

        $result = scanSheetXml($xml, $sharedStrings, $needle);
        $totalRows += $result['rows'];
        foreach ($result['matches'] as $match) {
            $matches[] = $name . ':' . $match['row'] . ' ' . $match['line'];
        }
    }

    $zip->close();

    if ($matches) {
        echo $file . " rows={$totalRows}" . PHP_EOL;
        foreach ($matches as $match) {
            echo $match . PHP_EOL;
        }
    }
}
