<?php
/**
 * Migration script: old captains -> new captains + captain_branch
 *
 * ASSUMPTIONS (based on what you confirmed):
 *  - OLD captains.id is a varchar(10) but holds a NUMERIC string (e.g. "1", "23")
 *    -> it is cast to int and reused as the new captains.id (preserved for any
 *       other tables that reference it). If a non-numeric id is found, that
 *       row is skipped and reported as a warning instead of failing the whole run.
 *  - OLD captains.branches_access = comma-separated branch NAMES, matched
 *    against the NEW branches table's branch_name column (must already be
 *    migrated - run the branches/users migration first).
 *  - OLD captains.created_at (timestamp) -> NEW captains.created_at (date),
 *    just the date portion is kept.
 *  - NEW captains.created_by has no equivalent in the old table -> set to NULL.
 *  - NEW captains.visible defaults to 1 for all migrated rows.
 *
 * CONFIGURE BELOW:
 *  - DB connection details
 *  - Name of your OLD captains table (new tables are assumed to already exist
 *    as: captains, captain_branch, branches)
 */

// ----------------------------------------------------------------------
// CONFIG
// ----------------------------------------------------------------------
$DB_HOST = '127.0.0.1';
$DB_NAME = 'your_database';
$DB_USER = 'your_user';
$DB_PASS = 'your_password';

// Name of the OLD captains table (rename if different)
$OLD_CAPTAINS_TABLE = 'old_captains';

// Names of the NEW tables (per the schema you posted)
$NEW_CAPTAINS_TABLE       = 'captains';
$NEW_CAPTAIN_BRANCH_TABLE = 'captain_branch';
$NEW_BRANCHES_TABLE       = 'branches';

// ----------------------------------------------------------------------
// CONNECT
// ----------------------------------------------------------------------
try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

$warnings = [];
$pdo->beginTransaction();

try {
    // ------------------------------------------------------------------
    // STEP 0: Build branch name -> id map from the (already migrated) branches table
    // ------------------------------------------------------------------
    $branchNameToId = [];
    $branchRows = $pdo->query("SELECT id, branch_name FROM `{$NEW_BRANCHES_TABLE}`")->fetchAll();
    foreach ($branchRows as $row) {
        $key = mb_strtolower(trim($row['branch_name']));
        $branchNameToId[$key] = $row['id'];
    }

    if (empty($branchNameToId)) {
        $warnings[] = "No rows found in `{$NEW_BRANCHES_TABLE}` - did you run the branches migration first?";
    }

    // ------------------------------------------------------------------
    // STEP 1: Migrate captains (old -> new), preserving numeric ids
    // ------------------------------------------------------------------
    $oldCaptains = $pdo->query("
        SELECT id, name, phone, created_at, branches_access
        FROM `{$OLD_CAPTAINS_TABLE}`
    ")->fetchAll();

    $insertCaptain = $pdo->prepare("
        INSERT INTO `{$NEW_CAPTAINS_TABLE}`
            (id, captain_name, phone_number, created_at, created_by, visible)
        VALUES
            (:id, :captain_name, :phone_number, :created_at, NULL, 1)
    ");

    $insertCaptainBranch = $pdo->prepare("
        INSERT IGNORE INTO `{$NEW_CAPTAIN_BRANCH_TABLE}` (branch_id, captain_id)
        VALUES (:branch_id, :captain_id)
    ");

    $migratedCaptains = 0;
    $linkedPairs      = 0;

    foreach ($oldCaptains as $captain) {
        $oldId = trim((string) $captain['id']);

        if (!ctype_digit($oldId)) {
            $warnings[] = "Captain id '{$captain['id']}' ({$captain['name']}) is not numeric - row skipped.";
            continue;
        }

        $newId = (int) $oldId;

        // created_at is a timestamp in the old table; keep just the date part
        $createdAtDate = $captain['created_at'] ? date('Y-m-d', strtotime($captain['created_at'])) : null;

        $insertCaptain->execute([
            ':id'           => $newId,
            ':captain_name' => $captain['name'],
            ':phone_number' => $captain['phone'],
            ':created_at'   => $createdAtDate,
        ]);
        $migratedCaptains++;

        // ----------------------------------------------------------------
        // STEP 2: Build captain_branch links from branches_access
        // ----------------------------------------------------------------
        if (empty($captain['branches_access'])) {
            continue;
        }

        $seen = [];
        foreach (explode(',', $captain['branches_access']) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if (!isset($branchNameToId[$key])) {
                $warnings[] = "Captain #{$newId} ({$captain['name']}): branch '{$name}' not found in branches table - skipped.";
                continue;
            }

            $insertCaptainBranch->execute([
                ':branch_id'  => $branchNameToId[$key],
                ':captain_id' => $newId,
            ]);
            $linkedPairs++;
        }
    }

    echo "Migrated {$migratedCaptains} captain(s).\n";
    echo "Created {$linkedPairs} captain_branch link(s).\n";

    $pdo->commit();
    echo "Migration completed successfully.\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    die("Migration FAILED, all changes rolled back: " . $e->getMessage() . "\n");
}

// ------------------------------------------------------------------
// Report any warnings
// ------------------------------------------------------------------
if (!empty($warnings)) {
    echo "\n--- Warnings ---\n";
    foreach ($warnings as $w) {
        echo $w . "\n";
    }
}
