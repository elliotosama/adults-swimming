<?php
/**
 * fix_missing_images.php
 *
 * Finds old receipt images (old_db.receipts.attachment, JSON array of paths)
 * that are missing from the new database (new_db.transactions.attachment),
 * matched via transactions.receipt_id == old receipts.id, and fixes it by:
 *
 *   1. Filling any existing transaction rows for that receipt_id whose
 *      `attachment` is NULL/empty with a missing filename (UPDATE).
 *   2. If there are still missing filenames left over after filling empty
 *      slots, inserting new transaction rows with just:
 *        receipt_id, attachment, type = 'payment', created_at = today
 *      (all other columns left NULL/default) (INSERT).
 *
 * Receipts whose id does NOT exist in the new `receipts` table are SKIPPED
 * and reported, since transactions.receipt_id has an FK constraint to
 * receipts(id) and the insert/update would fail otherwise.
 *
 * ------------------------------------------------------------------
 * SAFETY: DRY RUN BY DEFAULT
 * ------------------------------------------------------------------
 * By default this script only PRINTS what it would do. No writes happen.
 * To actually apply the changes, run with --commit:
 *
 *   php fix_missing_images.php            (dry run, safe, no writes)
 *   php fix_missing_images.php --commit   (actually applies changes)
 *
 * All writes (for a single run) happen inside one DB transaction on the
 * new database. If anything unexpected fails mid-run, it rolls back.
 * ------------------------------------------------------------------
 */

// ============================================================
// CONFIG - fill these in
// ============================================================

// OLD database connection
$OLD_DB_HOST = '127.0.0.1';
$OLD_DB_NAME = 'old_database_name';
$OLD_DB_USER = 'old_db_user';
$OLD_DB_PASS = 'old_db_password';

// NEW database connection
$NEW_DB_HOST = '127.0.0.1';
$NEW_DB_NAME = 'new_database_name';
$NEW_DB_USER = 'new_db_user';
$NEW_DB_PASS = 'new_db_password';

// Table/column names
$OLD_TABLE = 'receipts';
$OLD_ID_COL = 'id';
$OLD_ATTACHMENT_COL = 'attachment';

$NEW_RECEIPTS_TABLE = 'receipts';
$NEW_RECEIPTS_ID_COL = 'id';

$NEW_TRANSACTIONS_TABLE = 'transactions';
$NEW_TX_ID_COL = 'id';
$NEW_TX_RECEIPT_ID_COL = 'receipt_id';
$NEW_TX_ATTACHMENT_COL = 'attachment';

// ============================================================
// CLI FLAG
// ============================================================

$COMMIT = in_array('--commit', $argv ?? [], true);

echo $COMMIT
    ? "*** RUNNING IN COMMIT MODE - CHANGES WILL BE WRITTEN ***" . PHP_EOL
    : "*** DRY RUN MODE - no changes will be written (use --commit to apply) ***" . PHP_EOL;
echo PHP_EOL;

// ============================================================
// CONNECT
// ============================================================

function connect(string $host, string $dbname, string $user, string $pass): PDO
{
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

try {
    $oldDb = connect($OLD_DB_HOST, $OLD_DB_NAME, $OLD_DB_USER, $OLD_DB_PASS);
    $newDb = connect($NEW_DB_HOST, $NEW_DB_NAME, $NEW_DB_USER, $NEW_DB_PASS);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . PHP_EOL);
}

// ============================================================
// HELPERS
// ============================================================

function extractFilename(string $path): string
{
    $normalized = str_replace('\\', '/', $path);
    $parts = explode('/', $normalized);
    return trim(end($parts));
}

function decodeOldAttachment(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $filenames = [];
        foreach ($decoded as $path) {
            if (is_string($path) && trim($path) !== '') {
                $filenames[] = extractFilename($path);
            }
        }
        return $filenames;
    }

    return [extractFilename($raw)];
}

// ============================================================
// FETCH OLD RECEIPTS WITH ATTACHMENTS
// ============================================================

echo "Fetching old receipts..." . PHP_EOL;
$oldStmt = $oldDb->query("SELECT `{$OLD_ID_COL}` AS id, `{$OLD_ATTACHMENT_COL}` AS attachment FROM `{$OLD_TABLE}`");
$oldReceipts = $oldStmt->fetchAll();

$oldImagesByReceipt = [];
foreach ($oldReceipts as $row) {
    $filenames = decodeOldAttachment($row['attachment']);
    if (!empty($filenames)) {
        $oldImagesByReceipt[(int)$row['id']] = $filenames;
    }
}
echo "Old receipts with at least one image: " . count($oldImagesByReceipt) . PHP_EOL;

// ============================================================
// FETCH NEW receipts IDs (to validate FK before insert/update)
// ============================================================

echo "Fetching new receipts ids..." . PHP_EOL;
$newReceiptIdsStmt = $newDb->query("SELECT `{$NEW_RECEIPTS_ID_COL}` AS id FROM `{$NEW_RECEIPTS_TABLE}`");
$existingNewReceiptIds = [];
foreach ($newReceiptIdsStmt->fetchAll() as $row) {
    $existingNewReceiptIds[(int)$row['id']] = true;
}
echo "New receipts found: " . count($existingNewReceiptIds) . PHP_EOL;

// ============================================================
// FETCH NEW TRANSACTIONS (existing rows per receipt_id)
// ============================================================

echo "Fetching new transactions..." . PHP_EOL;
$newTxStmt = $newDb->query(
    "SELECT `{$NEW_TX_ID_COL}` AS tx_id, `{$NEW_TX_RECEIPT_ID_COL}` AS receipt_id, `{$NEW_TX_ATTACHMENT_COL}` AS attachment
     FROM `{$NEW_TRANSACTIONS_TABLE}`"
);
$txRows = $newTxStmt->fetchAll();

// Group transaction rows by receipt_id
$txByReceipt = []; // receipt_id => array of ['tx_id'=>.., 'attachment'=>..]
foreach ($txRows as $row) {
    $rid = (int)$row['receipt_id'];
    $txByReceipt[$rid][] = [
        'tx_id' => (int)$row['tx_id'],
        'attachment' => $row['attachment'] !== null ? trim($row['attachment']) : '',
    ];
}
echo "New transaction rows found: " . count($txRows) . PHP_EOL;
echo PHP_EOL;

// ============================================================
// BUILD PLAN: for each old receipt with images, figure out what's missing
// and how to fix it (fill empty slots first, then insert new rows)
// ============================================================

$plan = []; // receipt_id => ['updates' => [[tx_id, filename]], 'inserts' => [filename, ...]]
$totalUpdates = 0;
$totalInserts = 0;
$skippedReceiptIds = [];

foreach ($oldImagesByReceipt as $receiptId => $expectedFilenames) {
    if (!isset($existingNewReceiptIds[$receiptId])) {
        // Can't insert/update transactions for a receipt_id that doesn't
        // exist in new receipts table (FK constraint would fail)
        $skippedReceiptIds[] = $receiptId;
        continue;
    }

    $existingTxRows = $txByReceipt[$receiptId] ?? [];
    $existingAttachments = array_filter(array_column($existingTxRows, 'attachment'), fn($v) => $v !== '');

    // Which expected filenames are already present anywhere in this receipt's transactions?
    $missingFilenames = [];
    foreach ($expectedFilenames as $filename) {
        if (!in_array($filename, $existingAttachments, true)) {
            $missingFilenames[] = $filename;
        }
    }

    if (empty($missingFilenames)) {
        continue; // nothing to do for this receipt
    }

    // Find transaction rows with empty/null attachment to fill first
    $emptySlotTxIds = [];
    foreach ($existingTxRows as $tx) {
        if ($tx['attachment'] === '') {
            $emptySlotTxIds[] = $tx['tx_id'];
        }
    }

    $updates = [];
    $inserts = [];

    foreach ($missingFilenames as $filename) {
        if (!empty($emptySlotTxIds)) {
            $txId = array_shift($emptySlotTxIds);
            $updates[] = ['tx_id' => $txId, 'filename' => $filename];
        } else {
            $inserts[] = $filename;
        }
    }

    $plan[$receiptId] = [
        'updates' => $updates,
        'inserts' => $inserts,
    ];

    $totalUpdates += count($updates);
    $totalInserts += count($inserts);
}

// ============================================================
// REPORT PLAN
// ============================================================

echo "============================================================" . PHP_EOL;
echo "PLAN SUMMARY" . PHP_EOL;
echo "============================================================" . PHP_EOL;
echo "Receipts needing fixes: " . count($plan) . PHP_EOL;
echo "Existing transaction rows to UPDATE (fill empty attachment): {$totalUpdates}" . PHP_EOL;
echo "New transaction rows to INSERT: {$totalInserts}" . PHP_EOL;
echo "Receipts skipped (id not found in new receipts table): " . count($skippedReceiptIds) . PHP_EOL;
echo "============================================================" . PHP_EOL . PHP_EOL;

if (!empty($skippedReceiptIds)) {
    echo "Skipped receipt IDs (not present in new receipts table):" . PHP_EOL;
    echo "  " . implode(', ', $skippedReceiptIds) . PHP_EOL . PHP_EOL;
}

foreach ($plan as $receiptId => $actions) {
    echo "Receipt ID: {$receiptId}" . PHP_EOL;
    foreach ($actions['updates'] as $u) {
        echo "  UPDATE transactions SET attachment = '{$u['filename']}' WHERE id = {$u['tx_id']}" . PHP_EOL;
    }
    foreach ($actions['inserts'] as $filename) {
        echo "  INSERT INTO transactions (receipt_id, attachment, type, created_at) VALUES ({$receiptId}, '{$filename}', 'payment', CURDATE())" . PHP_EOL;
    }
    echo PHP_EOL;
}

// ============================================================
// APPLY (only if --commit passed)
// ============================================================

if (!$COMMIT) {
    echo "Dry run complete. No changes were written." . PHP_EOL;
    echo "Re-run with --commit to apply these changes." . PHP_EOL;
    exit(0);
}

echo "Applying changes..." . PHP_EOL;

$newDb->beginTransaction();

try {
    $updateStmt = $newDb->prepare(
        "UPDATE `{$NEW_TRANSACTIONS_TABLE}` SET `{$NEW_TX_ATTACHMENT_COL}` = :filename WHERE `{$NEW_TX_ID_COL}` = :tx_id"
    );

    $insertStmt = $newDb->prepare(
        "INSERT INTO `{$NEW_TRANSACTIONS_TABLE}` (`{$NEW_TX_RECEIPT_ID_COL}`, `{$NEW_TX_ATTACHMENT_COL}`, `type`, `created_at`)
         VALUES (:receipt_id, :filename, 'payment', CURDATE())"
    );

    $appliedUpdates = 0;
    $appliedInserts = 0;

    foreach ($plan as $receiptId => $actions) {
        foreach ($actions['updates'] as $u) {
            $updateStmt->execute([
                ':filename' => $u['filename'],
                ':tx_id' => $u['tx_id'],
            ]);
            $appliedUpdates++;
        }
        foreach ($actions['inserts'] as $filename) {
            $insertStmt->execute([
                ':receipt_id' => $receiptId,
                ':filename' => $filename,
            ]);
            $appliedInserts++;
        }
    }

    $newDb->commit();

    echo "Done. Applied {$appliedUpdates} updates and {$appliedInserts} inserts." . PHP_EOL;
} catch (Exception $e) {
    $newDb->rollBack();
    echo "ERROR - rolled back all changes. Nothing was written." . PHP_EOL;
    echo "Reason: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// ============================================================
// CSV LOG OF WHAT WAS PLANNED/APPLIED
// ============================================================

$csvPath = __DIR__ . '/fix_missing_images_log.csv';
$csv = fopen($csvPath, 'w');
fputcsv($csv, ['receipt_id', 'action', 'tx_id_or_new', 'filename']);
foreach ($plan as $receiptId => $actions) {
    foreach ($actions['updates'] as $u) {
        fputcsv($csv, [$receiptId, 'UPDATE', $u['tx_id'], $u['filename']]);
    }
    foreach ($actions['inserts'] as $filename) {
        fputcsv($csv, [$receiptId, 'INSERT', 'new_row', $filename]);
    }
}
fclose($csv);

echo "Log written to: {$csvPath}" . PHP_EOL;