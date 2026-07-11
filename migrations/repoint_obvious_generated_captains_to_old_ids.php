<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/database.php';

$db = get_db();

$mappings = [
    'c-448' => 'c-1',
    'c-458' => 'c-100',
    'c-469' => 'c-81',
    'c-476' => 'c-85',
    'c-486' => 'c-94',
    'c-304' => 'c-72',
];

$oldCaptainsToRestore = [
    'c-304' => [
        'captain_name' => 'عبدالعليم السيد عبدالعليم المحيص',
        'phone_number' => '01004791935',
        'created_at' => '2026-07-03',
        'created_by' => null,
        'visible' => 1,
    ],
];

$insertBranches = $db->prepare("
    INSERT IGNORE INTO captain_branch (branch_id, captain_id)
    SELECT branch_id, :target_id
    FROM captain_branch
    WHERE captain_id = :source_id
");
$updateReceipts = $db->prepare("UPDATE receipts SET captain_id = :target_id WHERE captain_id = :source_id");
$deleteBranches = $db->prepare("DELETE FROM captain_branch WHERE captain_id = ?");
$deleteCaptain = $db->prepare("DELETE FROM captains WHERE id = ?");
$restoreCaptain = $db->prepare("
    INSERT INTO captains (id, captain_name, phone_number, created_at, created_by, visible)
    VALUES (:id, :captain_name, :phone_number, :created_at, :created_by, :visible)
    ON DUPLICATE KEY UPDATE
        captain_name = VALUES(captain_name),
        phone_number = VALUES(phone_number),
        created_at = VALUES(created_at),
        created_by = VALUES(created_by),
        visible = VALUES(visible)
");

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

    foreach ($oldCaptainsToRestore as $id => $captain) {
        $restoreCaptain->execute([
            ':id' => $id,
            ':captain_name' => $captain['captain_name'],
            ':phone_number' => $captain['phone_number'],
            ':created_at' => $captain['created_at'],
            ':created_by' => $captain['created_by'],
            ':visible' => $captain['visible'],
        ]);
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

foreach ($mappings as $sourceId => $targetId) {
    echo "{$sourceId} -> {$targetId}" . PHP_EOL;
}
echo "restored old c-304" . PHP_EOL;
