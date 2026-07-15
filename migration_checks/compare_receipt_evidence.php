<?php
/**
 * compare_missing_images.php
 *
 * Compares images stored in OLD database (receipts.attachment, JSON array of paths)
 * against NEW database (transactions.payment_evidence, single filename string,
 * linked via transactions.receipt_id -> receipts.id).
 *
 * Matching key: old_receipts.id === new_receipts.id === new_transactions.receipt_id
 *
 * This script is READ-ONLY. It does not modify any data.
 *
 * ------------------------------------------------------------------
 * HOW MATCHING WORKS
 * ------------------------------------------------------------------
 * For each old receipt row:
 *   - Decode `attachment` as a JSON array of paths (e.g. ["uploads\\a.jpg","uploads/b.jpg"])
 *     - If JSON decode fails but the value is a non-empty string, treat it as a
 *       single-file array (fallback for legacy rows that aren't JSON-encoded).
 *   - Extract just the basename of each path (strip "uploads/" or "uploads\" prefix,
 *     normalize slashes) since the new system stores bare filenames only.
 *   - Look up all transactions in the NEW db where receipt_id = old receipt id.
 *   - Collect the set of payment_evidence filenames for that receipt_id.
 *   - Compare: any basename from the old array NOT found in the new evidence set
 *     is reported as "missing".
 *
 * A receipt is also flagged if the old receipt had images but there are
 * NO transactions at all for that receipt_id in the new db (nothing to compare against).
 * ------------------------------------------------------------------
 */

// ============================================================
// CONFIG - fill these in
// ============================================================

// OLD database connection
$OLD_DB_HOST = '127.0.0.1';
$OLD_DB_NAME = 'swimmingacademy';
$OLD_DB_USER = 'root';
$OLD_DB_PASS = '';

// NEW database connection
$NEW_DB_HOST = '127.0.0.1';
$NEW_DB_NAME = 'swimming_academy';
$NEW_DB_USER = 'root';
$NEW_DB_PASS = '';

// Table/column names (change here if your naming differs)
$OLD_TABLE = 'receipts';
$OLD_ID_COL = 'id';
$OLD_ATTACHMENT_COL = 'attachment';

$NEW_TRANSACTIONS_TABLE = 'transactions';
$NEW_RECEIPT_ID_COL = 'receipt_id';
$NEW_PAYMENT_EVIDENCE_COL = 'attachment';

// ============================================================
// CONNECT
// ============================================================

function connect(string $host, string $dbname, string $user, string $pass): PDO
{
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
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

/**
 * Normalize a stored old-system path down to just the filename.
 * Handles "uploads\\file.jpg", "uploads/file.jpg", or a bare filename.
 */
function extractFilename(string $path): string
{
    $normalized = str_replace('\\', '/', $path);
    $parts = explode('/', $normalized);
    return trim(end($parts));
}

/**
 * Decode the old `attachment` column into an array of filenames.
 * Tries JSON first; falls back to treating the raw value as a single filename.
 */
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

    // Not valid JSON - treat the whole string as a single path/filename
    return [extractFilename($raw)];
}

// ============================================================
// FETCH OLD RECEIPTS WITH ATTACHMENTS
// ============================================================

echo "Fetching old receipts..." . PHP_EOL;

$oldStmt = $oldDb->query("SELECT `{$OLD_ID_COL}` AS id, `{$OLD_ATTACHMENT_COL}` AS attachment FROM `{$OLD_TABLE}`");
$oldReceipts = $oldStmt->fetchAll();

echo "Found " . count($oldReceipts) . " old receipt rows." . PHP_EOL;

// Build map: receipt_id => array of expected filenames
$oldImagesByReceipt = [];
foreach ($oldReceipts as $row) {
    $filenames = decodeOldAttachment($row['attachment']);
    if (!empty($filenames)) {
        $oldImagesByReceipt[(int)$row['id']] = $filenames;
    }
}

echo "Old receipts that have at least one image: " . count($oldImagesByReceipt) . PHP_EOL;

// ============================================================
// FETCH NEW TRANSACTIONS' PAYMENT EVIDENCE
// ============================================================

echo "Fetching new transactions..." . PHP_EOL;

$newStmt = $newDb->query(
    "SELECT `{$NEW_RECEIPT_ID_COL}` AS receipt_id, `{$NEW_PAYMENT_EVIDENCE_COL}` AS payment_evidence
     FROM `{$NEW_TRANSACTIONS_TABLE}`"
);
$newTransactions = $newStmt->fetchAll();

echo "Found " . count($newTransactions) . " new transaction rows." . PHP_EOL;

// Build map: receipt_id => Set of payment_evidence filenames (non-empty only)
$newEvidenceByReceipt = [];
$receiptHasAnyTransaction = [];
foreach ($newTransactions as $row) {
    $receiptId = (int)$row['receipt_id'];
    $receiptHasAnyTransaction[$receiptId] = true;

    $evidence = trim((string)$row['payment_evidence']);
    if ($evidence !== '') {
        $newEvidenceByReceipt[$receiptId][] = $evidence;
    }
}

// ============================================================
// COMPARE
// ============================================================

$missingReport = []; // receipt_id => ['missing_files' => [...], 'reason' => '...']
$totalMissingFiles = 0;

foreach ($oldImagesByReceipt as $receiptId => $expectedFilenames) {
    if (!isset($receiptHasAnyTransaction[$receiptId])) {
        // No transactions at all exist for this receipt in the new system
        $missingReport[$receiptId] = [
            'missing_files' => $expectedFilenames,
            'reason' => 'No transactions found in new db for this receipt_id',
        ];
        $totalMissingFiles += count($expectedFilenames);
        continue;
    }

    $availableEvidence = $newEvidenceByReceipt[$receiptId] ?? [];
    $missingFiles = [];

    foreach ($expectedFilenames as $filename) {
        if (!in_array($filename, $availableEvidence, true)) {
            $missingFiles[] = $filename;
        }
    }

    if (!empty($missingFiles)) {
        $missingReport[$receiptId] = [
            'missing_files' => $missingFiles,
            'reason' => 'Image(s) not found among payment_evidence for this receipt_id',
        ];
        $totalMissingFiles += count($missingFiles);
    }
}

// ============================================================
// OUTPUT REPORT
// ============================================================

echo PHP_EOL . "============================================================" . PHP_EOL;
echo "REPORT" . PHP_EOL;
echo "============================================================" . PHP_EOL;
echo "Total old receipts with images checked: " . count($oldImagesByReceipt) . PHP_EOL;
echo "Receipts with at least one missing image: " . count($missingReport) . PHP_EOL;
echo "Total individual missing image files: " . $totalMissingFiles . PHP_EOL;
echo "============================================================" . PHP_EOL . PHP_EOL;

if (empty($missingReport)) {
    echo "No missing images found. Everything matches." . PHP_EOL;
} else {
    foreach ($missingReport as $receiptId => $info) {
        echo "Receipt ID: {$receiptId}" . PHP_EOL;
        echo "  Reason: {$info['reason']}" . PHP_EOL;
        echo "  Missing files:" . PHP_EOL;
        foreach ($info['missing_files'] as $file) {
            echo "    - {$file}" . PHP_EOL;
        }
        echo PHP_EOL;
    }
}

// Also write a CSV for easy filtering in Excel
$csvPath = __DIR__ . '/missing_images_report.csv';
$csv = fopen($csvPath, 'w');
fputcsv($csv, ['receipt_id', 'missing_filename', 'reason']);
foreach ($missingReport as $receiptId => $info) {
    foreach ($info['missing_files'] as $file) {
        fputcsv($csv, [$receiptId, $file, $info['reason']]);
    }
}
fclose($csv);

echo PHP_EOL . "CSV report written to: {$csvPath}" . PHP_EOL;