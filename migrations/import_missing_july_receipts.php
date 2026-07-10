<?php
require dirname(__DIR__) . '/config/database.php';

$apply = in_array('--apply', $argv, true);
$oldDump = dirname(__DIR__) . '/swimming_academy_old.sql';
$fromDate = '2026-07-01';
$toDate = '2026-07-31';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $fromDate = substr($arg, 7);
    }
    if (str_starts_with($arg, '--to=')) {
        $toDate = substr($arg, 5);
    }
}

function norm_key(?string $value): string {
    return mb_strtolower(trim((string) $value));
}

function clean_value(?string $value): ?string {
    if ($value === null || strtoupper($value) === 'NULL' || $value === '') {
        return null;
    }
    return $value;
}

function date_part(?string $value): ?string {
    $value = clean_value($value);
    return $value ? substr($value, 0, 10) : null;
}

function map_role(?string $role): string {
    return match ($role) {
        'superAdmin', 'admin' => 'admin',
        'areaManager' => 'area_manager',
        'manager' => 'branch_manager',
        'customerService' => 'customer_service',
        default => 'customer_service',
    };
}

function receipt_type(?string $type, ?string $renewType): ?string {
    if ($type === 'fresh') {
        return 'new';
    }
    if ($type === 'renew') {
        return 'renew';
    }
    return clean_value($renewType) ?? clean_value($type);
}

function read_old_dump_utf8(string $path): string {
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("Cannot read {$path}");
    }

    if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
        $converted = iconv('UTF-16', 'UTF-8//IGNORE', $raw);
        if ($converted === false) {
            throw new RuntimeException('Failed to convert old dump from UTF-16 to UTF-8');
        }
        return $converted;
    }

    return $raw;
}

function each_receipt_row(string $sql, callable $callback): void {
    $marker = 'INSERT INTO `receipts` VALUES ';
    $start = strpos($sql, $marker);
    if ($start === false) {
        throw new RuntimeException('No receipts INSERT found in old dump');
    }
    $start += strlen($marker);

    $end = strpos($sql, ";\r\n/*!40000 ALTER TABLE `receipts` ENABLE KEYS */", $start);
    if ($end === false) {
        $end = strpos($sql, ";\n/*!40000 ALTER TABLE `receipts` ENABLE KEYS */", $start);
    }
    if ($end === false) {
        throw new RuntimeException('Could not find end of receipts INSERT');
    }

    $values = substr($sql, $start, $end - $start);
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

$db = get_db();
$db->exec("SET time_zone = '+02:00'");

echo $apply ? "Applying import...\n" : "Dry run only. Use --apply to write changes.\n";
echo "Reading old dump...\n";
$sql = read_old_dump_utf8($oldDump);

$oldRows = [];
each_receipt_row($sql, function (array $row) use (&$oldRows, $fromDate, $toDate): void {
    $createdDate = date_part($row[16] ?? null);
    if ($createdDate >= $fromDate && $createdDate <= $toDate) {
        $oldRows[] = $row;
    }
});

echo "Rows in old dump from {$fromDate} to {$toDate}: " . count($oldRows) . "\n";

$branchMap = [];
foreach ($db->query("SELECT id, branch_name FROM branches") as $row) {
    $branchMap[norm_key($row['branch_name'])] = (int) $row['id'];
}

$captainMap = [];
foreach ($db->query("SELECT id, captain_name FROM captains") as $row) {
    $captainMap[norm_key($row['captain_name'])] = (string) $row['id'];
}

$maxBranchId = (int) $db->query("SELECT COALESCE(MAX(id), 0) FROM branches")->fetchColumn();
$maxCaptainId = (int) $db->query("SELECT COALESCE(MAX(CAST(id AS UNSIGNED)), 0) FROM captains WHERE id REGEXP '^[0-9]+$'")->fetchColumn();

$existsUser = $db->prepare("SELECT 1 FROM users WHERE id = ? LIMIT 1");
$existsClient = $db->prepare("SELECT 1 FROM clients WHERE id = ? LIMIT 1");
$existsReceipt = $db->prepare("SELECT 1 FROM receipts WHERE id = ? LIMIT 1");
$existsPhone = $db->prepare("SELECT 1 FROM clients WHERE phone = ? AND id <> ? LIMIT 1");

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
        $createdAt = date_part($row[16] ?? null);

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
                    ':username' => clean_value($row[5] ?? null) ?? "Migrated User {$creatorId}",
                    ':email' => "migrated.user.{$creatorId}@migration.local",
                    ':role' => map_role(clean_value($row[25] ?? null)),
                    ':created_at' => $createdAt,
                ]);
            }
        }

        $existsClient->execute([$clientId]);
        if (!$existsClient->fetchColumn()) {
            $phone = clean_value($row[3] ?? null) ?? "missing-phone-{$clientId}";
            $existsPhone->execute([$phone, $clientId]);
            if ($existsPhone->fetchColumn()) {
                $phone .= "#missing-client-{$clientId}";
            }

            $stats['clients']++;
            if ($apply) {
                $insertClient->execute([
                    ':id' => $clientId,
                    ':client_name' => clean_value($row[2] ?? null) ?? "Migrated Client {$clientId}",
                    ':phone' => $phone,
                    ':created_by' => $creatorId,
                    ':age' => clean_value($row[21] ?? null) !== null ? (int) $row[21] : null,
                    ':created_at' => $createdAt,
                    ':email' => "missing.client.{$clientId}@migration.local",
                ]);
            }
        }

        $branchName = clean_value($row[4] ?? null) ?? 'Unknown Branch';
        $branchKey = norm_key($branchName);
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

        $captainName = clean_value($row[7] ?? null) ?? 'Unknown Captain';
        $captainKey = norm_key($captainName);
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
                ':first_session' => clean_value($row[8] ?? null),
                ':last_session' => clean_value($row[9] ?? null),
                ':renewal_session' => clean_value($row[10] ?? null),
                ':created_at' => $createdAt,
                ':renewal_type' => receipt_type(clean_value($row[20] ?? null), clean_value($row[22] ?? null)),
                ':receipt_status' => clean_value($row[15] ?? null) ?? 'not_completed',
                ':exercise_time' => clean_value($row[17] ?? null),
                ':level' => clean_value($row[26] ?? null) !== null ? (int) $row[26] : null,
                ':pdf_path' => clean_value($row[18] ?? null),
            ]);
        }

        $paidAmount = clean_value($row[12] ?? null);
        if ($paidAmount !== null && (float) $paidAmount != 0.0) {
            $stats['transactions']++;
            if ($apply) {
                $insertTransaction->execute([
                    ':payment_method' => clean_value($row[11] ?? null),
                    ':amount' => $paidAmount,
                    ':receipt_id' => $receiptId,
                    ':created_by' => $creatorId,
                    ':created_at' => $createdAt,
                    ':attachment' => clean_value($row[18] ?? null),
                    ':notes' => clean_value($row[19] ?? null) ?? 'Migrated from old receipt payment',
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
