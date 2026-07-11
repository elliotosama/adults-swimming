<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/database.php';
require __DIR__ . '/analyze_current_captains_against_old_db.php';

function tokens_for_match(string $name): array {
    $normalized = norm_captain_name($name);
    $tokens = preg_split('/\s+/u', trim(str_replace(
        ['أ', 'إ', 'آ', 'ة', 'ى', 'ؤ', 'ئ', 'ـ'],
        ['ا', 'ا', 'ا', 'ه', 'ي', 'و', 'ي', ''],
        mb_strtolower($name, 'UTF-8')
    )));
    $tokens = array_values(array_filter($tokens ?: [], static fn($t) => mb_strlen($t, 'UTF-8') >= 3));
    if (!$tokens && $normalized !== '') {
        $tokens = [$normalized];
    }
    return array_values(array_unique($tokens));
}

function match_score(string $currentName, string $oldName): int {
    $currentKey = norm_captain_name($currentName);
    $oldKey = norm_captain_name($oldName);

    if ($currentKey === $oldKey) {
        return 1000;
    }
    if ($currentKey !== '' && str_contains($oldKey, $currentKey)) {
        return 850 + min(100, mb_strlen($currentKey, 'UTF-8'));
    }
    if ($oldKey !== '' && str_contains($currentKey, $oldKey)) {
        return 800 + min(100, mb_strlen($oldKey, 'UTF-8'));
    }

    $currentTokens = tokens_for_match($currentName);
    $oldTokens = tokens_for_match($oldName);
    $common = array_intersect($currentTokens, $oldTokens);
    $score = count($common) * 120;

    if ($currentTokens && count($common) === count($currentTokens)) {
        $score += 250;
    }
    if ($oldTokens && count($common) === count($oldTokens)) {
        $score += 150;
    }

    return $score;
}

$oldSql = file_get_contents(dirname(__DIR__) . '/old_db.sql');
preg_match('/INSERT INTO `captains` VALUES\s*(.*?);/s', $oldSql, $match);
$oldCaptains = [];
foreach (split_sql_tuples($match[1]) as $tuple) {
    $fields = parse_sql_tuple($tuple);
    $oldCaptains[] = [
        'id' => (string) $fields[0],
        'name' => (string) $fields[1],
    ];
}

$db = get_db();
$current = $db->query("
    SELECT c.id, c.captain_name, COALESCE(r.receipts_count, 0) AS receipts_count
    FROM captains c
    LEFT JOIN (
        SELECT captain_id, COUNT(*) AS receipts_count
        FROM receipts
        GROUP BY captain_id
    ) r ON r.captain_id = c.id
    WHERE CAST(SUBSTRING(c.id, 3) AS UNSIGNED) > 305
    ORDER BY receipts_count DESC, c.id
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($current as $row) {
    $candidates = [];
    foreach ($oldCaptains as $old) {
        $score = match_score((string) $row['captain_name'], $old['name']);
        if ($score >= 350) {
            $candidates[] = ['score' => $score] + $old;
        }
    }
    usort($candidates, static fn($a, $b) => $b['score'] <=> $a['score']);
    $top = array_slice($candidates, 0, 5);
    echo $row['id'] . "\t" . $row['captain_name'] . "\treceipts=" . $row['receipts_count'];
    foreach ($top as $candidate) {
        echo "\t" . $candidate['score'] . ':' . $candidate['id'] . ':' . $candidate['name'];
    }
    echo PHP_EOL;
}
