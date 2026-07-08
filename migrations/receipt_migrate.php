<?php
$DB_HOST = '127.0.0.1';
$DB_NAME = 'your_db_name';
$DB_USER = 'your_db_user';
$DB_PASS = 'your_db_pass';

$OLD_TABLE = 'receipts_old'; // rename to match your actual old table name
$NEW_TABLE = 'receipts';     // rename to match your actual new table name

$LOG_FILE = __DIR__ . '/migration_errors.log';
// ============================================================

$dryRun = in_array('--dry-run', $argv);

function connectDb($host, $name, $user, $pass): PDO
{
    return new PDO(
        "mysql:host=$host;dbname=$name;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function logError(string $file, string $message): void
{
    file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

/** Build a lowercase-name => id lookup map from a table. */
function buildNameLookup(PDO $pdo, string $table): array
{
    $map = [];
    $stmt = $pdo->query("SELECT id, name FROM `$table`");
    foreach ($stmt as $row) {
        if ($row['name'] === null || $row['name'] === '') {
            continue;
        }
        $key = mb_strtolower(trim($row['name']));
        $map[$key] = (int)$row['id'];
    }
    return $map;
}

try {
    $pdo = connectDb($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Building lookup tables (captains, branches)...\n";
$captainMap = buildNameLookup($pdo, 'captains');
$branchMap  = buildNameLookup($pdo, 'branches');
echo "Loaded " . count($captainMap) . " captains, " . count($branchMap) . " branches.\n";

echo "Fetching old receipts ordered by created_at...\n";
$stmt = $pdo->query("SELECT * FROM `$OLD_TABLE` ORDER BY created_at ASC, id ASC");
$oldRows = $stmt->fetchAll();
echo "Fetched " . count($oldRows) . " old rows.\n";

$insertSql = "INSERT INTO `$NEW_TABLE`
    (receipt_ref, client_id, creator_id, captain_id, branch_id,
     first_session, last_session, renewal_session, created_at,
     renewal_type, receipt_status, exercise_time, plan_id, level,
     pdf_path, is_refunded)
    VALUES
    (:receipt_ref, :client_id, :creator_id, :captain_id, :branch_id,
     :first_session, :last_session, :renewal_session, :created_at,
     :renewal_type, :receipt_status, :exercise_time, :plan_id, :level,
     :pdf_path, :is_refunded)";

$insertStmt = $pdo->prepare($insertSql);

$monthCounters = []; // 'ym' => last used sequence number
$migrated = 0;
$skipped = 0;

if (!$dryRun) {
    $pdo->beginTransaction();
}

foreach ($oldRows as $row) {
    $oldId = $row['id'];

    // --- Required: client_id ---
    if (empty($row['client_id'])) {
        logError($LOG_FILE, "Skipped old id=$oldId: missing client_id");
        $skipped++;
        continue;
    }

    // --- Required: creator_id ---
    if (empty($row['creator_id'])) {
        logError($LOG_FILE, "Skipped old id=$oldId: missing creator_id");
        $skipped++;
        continue;
    }

    // --- Resolve captain_id from old.coach ---
    $captainId = null;
    if (!empty($row['coach'])) {
        $key = mb_strtolower(trim($row['coach']));
        $captainId = $captainMap[$key] ?? null;
    }
    if ($captainId === null) {
        logError($LOG_FILE, "Skipped old id=$oldId: no captain match for coach='" . ($row['coach'] ?? '') . "'");
        $skipped++;
        continue;
    }

    // --- Resolve branch_id from old.branch ---
    $branchId = null;
    if (!empty($row['branch'])) {
        $key = mb_strtolower(trim($row['branch']));
        $branchId = $branchMap[$key] ?? null;
    }
    if ($branchId === null) {
        logError($LOG_FILE, "Skipped old id=$oldId: no branch match for branch='" . ($row['branch'] ?? '') . "'");
        $skipped++;
        continue;
    }

    // --- created_at: old timestamp -> new date ---
    $createdAt = null;
    if (!empty($row['created_at'])) {
        $ts = strtotime($row['created_at']);
        $createdAt = $ts ? date('Y-m-d', $ts) : null;
    }
    if ($createdAt === null) {
        logError($LOG_FILE, "Skipped old id=$oldId: invalid/missing created_at");
        $skipped++;
        continue;
    }

    // --- receipt_ref: YYMM + 4-digit sequence, per month, chronological ---
    $yymm = date('ym', strtotime($createdAt));
    if (!isset($monthCounters[$yymm])) {
        $monthCounters[$yymm] = 0;
    }
    $monthCounters[$yymm]++;
    $receiptRef = $yymm . str_pad((string)$monthCounters[$yymm], 4, '0', STR_PAD_LEFT);

    $receiptStatus = !empty($row['status']) ? $row['status'] : 'not_completed';

    $params = [
        ':receipt_ref'     => $receiptRef,
        ':client_id'       => (int)$row['client_id'],
        ':creator_id'      => (int)$row['creator_id'],
        ':captain_id'      => $captainId,
        ':branch_id'       => $branchId,
        ':first_session'   => $row['first_session'] ?: null,
        ':last_session'    => $row['last_session'] ?: null,
        ':renewal_session' => $row['renewal_date'] ?: null,
        ':created_at'      => $createdAt,
        ':renewal_type'    => $row['renew_type'] ?: null,
        ':receipt_status'  => $receiptStatus,
        ':exercise_time'   => $row['exerciseTime'] ?: null,
        ':plan_id'         => null,
        ':level'           => $row['clientLevel'] !== null ? (int)$row['clientLevel'] : null,
        ':pdf_path'        => $row['attachment'] ?: null,
        ':is_refunded'     => 0,
    ];

    if ($dryRun) {
        echo "[DRY RUN] old id=$oldId -> receipt_ref=$receiptRef, client_id={$params[':client_id']}, "
            . "captain_id=$captainId, branch_id=$branchId\n";
    } else {
        try {
            $insertStmt->execute($params);
        } catch (PDOException $e) {
            logError($LOG_FILE, "Insert failed for old id=$oldId: " . $e->getMessage());
            $skipped++;
            continue;
        }
    }

    $migrated++;
}

if (!$dryRun) {
    $pdo->commit();
}

echo "\nDone.\n";
echo "Migrated: $migrated\n";
echo "Skipped:  $skipped\n";
if ($skipped > 0) {
    echo "See $LOG_FILE for details.\n";
}