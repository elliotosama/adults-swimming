<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/database.php';

function captain_dedupe_key(string $name): string {
    $name = trim(mb_strtolower($name, 'UTF-8'));
    return str_replace(
        [' ', "\t", 'ـ', 'أ', 'إ', 'آ', 'ة', 'ى', 'ؤ'],
        ['',  '',   '',  'ا', 'ا', 'ا', 'ه', 'ي', 'و'],
        $name
    );
}

$db = get_db();

$captains = $db->query("
    SELECT
        c.id,
        c.captain_name,
        c.phone_number,
        c.visible,
        COALESCE(r.receipt_count, 0) AS receipt_count,
        COALESCE(cb.branch_count, 0) AS branch_count
    FROM captains c
    LEFT JOIN (
        SELECT captain_id, COUNT(*) AS receipt_count
        FROM receipts
        GROUP BY captain_id
    ) r ON r.captain_id = c.id
    LEFT JOIN (
        SELECT captain_id, COUNT(*) AS branch_count
        FROM captain_branch
        GROUP BY captain_id
    ) cb ON cb.captain_id = c.id
")->fetchAll(PDO::FETCH_ASSOC);

$groups = [];
foreach ($captains as $captain) {
    $groups[captain_dedupe_key((string) $captain['captain_name'])][] = $captain;
}

$insertBranches = $db->prepare("
    INSERT IGNORE INTO captain_branch (branch_id, captain_id)
    SELECT branch_id, :keep_id
    FROM captain_branch
    WHERE captain_id = :duplicate_id
");
$updateReceipts = $db->prepare("
    UPDATE receipts
    SET captain_id = :keep_id
    WHERE captain_id = :duplicate_id
");
$deleteBranches = $db->prepare("DELETE FROM captain_branch WHERE captain_id = ?");
$deleteCaptain  = $db->prepare("DELETE FROM captains WHERE id = ?");

$merged = [];

$db->beginTransaction();
try {
    foreach ($groups as $key => $group) {
        if (count($group) < 2) {
            continue;
        }

        usort($group, static function (array $a, array $b): int {
            return [
                -(int) $a['receipt_count'],
                -(int) $a['branch_count'],
                -(int) $a['visible'],
                empty(trim((string) $a['phone_number'])) ? 1 : 0,
                (string) $a['id'],
            ] <=> [
                -(int) $b['receipt_count'],
                -(int) $b['branch_count'],
                -(int) $b['visible'],
                empty(trim((string) $b['phone_number'])) ? 1 : 0,
                (string) $b['id'],
            ];
        });

        $keep = array_shift($group);

        foreach ($group as $duplicate) {
            $insertBranches->execute([
                ':keep_id'       => $keep['id'],
                ':duplicate_id'  => $duplicate['id'],
            ]);
            $updateReceipts->execute([
                ':keep_id'       => $keep['id'],
                ':duplicate_id'  => $duplicate['id'],
            ]);
            $deleteBranches->execute([$duplicate['id']]);
            $deleteCaptain->execute([$duplicate['id']]);

            $merged[] = [
                'key'       => $key,
                'duplicate' => $duplicate,
                'keep'      => $keep,
            ];
        }
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

echo "Merged duplicate captains: " . count($merged) . PHP_EOL;
foreach ($merged as $row) {
    echo $row['duplicate']['id']
        . " (" . $row['duplicate']['captain_name'] . ") -> "
        . $row['keep']['id']
        . " (" . $row['keep']['captain_name'] . ")"
        . PHP_EOL;
}
