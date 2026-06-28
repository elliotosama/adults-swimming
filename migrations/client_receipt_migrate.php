<?php
/**
 * Migration script: old clients / receipts / activities
 *                 -> new clients / receipts / transactions / receipt_audit_log
 *
 * This is the big one - the old "receipts" table is a denormalized blob that
 * mixes receipt data, a single payment/transaction, a client snapshot, and an
 * edit history. This script splits it back out into the normalized new schema.
 *
 * ============================================================================
 * KEY ASSUMPTIONS (please review carefully before running)
 * ============================================================================
 *
 * IDS
 *  - old clients.id  -> new clients.id  (preserved)
 *  - old receipts.id -> new receipts.id (preserved, so receiptId references
 *    in old `activities` map 1:1 to the new receipts.id)
 *
 * CLIENTS
 *  - name -> client_name, phone -> phone, age -> age
 *  - created_by (old: username string) -> created_by (new: int user id),
 *    resolved via the new `users` table by username
 *  - created_at (timestamp) -> created_at (date)
 *  - gender, email -> NULL (no old equivalent)
 *  - coach / branch / sessions / clientLevel from old clients table are
 *    DROPPED (these are now per-receipt fields in the new schema, sourced
 *    from the old `receipts` table instead)
 *
 * RECEIPTS
 *  - receipt_ref is GENERATED as YYMM + 4-digit sequence (e.g. "26060042"),
 *    sequence resets each month, ordered by created_at then id
 *  - client_id      -> client_id (old)
 *  - creator_id     -> creator_id (old), falling back to creator_username
 *                      lookup in `users` if creator_id is empty
 *  - captain_id     -> resolved from old `coach` (name) via the new `captains`
 *                      table (captain_name)
 *  - branch_id      -> resolved from old `branch` (name) via the new
 *                      `branches` table (branch_name)
 *  - first_session, last_session -> direct
 *  - renewal_date   -> renewal_session
 *  - created_at     -> created_at (date)
 *  - renew_type     -> renewal_type
 *  - status         -> receipt_status
 *  - exerciseTime   -> exercise_time
 *  - clientLevel    -> level
 *  - plan_id, pdf_path -> NULL (no old equivalent)
 *  - ANY of client_id / creator_id / captain_id / branch_id that can't be
 *    resolved get a PLACEHOLDER value of 0, and a warning is logged. You
 *    MUST review these warnings and fix the affected rows manually.
 *
 * TRANSACTIONS
 *  - One transaction row is created per old receipt IF it has a
 *    payment_method and/or a non-zero paid_amount.
 *  - payment_method, amount (<- paid_amount), attachment, notes -> direct
 *  - receipt_id -> the (preserved) new receipt id
 *  - created_by -> same resolved creator_id as the receipt
 *  - type -> old `type`, defaulting to 'payment' if empty
 *
 * SUBSCRIPTION_FEE / REMAINING_BALANCE
 *  - These columns have NO home in the new schema. Rather than silently
 *    losing this data, each non-null value is recorded as an informational
 *    row in receipt_audit_log (field_name = 'subscription_fee' /
 *    'remaining_balance', new_value = the old value, old_value = NULL).
 *    Set $PRESERVE_FEE_AND_BALANCE = false below to skip this.
 *
 * RECEIPT_AUDIT_LOG (from old receipts.edit_history)
 *  - edit_history is a JSON array of objects:
 *    { field, old_value, new_value, changed_by, role, changed_at }
 *  - changed_by may be a numeric user id OR a username string - both are
 *    handled (username is resolved via the `users` table)
 *  - Each entry becomes one receipt_audit_log row.
 *
 * RECEIPT_AUDIT_LOG (from old activities, where receiptId is set)
 *  - action      -> field_name
 *  - details     -> new_value
 *  - old_value   -> NULL
 *  - user_id (fallback: username) -> changed_by
 *  - role        -> looked up from the new `users` table via user_id,
 *                    defaults to 'unknown' if not resolvable
 *  - created_at  -> changed_at
 *  - Rows where receiptId is NULL/empty are skipped (receipt_id is NOT NULL
 *    in the new schema) and logged as warnings.
 *
 * ============================================================================
 * CONFIGURE BELOW
 * ============================================================================
 */

// ----------------------------------------------------------------------
// CONFIG
// ----------------------------------------------------------------------
$DB_HOST = '127.0.0.1';
$DB_NAME = 'your_database';
$DB_USER = 'your_user';
$DB_PASS = 'your_password';

// Old table names (rename if different)
$OLD_CLIENTS_TABLE    = 'old_clients';
$OLD_RECEIPTS_TABLE   = 'old_receipts';
$OLD_ACTIVITIES_TABLE = 'old_activities';

// New table names (per the schema you posted)
$NEW_CLIENTS_TABLE    = 'clients';
$NEW_RECEIPTS_TABLE   = 'receipts';
$NEW_TRANSACTIONS_TABLE = 'transactions';
$NEW_AUDIT_LOG_TABLE  = 'receipt_audit_log';
$NEW_USERS_TABLE      = 'users';
$NEW_BRANCHES_TABLE   = 'branches';
$NEW_CAPTAINS_TABLE   = 'captains';

// Preserve subscription_fee / remaining_balance as audit log entries?
$PRESERVE_FEE_AND_BALANCE = true;

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

// ----------------------------------------------------------------------
// HELPERS
// ----------------------------------------------------------------------

/** Resolve a user id from a numeric id and/or a username, with placeholder fallback */
function resolveUserId($id, $username, array $usernameToId, string $context, array &$warnings): int {
    if (!empty($id) && (int) $id > 0) {
        return (int) $id;
    }
    if (!empty($username)) {
        $key = mb_strtolower(trim((string) $username));
        if (isset($usernameToId[$key])) {
            return $usernameToId[$key];
        }
    }
    $warnings[] = "{$context}: could not resolve user id (id='" . var_export($id, true) . "', username='" . var_export($username, true) . "') - using placeholder 0.";
    return 0;
}

/** Resolve an id by name from a name->id map, with placeholder fallback */
function resolveByName(?string $name, array $nameToId, string $context, array &$warnings): int {
    if (empty($name)) {
        $warnings[] = "{$context}: name is empty - using placeholder 0.";
        return 0;
    }
    $key = mb_strtolower(trim($name));
    if (isset($nameToId[$key])) {
        return $nameToId[$key];
    }
    $warnings[] = "{$context}: '{$name}' not found - using placeholder 0.";
    return 0;
}

/** Convert a date/timestamp string to 'Y-m-d', or null */
function toDate(?string $value): ?string {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return null;
    }
    return date('Y-m-d', strtotime($value));
}

/** Convert a date/timestamp string to 'Y-m-d H:i:s', or null */
function toDateTime(?string $value): ?string {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return null;
    }
    return date('Y-m-d H:i:s', strtotime($value));
}

$pdo->beginTransaction();

try {
    // ------------------------------------------------------------------
    // STEP 0: Build lookup maps from already-migrated reference tables
    // ------------------------------------------------------------------
    $usernameToId = [];
    $userIdToRole = [];
    foreach ($pdo->query("SELECT id, username, role FROM `{$NEW_USERS_TABLE}`") as $u) {
        $usernameToId[mb_strtolower(trim($u['username']))] = (int) $u['id'];
        $userIdToRole[(int) $u['id']] = $u['role'];
    }

    $branchNameToId = [];
    foreach ($pdo->query("SELECT id, branch_name FROM `{$NEW_BRANCHES_TABLE}`") as $b) {
        $branchNameToId[mb_strtolower(trim($b['branch_name']))] = (int) $b['id'];
    }

    $captainNameToId = [];
    foreach ($pdo->query("SELECT id, captain_name FROM `{$NEW_CAPTAINS_TABLE}`") as $c) {
        $captainNameToId[mb_strtolower(trim($c['captain_name']))] = (int) $c['id'];
    }

    // ------------------------------------------------------------------
    // STEP 1: Migrate clients (old -> new)
    // ------------------------------------------------------------------
    $insertClient = $pdo->prepare("
        INSERT INTO `{$NEW_CLIENTS_TABLE}`
            (id, client_name, phone, created_by, age, gender, created_at, email)
        VALUES
            (:id, :client_name, :phone, :created_by, :age, NULL, :created_at, NULL)
    ");

    $oldClients = $pdo->query("
        SELECT id, name, phone, created_by, created_at, age
        FROM `{$OLD_CLIENTS_TABLE}`
    ")->fetchAll();

    foreach ($oldClients as $c) {
        $createdBy = resolveUserId(null, $c['created_by'], $usernameToId, "Client #{$c['id']}", $warnings);

        $insertClient->execute([
            ':id'          => $c['id'],
            ':client_name' => $c['name'],
            ':phone'       => $c['phone'],
            ':created_by'  => $createdBy,
            ':age'         => $c['age'],
            ':created_at'  => toDate($c['created_at']),
        ]);
    }
    echo "Migrated " . count($oldClients) . " client(s).\n";

    // ------------------------------------------------------------------
    // STEP 2: Migrate receipts -> new receipts + transactions
    //         (+ subscription_fee/remaining_balance -> audit log)
    // ------------------------------------------------------------------
    $oldReceipts = $pdo->query("
        SELECT * FROM `{$OLD_RECEIPTS_TABLE}`
    ")->fetchAll();

    // --- Generate receipt_ref values: YYMM + 4-digit monthly sequence ---
    // Sort a copy by created_at then id to determine sequence order
    $sorted = $oldReceipts;
    usort($sorted, function ($a, $b) {
        $da = $a['created_at'] ?: '1970-01-01';
        $db = $b['created_at'] ?: '1970-01-01';
        if ($da === $db) {
            return $a['id'] <=> $b['id'];
        }
        return strcmp($da, $db);
    });

    $monthCounters = [];
    $receiptRefs = []; // old receipt id => generated ref
    foreach ($sorted as $r) {
        $ts = $r['created_at'] ? strtotime($r['created_at']) : time();
        $yymm = date('ym', $ts);
        $monthCounters[$yymm] = ($monthCounters[$yymm] ?? 0) + 1;
        $receiptRefs[$r['id']] = $yymm . sprintf('%04d', $monthCounters[$yymm]);
    }

    $insertReceipt = $pdo->prepare("
        INSERT INTO `{$NEW_RECEIPTS_TABLE}`
            (id, receipt_ref, client_id, creator_id, captain_id, branch_id,
             first_session, last_session, renewal_session, created_at,
             renewal_type, receipt_status, exercise_time, plan_id, level, pdf_path)
        VALUES
            (:id, :receipt_ref, :client_id, :creator_id, :captain_id, :branch_id,
             :first_session, :last_session, :renewal_session, :created_at,
             :renewal_type, :receipt_status, :exercise_time, NULL, :level, NULL)
    ");

    $insertTransaction = $pdo->prepare("
        INSERT INTO `{$NEW_TRANSACTIONS_TABLE}`
            (payment_method, amount, receipt_id, created_by, created_at, attachment, notes, type)
        VALUES
            (:payment_method, :amount, :receipt_id, :created_by, :created_at, :attachment, :notes, :type)
    ");

    $insertAuditLog = $pdo->prepare("
        INSERT INTO `{$NEW_AUDIT_LOG_TABLE}`
            (receipt_id, changed_by, changed_at, role, field_name, old_value, new_value)
        VALUES
            (:receipt_id, :changed_by, :changed_at, :role, :field_name, :old_value, :new_value)
    ");

    $migratedReceipts    = 0;
    $migratedTxns        = 0;
    $migratedAuditFromEH = 0;

    foreach ($oldReceipts as $r) {
        $creatorId = resolveUserId($r['creator_id'], $r['creator_username'], $usernameToId, "Receipt #{$r['id']}: creator", $warnings);
        $captainId = resolveByName($r['coach'], $captainNameToId, "Receipt #{$r['id']}: coach", $warnings);
        $branchId  = resolveByName($r['branch'], $branchNameToId, "Receipt #{$r['id']}: branch", $warnings);

        $clientId = (int) ($r['client_id'] ?? 0);
        if ($clientId <= 0) {
            $warnings[] = "Receipt #{$r['id']}: client_id is empty - using placeholder 0.";
            $clientId = 0;
        }

        $createdAtDate = toDate($r['created_at']);

        $insertReceipt->execute([
            ':id'              => $r['id'],
            ':receipt_ref'     => $receiptRefs[$r['id']],
            ':client_id'       => $clientId,
            ':creator_id'      => $creatorId,
            ':captain_id'      => $captainId,
            ':branch_id'       => $branchId,
            ':first_session'   => toDate($r['first_session']),
            ':last_session'    => toDate($r['last_session']),
            ':renewal_session' => toDate($r['renewal_date']),
            ':created_at'      => $createdAtDate,
            ':renewal_type'    => $r['renew_type'],
            ':receipt_status'  => $r['status'] ?: 'not_completed',
            ':exercise_time'   => $r['exerciseTime'],
            ':level'           => $r['clientLevel'],
        ]);
        $migratedReceipts++;

        // ---- Transaction (one per old receipt, if payment info present) ----
        $hasPayment = !empty($r['payment_method']) || (!empty($r['paid_amount']) && (float) $r['paid_amount'] != 0);

        if ($hasPayment) {
            $insertTransaction->execute([
                ':payment_method' => $r['payment_method'],
                ':amount'         => $r['paid_amount'],
                ':receipt_id'     => $r['id'],
                ':created_by'     => $creatorId,
                ':created_at'     => $createdAtDate,
                ':attachment'     => $r['attachment'],
                ':notes'          => $r['notes'],
                ':type'           => $r['type'] ?: 'payment',
            ]);
            $migratedTxns++;
        }

        // ---- Preserve subscription_fee / remaining_balance as audit entries ----
        if ($PRESERVE_FEE_AND_BALANCE) {
            $changedAtDt = toDateTime($r['created_at']) ?? date('Y-m-d H:i:s');
            $role = $r['creator_role'] ?: 'system';

            if (!empty($r['subscription_fee'])) {
                $insertAuditLog->execute([
                    ':receipt_id' => $r['id'],
                    ':changed_by' => $creatorId,
                    ':changed_at' => $changedAtDt,
                    ':role'       => $role,
                    ':field_name' => 'subscription_fee',
                    ':old_value'  => null,
                    ':new_value'  => $r['subscription_fee'],
                ]);
            }
            if (!empty($r['remaining_balance'])) {
                $insertAuditLog->execute([
                    ':receipt_id' => $r['id'],
                    ':changed_by' => $creatorId,
                    ':changed_at' => $changedAtDt,
                    ':role'       => $role,
                    ':field_name' => 'remaining_balance',
                    ':old_value'  => null,
                    ':new_value'  => $r['remaining_balance'],
                ]);
            }
        }

        // ---- edit_history -> receipt_audit_log ----
        $editHistory = json_decode($r['edit_history'] ?: '[]', true);
        if (!is_array($editHistory)) {
            $warnings[] = "Receipt #{$r['id']}: edit_history is not valid JSON - skipped.";
            $editHistory = [];
        }

        foreach ($editHistory as $entry) {
            if (empty($entry['field'])) {
                continue;
            }

            $changedByRaw = $entry['changed_by'] ?? null;
            if (is_numeric($changedByRaw)) {
                $changedById = (int) $changedByRaw;
            } else {
                $changedById = resolveUserId(null, $changedByRaw, $usernameToId, "Receipt #{$r['id']}: edit_history entry", $warnings);
            }

            $oldVal = $entry['old_value'] ?? null;
            $newVal = $entry['new_value'] ?? null;
            if (is_array($oldVal)) { $oldVal = json_encode($oldVal); }
            if (is_array($newVal)) { $newVal = json_encode($newVal); }

            $insertAuditLog->execute([
                ':receipt_id' => $r['id'],
                ':changed_by' => $changedById,
                ':changed_at' => toDateTime($entry['changed_at'] ?? null) ?? (toDateTime($r['created_at']) ?? date('Y-m-d H:i:s')),
                ':role'       => $entry['role'] ?? 'unknown',
                ':field_name' => $entry['field'],
                ':old_value'  => $oldVal,
                ':new_value'  => $newVal,
            ]);
            $migratedAuditFromEH++;
        }
    }

    echo "Migrated {$migratedReceipts} receipt(s).\n";
    echo "Created {$migratedTxns} transaction(s).\n";
    echo "Created {$migratedAuditFromEH} audit log entries from edit_history.\n";

    // ------------------------------------------------------------------
    // STEP 3: Migrate old activities -> receipt_audit_log (where receiptId set)
    // ------------------------------------------------------------------
    $oldActivities = $pdo->query("
        SELECT id, user_id, username, action, details, created_at, receiptId
        FROM `{$OLD_ACTIVITIES_TABLE}`
    ")->fetchAll();

    $migratedActivities = 0;
    $skippedActivities  = 0;

    foreach ($oldActivities as $a) {
        if (empty($a['receiptId'])) {
            $skippedActivities++;
            continue;
        }

        $changedById = resolveUserId($a['user_id'], $a['username'], $usernameToId, "Activity #{$a['id']}", $warnings);
        $role = $userIdToRole[$changedById] ?? 'unknown';

        $insertAuditLog->execute([
            ':receipt_id' => $a['receiptId'],
            ':changed_by' => $changedById,
            ':changed_at' => toDateTime($a['created_at']) ?? date('Y-m-d H:i:s'),
            ':role'       => $role,
            ':field_name' => $a['action'] ?: 'unknown_action',
            ':old_value'  => null,
            ':new_value'  => $a['details'],
        ]);
        $migratedActivities++;
    }

    echo "Migrated {$migratedActivities} activity log entr(y/ies) into receipt_audit_log.\n";
    echo "Skipped {$skippedActivities} activit(y/ies) with no receiptId.\n";

    $pdo->commit();
    echo "Migration completed successfully.\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    die("Migration FAILED, all changes rolled back: " . $e->getMessage() . "\n");
}

// ------------------------------------------------------------------
// Report warnings
// ------------------------------------------------------------------
if (!empty($warnings)) {
    echo "\n--- Warnings (" . count($warnings) . ") ---\n";
    foreach ($warnings as $w) {
        echo $w . "\n";
    }
}
