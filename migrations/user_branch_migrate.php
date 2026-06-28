<?php
/**
 * Migration script: old users/branches -> new users/branches/user_branch
 *
 * ASSUMPTIONS (based on what you confirmed):
 *  - OLD users.branch          = a single branch NAME (or NULL/empty)
 *  - OLD users.branches_access = comma-separated branch NAMES (or NULL/empty)
 *  - OLD users.password is ALREADY a valid hash -> copied as-is into new password_hash
 *  - OLD ids are preserved as new ids (so we keep the same primary keys for
 *    users and branches). If the new tables already contain rows with the
 *    same ids, this will fail loudly rather than silently overwrite anything.
 *
 * CONFIGURE BELOW:
 *  - DB connection details
 *  - Names of your OLD tables (the new tables are assumed to already exist
 *    with the schema you described: users, branches, user_branch)
 */

// ----------------------------------------------------------------------
// CONFIG
// ----------------------------------------------------------------------
$DB_HOST = '127.0.0.1';
$DB_NAME = 'your_database';
$DB_USER = 'your_user';
$DB_PASS = 'your_password';

// Names of the OLD tables (rename these if your old tables have different names)
$OLD_USERS_TABLE    = 'old_users';
$OLD_BRANCHES_TABLE = 'old_branches';

// Names of the NEW tables (per the schema you posted)
$NEW_USERS_TABLE       = 'users';
$NEW_BRANCHES_TABLE    = 'branches';
$NEW_USER_BRANCH_TABLE = 'user_branch';

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
    // STEP 1: Migrate branches (old -> new)
    // ------------------------------------------------------------------
    $oldBranches = $pdo->query("SELECT id, name FROM `{$OLD_BRANCHES_TABLE}`")->fetchAll();

    $insertBranch = $pdo->prepare("
        INSERT INTO `{$NEW_BRANCHES_TABLE}`
            (id, branch_name, created_at, visible, working_days1, working_days2, working_days3, country_id, working_time_from, working_time_to)
        VALUES
            (:id, :branch_name, CURDATE(), 1, NULL, NULL, NULL, NULL, NULL, NULL)
    ");

    // name (lowercased/trimmed) -> new branch id, used for user_branch lookups
    $branchNameToId = [];

    foreach ($oldBranches as $branch) {
        $insertBranch->execute([
            ':id'          => $branch['id'],
            ':branch_name' => $branch['name'],
        ]);

        $key = mb_strtolower(trim($branch['name']));
        $branchNameToId[$key] = $branch['id'];
    }

    echo "Migrated " . count($oldBranches) . " branch(es).\n";

    // ------------------------------------------------------------------
    // STEP 2: Migrate users (old -> new)
    // ------------------------------------------------------------------
    $oldUsers = $pdo->query("
        SELECT id, username, email, password, branch, branches_access, role, phone
        FROM `{$OLD_USERS_TABLE}`
    ")->fetchAll();

    $insertUser = $pdo->prepare("
        INSERT INTO `{$NEW_USERS_TABLE}`
            (id, username, email, password_hash, role, phone, visible, created_at, last_login, is_active)
        VALUES
            (:id, :username, :email, :password_hash, :role, :phone, 1, CURDATE(), NULL, 1)
    ");

    $insertUserBranch = $pdo->prepare("
        INSERT IGNORE INTO `{$NEW_USER_BRANCH_TABLE}` (user_id, branch_id)
        VALUES (:user_id, :branch_id)
    ");

    $migratedUsers = 0;
    $linkedPairs   = 0;

    foreach ($oldUsers as $user) {
        $insertUser->execute([
            ':id'            => $user['id'],
            ':username'      => $user['username'],
            ':email'         => $user['email'] ?: null,
            ':password_hash' => $user['password'],
            ':role'          => $user['role'] ?: null,
            ':phone'         => $user['phone'] ?: null,
        ]);
        $migratedUsers++;

        // ----------------------------------------------------------------
        // STEP 3: Build user_branch links from `branch` + `branches_access`
        // ----------------------------------------------------------------
        $branchNames = [];

        if (!empty($user['branch'])) {
            $branchNames[] = trim($user['branch']);
        }

        if (!empty($user['branches_access'])) {
            foreach (explode(',', $user['branches_access']) as $name) {
                $name = trim($name);
                if ($name !== '') {
                    $branchNames[] = $name;
                }
            }
        }

        // de-duplicate (case-insensitive)
        $seen = [];
        foreach ($branchNames as $name) {
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if (!isset($branchNameToId[$key])) {
                $warnings[] = "User #{$user['id']} ({$user['username']}): branch '{$name}' not found in branches table - skipped.";
                continue;
            }

            $insertUserBranch->execute([
                ':user_id'   => $user['id'],
                ':branch_id' => $branchNameToId[$key],
            ]);
            $linkedPairs++;
        }
    }

    echo "Migrated {$migratedUsers} user(s).\n";
    echo "Created {$linkedPairs} user_branch link(s).\n";

    $pdo->commit();
    echo "Migration completed successfully.\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    die("Migration FAILED, all changes rolled back: " . $e->getMessage() . "\n");
}

// ------------------------------------------------------------------
// Report any branch names that couldn't be matched
// ------------------------------------------------------------------
if (!empty($warnings)) {
    echo "\n--- Warnings ---\n";
    foreach ($warnings as $w) {
        echo $w . "\n";
    }
}
