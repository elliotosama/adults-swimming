<?php
/**
 * migrate_edit_history_to_audit_log.php
 *
 * Purpose:
 *  1) Delete all rows in the NEW database's `receipt_audit_log` table
 *     with changed_at < CUTOFF_DATE.
 *  2) Read the OLD database's `receipts` table, decode each row's
 *     `edit_history` JSON column, and insert one `receipt_audit_log`
 *     row per history entry.
 *
 * Safety:
 *  - Defaults to DRY RUN. Nothing is deleted or inserted unless you
 *    pass --execute on the command line.
 *  - Wraps each destructive phase in a transaction.
 *  - Skips inserting an entry if a matching (receipt_id, changed_at, new_value)
 *    row already exists in receipt_audit_log, so the script is safe
 *    to re-run.
 *
 * Usage:
 *   php migrate_edit_history_to_audit_log.php              (dry run)
 *   php migrate_edit_history_to_audit_log.php --execute     (runs for real)
 *
 * Fill in the CONFIG section below before running.
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

// ============================================================
// CONFIG - edit these before running
// ============================================================

// Old database (source) - contains the `receipts` table with edit_history JSON
$OLD_DB = [
    'host' => '127.0.0.1',
    'name' => 'swimmingacademy',
    'user' => 'osama',
    'pass' => 'osamaisthebest',
    'charset' => 'utf8mb4',
];

// New database (target) - contains the `receipt_audit_log` table
$NEW_DB = [
    'host' => '127.0.0.1',
    'name' => 'swimming_academy',
    'user' => 'osama',
    'pass' => 'osamaisthebest',
    'charset' => 'utf8mb4',
];

// Cutoff: delete existing receipt_audit_log rows older than this date
const CUTOFF_DATE = '2026-07-10 00:00:00';

// Optional: table/column in the NEW db that lets us resolve a user's
// role from their id. Set to null to skip lookups (role will be 'unknown').
const USERS_TABLE = 'users';       // set to null to disable lookup
const USERS_ID_COL = 'id';
const USERS_ROLE_COL = 'role';

// If an old edit entry has no numeric editorId, try resolving by name here.
// Set to null to skip name-based lookups.
const USERS_NAME_COL = 'username'; // set to null to disable name lookup

// changed_by is NOT NULL in receipt_audit_log, so when an old editorId can't
// be resolved to a real row in `users` (different id space, deleted user,
// missing name match, etc.) we must fall back to SOME valid user id rather
// than NULL. Create a placeholder account in `users` for this (e.g. named
// "Migrated Data" / "Unknown") and put its id here before running.
const FALLBACK_USER_ID = 1; // <-- set this to a real, existing user id

// ============================================================
// SCRIPT - shouldn't need to edit below this line
// ============================================================

$isExecute = in_array('--execute', $argv, true);

function connect(array $cfg): PDO
{
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['name'], $cfg['charset']);
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

echo "==================================================\n";
echo $isExecute ? "MODE: EXECUTE (changes WILL be written)\n" : "MODE: DRY RUN (no changes will be written)\n";
echo "==================================================\n\n";

try {
    $oldPdo = connect($OLD_DB);
    $newPdo = connect($NEW_DB);
} catch (PDOException $e) {
    fwrite(STDERR, "Connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

if (USERS_TABLE !== null) {
    $checkFallback = $newPdo->prepare(
        sprintf('SELECT 1 FROM %s WHERE %s = :id LIMIT 1', USERS_TABLE, USERS_ID_COL)
    );
    $checkFallback->execute(['id' => FALLBACK_USER_ID]);
    if (!$checkFallback->fetch()) {
        echo "WARNING: FALLBACK_USER_ID (" . FALLBACK_USER_ID . ") does not exist in `" . USERS_TABLE . "`.\n";
        echo "This is only used for entries with NO editor info at all, and FK checks are\n";
        echo "disabled during the insert, so this will not block the migration - but any\n";
        echo "row using it will have a changed_by value that doesn't join to a real user.\n\n";
    }
}

// ------------------------------------------------------------
// STEP 1: purge old receipt_audit_log rows before CUTOFF_DATE
// ------------------------------------------------------------

echo "--- Step 1: Purge receipt_audit_log rows before " . CUTOFF_DATE . " ---\n";

$countStmt = $newPdo->prepare("SELECT COUNT(*) AS c FROM receipt_audit_log WHERE changed_at < :cutoff");
$countStmt->execute(['cutoff' => CUTOFF_DATE]);
$toDelete = (int) $countStmt->fetch()['c'];

echo "Rows that will be deleted: {$toDelete}\n";

if ($isExecute) {
    $newPdo->beginTransaction();
    try {
        $del = $newPdo->prepare("DELETE FROM receipt_audit_log WHERE changed_at < :cutoff");
        $del->execute(['cutoff' => CUTOFF_DATE]);
        $deleted = $del->rowCount();
        $newPdo->commit();
        echo "Deleted {$deleted} rows.\n\n";
    } catch (Throwable $e) {
        $newPdo->rollBack();
        fwrite(STDERR, "Delete failed, rolled back: " . $e->getMessage() . "\n");
        exit(1);
    }
} else {
    echo "(dry run - nothing deleted)\n\n";
}

// ------------------------------------------------------------
// STEP 2: migrate edit_history JSON -> receipt_audit_log
// ------------------------------------------------------------

echo "--- Step 2: Migrate edit_history from old receipts table ---\n";

// Cache of user_id => [validId, role], and username => user_id, to avoid repeated queries
$userCache = [];
$nameToIdCache = [];

/**
 * Old data has timestamps in more than one format
 * (e.g. "2024-08-29 19:27:37" and "01/09/2024 23:51:35").
 * Normalize anything we find to MySQL's "Y-m-d H:i:s".
 */
function normalizeDatetime(?string $raw): ?string
{
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    $raw = trim($raw);

    $formats = [
        'Y-m-d H:i:s',   // already MySQL format
        'd/m/Y H:i:s',   // e.g. 01/09/2024 23:51:35
        'Y-m-d\TH:i:s',  // ISO-ish, just in case
        'd-m-Y H:i:s',
    ];

    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw);
        if ($dt !== false) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    // Last resort: let PHP guess
    $ts = strtotime($raw);
    if ($ts !== false) {
        return date('Y-m-d H:i:s', $ts);
    }

    return null;
}

/**
 * Looks up a user by id and returns [validId, role].
 * If the id doesn't exist in the users table (old system ids that no
 * longer/never existed in the new one), returns [null, 'unknown'] so
 * we never try to insert a changed_by value that violates the FK.
 */
function resolveUser(PDO $newPdo, ?int $userId, array &$userCache): array
{
    if ($userId === null) {
        return [FALLBACK_USER_ID, 'unknown'];
    }
    if (USERS_TABLE === null) {
        // No way to look up role, but FK checks are disabled so keep the original id.
        return [$userId, 'unknown'];
    }
    if (array_key_exists($userId, $userCache)) {
        return $userCache[$userId];
    }
    $sql = sprintf(
        'SELECT %s AS id, %s AS role FROM %s WHERE %s = :id LIMIT 1',
        USERS_ID_COL,
        USERS_ROLE_COL,
        USERS_TABLE,
        USERS_ID_COL
    );
    $stmt = $newPdo->prepare($sql);
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();

    // FK checks are disabled for this migration, so if the id isn't found
    // in `users` we still keep the ORIGINAL old id (for historical accuracy)
    // rather than forcing it to the fallback user - we just can't resolve its role.
    $result = $row
        ? [(int) $row['id'], $row['role'] ?? 'unknown']
        : [$userId, 'unknown'];

    $userCache[$userId] = $result;
    return $result;
}

function resolveIdByName(PDO $newPdo, ?string $name, array &$nameToIdCache): ?int
{
    if (USERS_TABLE === null || USERS_NAME_COL === null || $name === null || $name === '') {
        return null;
    }
    if (array_key_exists($name, $nameToIdCache)) {
        return $nameToIdCache[$name];
    }
    $sql = sprintf(
        'SELECT %s AS id FROM %s WHERE %s = :name LIMIT 1',
        USERS_ID_COL,
        USERS_TABLE,
        USERS_NAME_COL
    );
    $stmt = $newPdo->prepare($sql);
    $stmt->execute(['name' => $name]);
    $row = $stmt->fetch();
    $id = $row ? (int) $row['id'] : null;
    $nameToIdCache[$name] = $id;
    return $id;
}

// Fetch all receipts that actually have history to migrate
$rows = $oldPdo->query(
    "SELECT id, edit_history FROM receipts
     WHERE edit_history IS NOT NULL AND edit_history != '[]'"
)->fetchAll();

echo "Receipts with edit_history to process: " . count($rows) . "\n";

$existsStmt = $newPdo->prepare(
    "SELECT 1 FROM receipt_audit_log
     WHERE receipt_id = :receipt_id AND changed_at = :changed_at AND field_name = 'receipt_edited'
     LIMIT 1"
);

$insertStmt = $newPdo->prepare(
    "INSERT INTO receipt_audit_log
        (receipt_id, changed_by, changed_at, role, field_name, old_value, new_value)
     VALUES
        (:receipt_id, :changed_by, :changed_at, :role, :field_name, :old_value, :new_value)"
);

$totalEntries = 0;
$totalInserted = 0;
$totalSkippedExisting = 0;
$totalSkippedBadJson = 0;
$totalUnresolvedUser = 0;
$totalSkippedFkViolation = 0;
$samplePrinted = 0;

// Note: we no longer wrap all inserts in one big transaction. If a single
// row fails (e.g. a genuine FK violation), we skip just that row and keep
// going, rather than rolling back everything already inserted.
if ($isExecute) {
    $newPdo->exec('SET FOREIGN_KEY_CHECKS=0');
}

foreach ($rows as $row) {
    $receiptId = (int) $row['id'];
    $history = json_decode($row['edit_history'], true);

    if (!is_array($history)) {
        $totalSkippedBadJson++;
        continue;
    }

    foreach ($history as $entry) {
        $totalEntries++;

        $changes = $entry['changes'] ?? [];
        $editorName = $entry['editor'] ?? ($changes['editorName'] ?? null);
        $editorId = isset($changes['editorId']) ? (int) $changes['editorId'] : null;

        if ($editorId === null) {
            $editorId = resolveIdByName($newPdo, $editorName, $nameToIdCache);
        }

        $changedAtRaw = $entry['timestamp'] ?? ($changes['edit_time'] ?? null);
        $changedAt = normalizeDatetime($changedAtRaw);
        if ($changedAt === null) {
            // No usable/parseable timestamp - can't insert reliably, skip.
            $totalSkippedBadJson++;
            continue;
        }

        $editorIdBeforeLookup = $editorId;
        [$editorId, $role] = resolveUser($newPdo, $editorId, $userCache);
        if ($editorIdBeforeLookup === null) {
            $totalUnresolvedUser++; // no editor info at all, fell back to FALLBACK_USER_ID
        } elseif ($role === 'unknown') {
            $totalUnresolvedUser++; // had an old id, but it doesn't exist in `users` - kept as-is
        }

        // Store the raw entry as-is (untouched) in new_value. old_value is left blank.
        $newValue = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($newValue === false) {
            $totalSkippedBadJson++;
            continue;
        }

        // Dedupe check (receipt_id + changed_at + field_name - not new_value,
        // since new_value's format has changed across script versions and we
        // don't want that to cause the same event to be inserted twice)
        $existsStmt->execute([
            'receipt_id' => $receiptId,
            'changed_at' => $changedAt,
        ]);
        if ($existsStmt->fetch()) {
            $totalSkippedExisting++;
            continue;
        }

        if ($samplePrinted < 5) {
            echo "Sample -> receipt_id={$receiptId}, changed_by=" . ($editorId ?? 'NULL')
                . ", changed_at={$changedAt}, role={$role}, new_value="
                . mb_substr($newValue, 0, 80) . "...\n";
            $samplePrinted++;
        }

        if ($isExecute) {
            try {
                $insertStmt->execute([
                    'receipt_id' => $receiptId,
                    'changed_by' => $editorId,
                    'changed_at' => $changedAt,
                    'role' => $role,
                    'field_name' => 'receipt_edited',
                    'old_value' => null,
                    'new_value' => $newValue,
                ]);
            } catch (PDOException $e) {
                // SQLSTATE 23000 = integrity constraint violation (FK, NOT NULL, etc.)
                if ($e->getCode() === '23000') {
                    $totalSkippedFkViolation++;
                    continue;
                }
                // Anything else is unexpected - surface it rather than hiding it.
                throw $e;
            }
        }
        $totalInserted++;
    }
}

if ($isExecute) {
    $newPdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

echo "\n--- Summary ---\n";
echo "Total history entries seen: {$totalEntries}\n";
echo ($isExecute ? "Inserted" : "Would insert") . ": {$totalInserted}\n";
echo "Skipped (already existed): {$totalSkippedExisting}\n";
echo "Skipped (bad/missing data): {$totalSkippedBadJson}\n";
echo "Rows with unresolved/unmatched editor (role='unknown'): {$totalUnresolvedUser}\n";
echo "Skipped (FK/constraint violation on insert): {$totalSkippedFkViolation}\n";

if (!$isExecute) {
    echo "\nThis was a DRY RUN. Re-run with --execute to apply changes.\n";
}