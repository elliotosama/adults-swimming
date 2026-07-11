<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/database.php';

$mappings = [
    // old/short generated ID => authoritative old DB captain ID
    'c-445' => 'c-115', // محمود هادي -> محمود هادي عبد المعبود
    'c-440' => 'c-214', // محمود محمد عبد السلام محمد -> محمود محمد عبد السلام محمد غانم
    'c-449' => 'c-24',  // محمود سامي -> محمود سامي احمد
    'c-472' => 'c-110', // محمد عبد القادر -> محمد عبد القادر احمد
    'c-463' => 'c-66',  // محمد سيد -> محمد سيد السيد
    'c-464' => 'c-30',  // محمد سعد -> محمد سعد منصور
    'c-462' => 'c-25',  // محمد جمال -> محمد جمال محمد
];

$db = get_db();

$insertBranches = $db->prepare("
    INSERT IGNORE INTO captain_branch (branch_id, captain_id)
    SELECT branch_id, :target_id
    FROM captain_branch
    WHERE captain_id = :source_id
");
$updateReceipts = $db->prepare("UPDATE receipts SET captain_id = :target_id WHERE captain_id = :source_id");
$deleteBranches = $db->prepare("DELETE FROM captain_branch WHERE captain_id = ?");
$deleteCaptain = $db->prepare("DELETE FROM captains WHERE id = ?");

$db->beginTransaction();
try {
    foreach ($mappings as $sourceId => $targetId) {
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
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

foreach ($mappings as $sourceId => $targetId) {
    echo "{$sourceId} -> {$targetId}" . PHP_EOL;
}
