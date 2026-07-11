<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/analyze_current_captains_against_old_db.php';

function captain_words(string $name): array {
    $name = str_replace(['أ', 'إ', 'آ', 'ة', 'ى', 'ؤ', 'ئ', 'ـ'], ['ا', 'ا', 'ا', 'ه', 'ي', 'و', 'ي', ''], mb_strtolower($name, 'UTF-8'));
    $parts = preg_split('/\s+/u', trim($name));
    return array_values(array_filter($parts ?: [], static fn(string $part): bool => mb_strlen($part, 'UTF-8') >= 3));
}

function candidate_score(string $currentName, string $oldName): int {
    $currentKey = norm_captain_name($currentName);
    $oldKey = norm_captain_name($oldName);
    if ($currentKey === '' || $oldKey === '') return 0;
    if ($currentKey === $oldKey) return 1000;
    if (str_contains($oldKey, $currentKey)) return 850 + min(100, mb_strlen($currentKey, 'UTF-8'));

    $currentWords = captain_words($currentName);
    $oldWords = captain_words($oldName);
    $common = array_intersect($currentWords, $oldWords);
    if ($currentWords && count($common) === count($currentWords) && count($currentWords) >= 2) {
        return 700 + count($common) * 40;
    }
    return 0;
}

$oldSql = file_get_contents(dirname(__DIR__) . '/old_db.sql');
preg_match('/INSERT INTO `captains` VALUES\s*(.*?);/s', $oldSql, $captainMatch);

$oldCaptains = [];
foreach (split_sql_tuples($captainMatch[1]) as $tuple) {
    $fields = parse_sql_tuple($tuple);
    if (empty($fields[2])) continue;
    $oldCaptains[] = [
        'id' => (string) $fields[0],
        'name' => (string) $fields[1],
        'phone' => (string) $fields[2],
        'created_at' => substr((string) ($fields[3] ?? ''), 0, 10) ?: null,
    ];
}

$db = get_db();
$current = $db->query("
    SELECT id, captain_name
    FROM captains
    WHERE phone_number IS NULL OR TRIM(phone_number) = ''
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

$existing = [];
foreach ($db->query("SELECT id, captain_name, phone_number FROM captains") as $row) {
    $existing[(string) $row['id']] = $row;
}

$insertTarget = $db->prepare("
    INSERT INTO captains (id, captain_name, phone_number, created_at, created_by, visible)
    VALUES (:id, :captain_name, :phone_number, :created_at, NULL, 1)
    ON DUPLICATE KEY UPDATE
        captain_name = VALUES(captain_name),
        phone_number = VALUES(phone_number),
        created_at = VALUES(created_at),
        visible = 1
");
$insertBranches = $db->prepare("
    INSERT IGNORE INTO captain_branch (branch_id, captain_id)
    SELECT branch_id, :target_id
    FROM captain_branch
    WHERE captain_id = :source_id
");
$updateReceipts = $db->prepare("UPDATE receipts SET captain_id = :target_id WHERE captain_id = :source_id");
$deleteBranches = $db->prepare("DELETE FROM captain_branch WHERE captain_id = ?");
$deleteCaptain = $db->prepare("DELETE FROM captains WHERE id = ?");

$merged = [];
$skipped = [];

$db->beginTransaction();
try {
    foreach ($current as $source) {
        $sourceId = (string) $source['id'];
        $sourceName = (string) $source['captain_name'];

        $candidates = [];
        foreach ($oldCaptains as $old) {
            $score = candidate_score($sourceName, $old['name']);
            if ($score >= 850) {
                $candidates[] = ['score' => $score] + $old;
            }
        }

        usort($candidates, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $topScore = $candidates[0]['score'] ?? 0;
        $top = array_values(array_filter($candidates, static fn(array $candidate): bool => $candidate['score'] === $topScore));

        if (count($top) !== 1) {
            $skipped[] = [$sourceId, $sourceName, 'ambiguous_or_no_unique_match'];
            continue;
        }

        $target = $top[0];
        $targetId = $target['id'];

        if ($sourceId === $targetId) {
            $insertTarget->execute([
                ':id' => $targetId,
                ':captain_name' => $target['name'],
                ':phone_number' => $target['phone'],
                ':created_at' => $target['created_at'],
            ]);
            $merged[] = [$sourceId, $targetId, $sourceName, $target['name'], 'restored_phone'];
            continue;
        }

        if (isset($existing[$targetId])) {
            $targetExisting = $existing[$targetId];
            $targetPhone = trim((string) ($targetExisting['phone_number'] ?? ''));
            if ($targetPhone === '' && norm_captain_name((string) $targetExisting['captain_name']) !== norm_captain_name($target['name'])) {
                $skipped[] = [$sourceId, $sourceName, "target_id_conflict:{$targetId}"];
                continue;
            }
        }

        $insertTarget->execute([
            ':id' => $targetId,
            ':captain_name' => $target['name'],
            ':phone_number' => $target['phone'],
            ':created_at' => $target['created_at'],
        ]);
        $insertBranches->execute([
            ':source_id' => $sourceId,
            ':target_id' => $targetId,
        ]);
        $updateReceipts->execute([
            ':source_id' => $sourceId,
            ':target_id' => $targetId,
        ]);
        $deleteBranches->execute([$sourceId]);
        $deleteCaptain->execute([$sourceId]);

        $merged[] = [$sourceId, $targetId, $sourceName, $target['name'], 'merged'];
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

echo "merged_or_restored=" . count($merged) . PHP_EOL;
foreach ($merged as $row) {
    echo implode("\t", $row) . PHP_EOL;
}

echo "skipped=" . count($skipped) . PHP_EOL;
foreach (array_slice($skipped, 0, 80) as $row) {
    echo implode("\t", $row) . PHP_EOL;
}
