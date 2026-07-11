<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/analyze_current_captains_against_old_db.php';

function words_for_name(string $name): array {
    $name = str_replace(['أ', 'إ', 'آ', 'ة', 'ى', 'ؤ', 'ئ', 'ـ'], ['ا', 'ا', 'ا', 'ه', 'ي', 'و', 'ي', ''], mb_strtolower($name, 'UTF-8'));
    $parts = preg_split('/\s+/u', trim($name));
    return array_values(array_filter($parts ?: [], static fn(string $part): bool => mb_strlen($part, 'UTF-8') >= 3));
}

function old_match_score(string $coachName, string $oldName): int {
    $coachKey = norm_captain_name($coachName);
    $oldKey = norm_captain_name($oldName);
    if ($coachKey === '' || $oldKey === '') return 0;
    if ($coachKey === $oldKey) return 1000;
    if (str_contains($oldKey, $coachKey)) return 850 + min(100, mb_strlen($coachKey, 'UTF-8'));
    if (str_contains($coachKey, $oldKey)) return 800 + min(100, mb_strlen($oldKey, 'UTF-8'));

    $coachWords = words_for_name($coachName);
    $oldWords = words_for_name($oldName);
    $common = array_intersect($coachWords, $oldWords);
    $score = count($common) * 100;
    if ($coachWords && count($common) === count($coachWords)) $score += 220;
    return $score;
}

function best_old_captain_for_coach(string $coachName, array $oldCaptains): ?array {
    $best = null;
    foreach ($oldCaptains as $old) {
        $score = old_match_score($coachName, $old['name']);
        if ($score < 350) continue;
        if ($best === null || $score > $best['score']) {
            $best = ['score' => $score] + $old;
        } elseif ($score === $best['score']) {
            $best['ambiguous'] = true;
        }
    }
    return $best;
}

$oldSql = file_get_contents(dirname(__DIR__) . '/old_db.sql');

if (!preg_match('/INSERT INTO `captains` VALUES\s*(.*?);/s', $oldSql, $captainMatch)) {
    throw new RuntimeException('Could not find old captains insert.');
}

$receiptInsertNeedle = 'INSERT INTO `receipts` VALUES ';
$receiptInsertPos = strpos($oldSql, $receiptInsertNeedle);
if ($receiptInsertPos === false) {
    throw new RuntimeException('Could not find old receipts insert.');
}
$receiptValuesStart = $receiptInsertPos + strlen($receiptInsertNeedle);
$receiptValuesEnd = strpos($oldSql, ";\n", $receiptValuesStart);
if ($receiptValuesEnd === false) {
    $receiptValuesEnd = strpos($oldSql, ';', $receiptValuesStart);
}
if ($receiptValuesEnd === false) {
    throw new RuntimeException('Could not find end of old receipts insert.');
}
$receiptValues = substr($oldSql, $receiptValuesStart, $receiptValuesEnd - $receiptValuesStart);

$oldCaptains = [];
foreach (split_sql_tuples($captainMatch[1]) as $tuple) {
    $fields = parse_sql_tuple($tuple);
    $oldCaptains[] = [
        'id' => (string) $fields[0],
        'name' => (string) $fields[1],
        'phone' => $fields[2] ?? null,
    ];
}

$oldReceiptCoach = [];
foreach (split_sql_tuples($receiptValues) as $tuple) {
    $fields = parse_sql_tuple($tuple);
    $receiptId = (string) ($fields[0] ?? '');
    $coachName = trim((string) ($fields[7] ?? ''));
    if ($receiptId !== '' && $coachName !== '') {
        $oldReceiptCoach[$receiptId] = $coachName;
    }
}

$db = get_db();
$rows = $db->query("
    SELECT c.id AS captain_id,
           c.captain_name,
           r.id AS receipt_id
    FROM captains c
    JOIN receipts r ON r.captain_id = c.id
    WHERE c.phone_number IS NULL OR TRIM(c.phone_number) = ''
    ORDER BY c.id, r.id
")->fetchAll(PDO::FETCH_ASSOC);

$grouped = [];
foreach ($rows as $row) {
    $captainId = (string) $row['captain_id'];
    $grouped[$captainId]['captain_name'] = (string) $row['captain_name'];
    $grouped[$captainId]['receipt_ids'][] = (string) $row['receipt_id'];
}

foreach ($grouped as $captainId => $info) {
    $votes = [];
    $missing = 0;
    foreach ($info['receipt_ids'] as $receiptId) {
        $coachName = $oldReceiptCoach[$receiptId] ?? '';
        if ($coachName === '') {
            $missing++;
            continue;
        }
        $best = best_old_captain_for_coach($coachName, $oldCaptains);
        if (!$best || !empty($best['ambiguous'])) {
            $votes['UNRESOLVED|' . $coachName] = ($votes['UNRESOLVED|' . $coachName] ?? 0) + 1;
            continue;
        }
        $key = $best['id'] . '|' . $best['name'];
        $votes[$key] = ($votes[$key] ?? 0) + 1;
    }

    arsort($votes);
    $topKey = array_key_first($votes);
    $topVotes = $topKey !== null ? $votes[$topKey] : 0;
    $total = count($info['receipt_ids']);

    echo $captainId . "\t" . $info['captain_name'] . "\treceipts={$total}\tmissing_old={$missing}";
    if ($topKey !== null) {
        echo "\ttop={$topVotes}/{$total}\t{$topKey}";
    }
    echo PHP_EOL;
}
