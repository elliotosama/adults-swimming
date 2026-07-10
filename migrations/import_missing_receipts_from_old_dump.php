<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/database.php';

$apply = in_array('--apply', $argv, true);
$oldDump = dirname(__DIR__) . '/swimming_academy_old.sql';
$missingIdsFile = dirname(__DIR__) . '/missing_receipt_ids.txt';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--old-dump=')) {
        $oldDump = substr($arg, 11);
    }
    if (str_starts_with($arg, '--ids=')) {
        $missingIdsFile = substr($arg, 6);
    }
}

function migration_norm_key(?string $value): string
{
    return mb_strtolower(trim((string) $value));
}

function migration_clean_value(?string $value): ?string
{
    if ($value === null || strtoupper($value) === 'NULL' || $value === '') {
        return null;
    }

    return $value;
}

function migration_date_part(?string $value): ?string
{
    $value = migration_clean_value($value);
    return $value ? substr($value, 0, 10) : null;
}

function migration_map_role(?string $role): string
{
    return match ($role) {
        'superAdmin', 'admin' => 'admin',
        'areaManager' => 'area_manager',
        'manager' => 'branch_manager',
        'customerService' => 'customer_service',
        default => 'customer_service',
    };
}

function migration_receipt_type(?string $type, ?string $renewType): ?string
{
    if ($type === 'fresh') {
        return 'new';
    }
    if ($type === 'renew') {
        return 'renew';
    }

    return migration_clean_value($renewType) ?? migration_clean_value($type);
}

function migration_read_dump_utf8(string $path): string
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("Cannot read {$path}");
    }

    if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
        $converted = iconv('UTF-16', 'UTF-8//IGNORE', $raw);
        if ($converted === false) {
            throw new RuntimeException("Failed to convert {$path} from UTF-16 to UTF-8");
        }

        return $converted;
    }

    return $raw;
}

function migration_each_value_tuple(string $values, callable $callback): void
{
    $length = strlen($values);
    $inString = false;
    $escaped = false;
    $depth = 0;
    $buffer = '';

    for ($i = 0; $i < $length; $i++) {
        $ch = $values[$i];

        if ($depth === 0) {
            if ($ch === '(') {
                $depth = 1;
                $buffer = '';
            }
            continue;
        }

        if ($inString) {
            if ($escaped) {
                $escaped = false;
            } elseif ($ch === '\\') {
                $escaped = true;
            } elseif ($ch === "'") {
                $inString = false;
            }
            $buffer .= $ch;
            continue;
        }

        if ($ch === "'") {
            $inString = true;
            $buffer .= $ch;
            continue;
        }

        if ($ch === '(') {
            $depth++;
            $buffer .= $ch;
            continue;
        }

        if ($ch === ')') {
            $depth--;
            if ($depth === 0) {
                $callback(str_getcsv($buffer, ',', "'", '\\'));
            } else {
                $buffer .= $ch;
            }
            continue;
        }

        $buffer .= $ch;
    }
}

function migration_each_old_receipt_row(string $sql, callable $callback): void
{
    $pattern = '/INSERT\s+INTO\s+`receipts`\s+VALUES\s*/i';
    $offset = 0;
    $found = false;

    while (preg_match($pattern, $sql, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $found = true;
        $start = $match[0][1] + strlen($match[0][0]);
        $end = strpos($sql, ';', $start);
        if ($end === false) {
            throw new RuntimeException('Unterminated receipts INSERT in old dump');
        }

        migration_each_value_tuple(substr($sql, $start, $end - $start), $callback);
        $offset = $end + 1;
    }

    if (!$found) {
        throw new RuntimeException('No receipts INSERT blocks found in old dump');
    }
}

function migration_load_missing_ids(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException("Cannot read {$path}");
    }

    $ids = [];
    foreach ($lines as $line) {
        $id = (int) trim($line);
        if ($id > 0) {
            $ids[$id] = true;
        }
    }

    return $ids;
}

$missingIds = migration_load_missing_ids($missingIdsFile);

echo $apply ? "Applying import...\n" : "Dry run only. Use --apply to write changes.\n";
echo 'Requested missing receipt IDs: ' . count($missingIds) . PHP_EOL;
echo "Reading old dump...\n";

$oldRows = [];
migration_each_old_receipt_row(migration_read_dump_utf8($oldDump), function (array $row) use (&$oldRows, $missingIds): void {
    $receiptId = (int) ($row[0] ?? 0);
    if (isset($missingIds[$receiptId])) {
        $oldRows[$receiptId] = $row;
    }
});
ksort($oldRows);

echo 'Found requested IDs in old dump: ' . count($oldRows) . PHP_EOL;
echo 'Requested IDs not found in old dump: ' . (count($missingIds) - count($oldRows)) . PHP_EOL;

$db = get_db();
$db->exec("SET time_zone = '+02:00'");

$branchMap = [];
foreach ($db->query('SELECT id, branch_name FROM branches') as $row) {
    $branchMap[migration_norm_key($row['branch_name'])] = (int) $row['id'];
}

$captainMap = [];
foreach ($db->query('SELECT id, captain_name FROM captains') as $row) {
    $captainMap[migration_norm_key($row['captain_name'])] = (string) $row['id'];
}

$maxBranchId = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM branches')->fetchColumn();
$maxCaptainId = (int) $db->query("SELECT COALESCE(MAX(CAST(id AS UNSIGNED)), 0) FROM captains WHERE id REGEXP '^[0-9]+$'")->fetchColumn();

$existsUser = $db->prepare('SELECT 1 FROM users WHERE id = ? LIMIT 1');
$existsClient = $db->prepare('SELECT 1 FROM clients WHERE id = ? LIMIT 1');
$existsReceipt = $db->prepare('SELECT 1 FROM receipts WHERE id = ? LIMIT 1');
$existsPhone = $db->prepare('SELECT 1 FROM clients WHERE phone = ? AND id <> ? LIMIT 1');

$insertUser = $db->prepare("
    INSERT INTO users (id, username, email, password_hash, role, phone, visible, created_at, last_login, is_active)
    VALUES (:id, :username, :email, NULL, :role, NULL, 1, :created_at, NULL, 1)
");
$insertClient = $db->prepare("
    INSERT INTO clients (id, client_name, phone, created_by, age, gender, created_at, email, visible)
    VALUES (:id, :client_name, :phone, :created_by, :age, NULL, :created_at, :email, 1)
");
$insertBranch = $db->prepare("
    INSERT INTO branches (id, branch_name, created_at, visible, country_id)
    VALUES (:id, :branch_name, CURRENT_DATE, 1, NULL)
");
$insertCaptain = $db->prepare("
    INSERT INTO captains (id, captain_name, phone_number, created_at, created_by, visible)
    VALUES (:id, :captain_name, NULL, CURRENT_DATE, NULL, 1)
");
$insertReceipt = $db->prepare("
    INSERT INTO receipts
        (id, receipt_ref, client_id, creator_id, captain_id, branch_id,
         first_session, last_session, renewal_session, created_at,
         renewal_type, receipt_status, exercise_time, plan_id, level, pdf_path, is_refunded)
    VALUES
        (:id, :receipt_ref, :client_id, :creator_id, :captain_id, :branch_id,
         :first_session, :last_session, :renewal_session, :created_at,
         :renewal_type, :receipt_status, :exercise_time, NULL, :level, :pdf_path, 0)
");
$insertTransaction = $db->prepare("
    INSERT INTO transactions (payment_method, amount, receipt_id, created_by, created_at, attachment, notes, type)
    VALUES (:payment_method, :amount, :receipt_id, :created_by, :created_at, :attachment, :notes, 'payment')
");

$stats = [
    'users' => 0,
    'clients' => 0,
    'branches' => 0,
    'captains' => 0,
    'receipts' => 0,
    'transactions' => 0,
    'skipped_existing' => 0,
    'skipped_invalid' => 0,
];

if ($apply) {
    $db->beginTransaction();
}

try {
    foreach ($oldRows as $row) {
        $receiptId = (int) ($row[0] ?? 0);
        $clientId = (int) ($row[1] ?? 0);
        $creatorId = (int) ($row[6] ?? 0);
        $createdAt = migration_date_part($row[16] ?? null);

        if (!$receiptId || !$clientId || !$creatorId || !$createdAt) {
            $stats['skipped_invalid']++;
            continue;
        }

        $existsReceipt->execute([$receiptId]);
        if ($existsReceipt->fetchColumn()) {
            $stats['skipped_existing']++;
            continue;
        }

        $existsUser->execute([$creatorId]);
        if (!$existsUser->fetchColumn()) {
            $stats['users']++;
            if ($apply) {
                $insertUser->execute([
                    ':id' => $creatorId,
                    ':username' => migration_clean_value($row[5] ?? null) ?? "Migrated User {$creatorId}",
                    ':email' => "migrated.user.{$creatorId}@gmail.com",
                    ':role' => migration_map_role(migration_clean_value($row[25] ?? null)),
                    ':created_at' => $createdAt,
                ]);
            }
        }

        $existsClient->execute([$clientId]);
        if (!$existsClient->fetchColumn()) {
            $phone = migration_clean_value($row[3] ?? null) ?? "missing-phone-{$clientId}";
            $existsPhone->execute([$phone, $clientId]);
            if ($existsPhone->fetchColumn()) {
                $phone .= "#missing-client-{$clientId}";
            }

            $stats['clients']++;
            if ($apply) {
                $insertClient->execute([
                    ':id' => $clientId,
                    ':client_name' => migration_clean_value($row[2] ?? null) ?? "Migrated Client {$clientId}",
                    ':phone' => $phone,
                    ':created_by' => $creatorId,
                    ':age' => migration_clean_value($row[21] ?? null) !== null ? (int) $row[21] : null,
                    ':created_at' => $createdAt,
                    ':email' => "missing.client.{$clientId}@migration.local",
                ]);
            }
        }

        $branchName = migration_clean_value($row[4] ?? null) ?? 'Unknown Branch';
        $branchKey = migration_norm_key($branchName);
        if (!isset($branchMap[$branchKey])) {
            $maxBranchId++;
            $branchMap[$branchKey] = $maxBranchId;
            $stats['branches']++;
            if ($apply) {
                $insertBranch->execute([
                    ':id' => $maxBranchId,
                    ':branch_name' => $branchName,
                ]);
            }
        }

        $captainName = migration_clean_value($row[7] ?? null) ?? 'Unknown Captain';
        $captainKey = migration_norm_key($captainName);
        if (!isset($captainMap[$captainKey])) {
            $maxCaptainId++;
            $captainMap[$captainKey] = (string) $maxCaptainId;
            $stats['captains']++;
            if ($apply) {
                $insertCaptain->execute([
                    ':id' => (string) $maxCaptainId,
                    ':captain_name' => $captainName,
                ]);
            }
        }

        $stats['receipts']++;
        if ($apply) {
            $insertReceipt->execute([
                ':id' => $receiptId,
                ':receipt_ref' => (string) $receiptId,
                ':client_id' => $clientId,
                ':creator_id' => $creatorId,
                ':captain_id' => $captainMap[$captainKey],
                ':branch_id' => $branchMap[$branchKey],
                ':first_session' => migration_clean_value($row[8] ?? null),
                ':last_session' => migration_clean_value($row[9] ?? null),
                ':renewal_session' => migration_clean_value($row[10] ?? null),
                ':created_at' => $createdAt,
                ':renewal_type' => migration_receipt_type(migration_clean_value($row[20] ?? null), migration_clean_value($row[22] ?? null)),
                ':receipt_status' => migration_clean_value($row[15] ?? null) ?? 'not_completed',
                ':exercise_time' => migration_clean_value($row[17] ?? null),
                ':level' => migration_clean_value($row[26] ?? null) !== null ? (int) $row[26] : null,
                ':pdf_path' => migration_clean_value($row[18] ?? null),
            ]);
        }

        $paidAmount = migration_clean_value($row[12] ?? null);
        if ($paidAmount !== null && (float) $paidAmount != 0.0) {
            $stats['transactions']++;
            if ($apply) {
                $insertTransaction->execute([
                    ':payment_method' => migration_clean_value($row[11] ?? null),
                    ':amount' => $paidAmount,
                    ':receipt_id' => $receiptId,
                    ':created_by' => $creatorId,
                    ':created_at' => $createdAt,
                    ':attachment' => migration_clean_value($row[18] ?? null),
                    ':notes' => migration_clean_value($row[19] ?? null) ?? 'Migrated from old receipt payment',
                ]);
            }
        }
    }

    if ($apply) {
        $db->commit();
    }
} catch (Throwable $e) {
    if ($apply && $db->inTransaction()) {
        $db->rollBack();
    }
    throw $e;
}

foreach ($stats as $key => $value) {
    echo "{$key}: {$value}\n";
}
