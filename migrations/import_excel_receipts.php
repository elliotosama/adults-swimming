<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/database.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

$apply = in_array('--apply', $argv, true);
$xlsxPath = dirname(__DIR__) . '/receipts(5).xlsx';
$oldDump = dirname(__DIR__) . '/swimming_academy_old.sql';
$fromId = 26070396;
$toId = PHP_INT_MAX;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--file=')) {
        $xlsxPath = substr($arg, 7);
    } elseif (str_starts_with($arg, '--old-dump=')) {
        $oldDump = substr($arg, 11);
    } elseif (str_starts_with($arg, '--from-id=')) {
        $fromId = (int) substr($arg, 10);
    } elseif (str_starts_with($arg, '--to-id=')) {
        $toId = (int) substr($arg, 8);
    }
}

function excel_norm_key(?string $value): string
{
    return mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '');
}

function excel_clean(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '' || mb_strtolower($value) === 'null') {
        return null;
    }

    return preg_replace('/\s+/u', ' ', $value) ?: null;
}

function excel_date_value(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
    }

    $value = trim((string) $value);
    foreach (['m/d/Y', 'd/m/Y', 'Y-m-d'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date instanceof DateTimeImmutable) {
            return $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

function excel_time_value(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        return ExcelDate::excelToDateTimeObject((float) $value)->format('H:i:s');
    }

    $value = trim((string) $value);
    foreach (['H:i:s', 'H:i', 'g:i A'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date instanceof DateTimeImmutable) {
            return $date->format('H:i:s');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('H:i:s', $timestamp) : null;
}

function excel_read_dump_utf8(string $path): string
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

function excel_each_value_tuple(string $values, callable $callback): void
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

function excel_load_old_evidence(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $sql = excel_read_dump_utf8($path);
    $pattern = '/INSERT\s+INTO\s+`receipts`\s+VALUES\s*/i';
    $offset = 0;
    $evidenceByReceipt = [];

    while (preg_match($pattern, $sql, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $start = $match[0][1] + strlen($match[0][0]);
        $end = strpos($sql, ';', $start);
        if ($end === false) {
            throw new RuntimeException('Unterminated receipts INSERT in old dump');
        }

        excel_each_value_tuple(substr($sql, $start, $end - $start), function (array $row) use (&$evidenceByReceipt): void {
            $receiptId = (int) ($row[0] ?? 0);
            $attachment = excel_clean($row[18] ?? null);
            if ($receiptId > 0 && $attachment !== null && $attachment !== '[]') {
                $evidenceByReceipt[$receiptId] = $attachment;
            }
        });

        $offset = $end + 1;
    }

    return $evidenceByReceipt;
}

function excel_renewal_type(?string $type): string
{
    return match (excel_clean($type)) {
        'جديد' => 'new',
        'حالي' => 'current_renewal',
        'سابق' => 'previous_renewal',
        default => excel_clean($type) ?? 'new',
    };
}

function excel_status(?string $status): string
{
    return match (excel_clean($status)) {
        'مكتمل' => 'completed',
        'غير مكتمل' => 'not_completed',
        default => excel_clean($status) ?? 'not_completed',
    };
}

function next_numeric_id(PDO $db, string $table): int
{
    return (int) $db->query("SELECT COALESCE(MAX(CAST(id AS UNSIGNED)), 0) FROM {$table} WHERE id REGEXP '^[0-9]+$'")->fetchColumn() + 1;
}

$db = get_db();
$db->exec("SET time_zone = '+02:00'");

$spreadsheet = IOFactory::load($xlsxPath);
$sheet = $spreadsheet->getSheetByName('Receipts') ?? $spreadsheet->getActiveSheet();
$rows = $sheet->toArray(null, true, true, false);
array_shift($rows);

$oldEvidenceByReceipt = excel_load_old_evidence($oldDump);

$branchMap = [];
foreach ($db->query('SELECT id, branch_name, country_id FROM branches') as $row) {
    $branchMap[excel_norm_key($row['branch_name'])] = [
        'id' => (int) $row['id'],
        'country_id' => $row['country_id'] !== null ? (int) $row['country_id'] : null,
    ];
}

$captainMap = [];
foreach ($db->query('SELECT id, captain_name FROM captains') as $row) {
    $captainMap[excel_norm_key($row['captain_name'])] = (string) $row['id'];
}

$userMap = [];
foreach ($db->query('SELECT id, username FROM users') as $row) {
    $userMap[excel_norm_key($row['username'])] = (int) $row['id'];
}

$priceMap = [];
foreach ($db->query('SELECT id, price, country_id FROM prices ORDER BY visible DESC, id ASC') as $row) {
    $key = ((int) round((float) $row['price'])) . ':' . (int) ($row['country_id'] ?? 0);
    $priceMap[$key] ??= (int) $row['id'];
}

$existsReceipt = $db->prepare('SELECT 1 FROM receipts WHERE id = ? LIMIT 1');
$existsClient = $db->prepare('SELECT 1 FROM clients WHERE id = ? LIMIT 1');
$existsPhone = $db->prepare('SELECT 1 FROM clients WHERE phone = ? AND id <> ? LIMIT 1');

$insertUser = $db->prepare("
    INSERT INTO users (id, username, email, password_hash, role, phone, visible, created_at, last_login, is_active)
    VALUES (:id, :username, :email, NULL, 'customer_service', NULL, 1, :created_at, NULL, 1)
");
$insertClient = $db->prepare("
    INSERT INTO clients (id, client_name, phone, created_by, age, gender, created_at, email, visible)
    VALUES (:id, :client_name, :phone, :created_by, :age, NULL, :created_at, :email, 1)
");
$insertBranch = $db->prepare("
    INSERT INTO branches (id, branch_name, created_at, visible, country_id)
    VALUES (:id, :branch_name, :created_at, 1, 4)
");
$insertCaptain = $db->prepare("
    INSERT INTO captains (id, captain_name, phone_number, created_at, created_by, visible)
    VALUES (:id, :captain_name, NULL, :created_at, NULL, 1)
");
$insertPrice = $db->prepare("
    INSERT INTO prices (description, price, created_at, updated_at, visible, number_of_sessions, country_id)
    VALUES (:description, :price, :created_at, :created_at, 1, 8, :country_id)
");
$insertReceipt = $db->prepare("
    INSERT INTO receipts
        (id, receipt_ref, client_id, creator_id, captain_id, branch_id,
         first_session, last_session, renewal_session, created_at,
         renewal_type, receipt_status, exercise_time, plan_id, level, pdf_path, is_refunded)
    VALUES
        (:id, :receipt_ref, :client_id, :creator_id, :captain_id, :branch_id,
         :first_session, :last_session, :renewal_session, :created_at,
         :renewal_type, :receipt_status, :exercise_time, :plan_id, :level, NULL, 0)
");
$insertTransaction = $db->prepare("
    INSERT INTO transactions (payment_method, amount, receipt_id, created_by, created_at, attachment, notes, type)
    VALUES (:payment_method, :amount, :receipt_id, :created_by, :created_at, :attachment, :notes, 'payment')
");

$nextUserId = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM users')->fetchColumn() + 1;
$nextBranchId = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM branches')->fetchColumn() + 1;
$nextCaptainId = next_numeric_id($db, 'captains');

$stats = [
    'candidate_rows' => 0,
    'users' => 0,
    'clients' => 0,
    'branches' => 0,
    'captains' => 0,
    'prices' => 0,
    'receipts' => 0,
    'transactions' => 0,
    'skipped_existing' => 0,
    'skipped_invalid' => 0,
];

echo $apply ? "Applying Excel import...\n" : "Dry run only. Use --apply to write changes.\n";
echo "ID range: {$fromId} - {$toId}\n";
echo 'Old evidence records loaded: ' . count($oldEvidenceByReceipt) . PHP_EOL;

if ($apply) {
    $db->beginTransaction();
}

try {
    foreach ($rows as $row) {
        $receiptId = (int) ($row[0] ?? 0);
        if ($receiptId < $fromId || $receiptId > $toId) {
            continue;
        }

        $stats['candidate_rows']++;

        $clientId = (int) ($row[1] ?? 0);
        $clientName = excel_clean($row[2] ?? null);
        $phone = excel_clean($row[3] ?? null);
        $fee = (float) ($row[5] ?? 0);
        $paid = (float) ($row[6] ?? 0);
        $paymentMethod = excel_clean($row[8] ?? null);
        $createdAt = excel_date_value($row[9] ?? null);
        $age = excel_clean($row[10] ?? null);
        $branchName = excel_clean($row[11] ?? null) ?? 'Unknown Branch';
        $creatorName = excel_clean($row[12] ?? null) ?? 'Migrated Excel User';
        $captainName = excel_clean($row[13] ?? null) ?? 'لم يحدد';
        $firstSession = excel_date_value($row[14] ?? null);
        $lastSession = excel_date_value($row[15] ?? null);
        $exerciseTime = excel_time_value($row[16] ?? null);
        $renewalSession = excel_date_value($row[17] ?? null);
        $notes = excel_clean($row[19] ?? null) ?? 'Imported from Excel receipts sheet';

        if (!$receiptId || !$clientId || !$clientName || !$phone || !$createdAt) {
            $stats['skipped_invalid']++;
            continue;
        }

        $existsReceipt->execute([$receiptId]);
        if ($existsReceipt->fetchColumn()) {
            $stats['skipped_existing']++;
            continue;
        }

        $creatorKey = excel_norm_key($creatorName);
        if (!isset($userMap[$creatorKey])) {
            $userMap[$creatorKey] = $nextUserId++;
            $stats['users']++;
            if ($apply) {
                $insertUser->execute([
                    ':id' => $userMap[$creatorKey],
                    ':username' => $creatorName,
                    ':email' => 'excel.user.' . $userMap[$creatorKey] . '@gmail.com',
                    ':created_at' => $createdAt,
                ]);
            }
        }
        $creatorId = $userMap[$creatorKey];

        $existsClient->execute([$clientId]);
        if (!$existsClient->fetchColumn()) {
            $clientPhone = $phone;
            $existsPhone->execute([$clientPhone, $clientId]);
            if ($existsPhone->fetchColumn()) {
                $clientPhone .= "#excel-client-{$clientId}";
            }

            $stats['clients']++;
            if ($apply) {
                $insertClient->execute([
                    ':id' => $clientId,
                    ':client_name' => $clientName,
                    ':phone' => $clientPhone,
                    ':created_by' => $creatorId,
                    ':age' => $age !== null ? (int) $age : null,
                    ':created_at' => $createdAt,
                    ':email' => "excel.client.{$clientId}@migration.local",
                ]);
            }
        }

        $branchKey = excel_norm_key($branchName);
        if (!isset($branchMap[$branchKey])) {
            $branchMap[$branchKey] = ['id' => $nextBranchId++, 'country_id' => 4];
            $stats['branches']++;
            if ($apply) {
                $insertBranch->execute([
                    ':id' => $branchMap[$branchKey]['id'],
                    ':branch_name' => $branchName,
                    ':created_at' => $createdAt,
                ]);
            }
        }

        $captainKey = excel_norm_key($captainName);
        if (!isset($captainMap[$captainKey])) {
            $captainMap[$captainKey] = (string) $nextCaptainId++;
            $stats['captains']++;
            if ($apply) {
                $insertCaptain->execute([
                    ':id' => $captainMap[$captainKey],
                    ':captain_name' => $captainName,
                    ':created_at' => $createdAt,
                ]);
            }
        }

        $countryId = $branchMap[$branchKey]['country_id'] ?? 4;
        $priceKey = ((int) round($fee)) . ':' . (int) $countryId;
        if ($fee > 0 && !isset($priceMap[$priceKey])) {
            $stats['prices']++;
            if ($apply) {
                $insertPrice->execute([
                    ':description' => 'Excel imported plan ' . (int) round($fee),
                    ':price' => $fee,
                    ':created_at' => $createdAt,
                    ':country_id' => $countryId,
                ]);
                $priceMap[$priceKey] = (int) $db->lastInsertId();
            } else {
                $priceMap[$priceKey] = 0;
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
                ':branch_id' => $branchMap[$branchKey]['id'],
                ':first_session' => $firstSession,
                ':last_session' => $lastSession,
                ':renewal_session' => $renewalSession,
                ':created_at' => $createdAt,
                ':renewal_type' => excel_renewal_type(excel_clean($row[4] ?? null)),
                ':receipt_status' => excel_status(excel_clean($row[18] ?? null)),
                ':exercise_time' => $exerciseTime,
                ':plan_id' => $fee > 0 ? ($priceMap[$priceKey] ?: null) : null,
                ':level' => null,
            ]);
        }

        if ($paid != 0.0) {
            $stats['transactions']++;
            if ($apply) {
                $insertTransaction->execute([
                    ':payment_method' => $paymentMethod,
                    ':amount' => $paid,
                    ':receipt_id' => $receiptId,
                    ':created_by' => $creatorId,
                    ':created_at' => $createdAt,
                    ':attachment' => $oldEvidenceByReceipt[$receiptId] ?? null,
                    ':notes' => $notes,
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
