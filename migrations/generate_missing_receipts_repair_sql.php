<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$oldDump = $root . '/old_db.sql';
$migratedDump = $root . '/swimming_academy_migrated.sql';
$outSql = $root . '/migrations/repair_missing_receipts_from_old_db.sql';
$outCsv = $root . '/migrations/missing_receipts_from_old_db_report.csv';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--old-dump=')) {
        $oldDump = substr($arg, 11);
    } elseif (str_starts_with($arg, '--migrated-dump=')) {
        $migratedDump = substr($arg, 16);
    } elseif (str_starts_with($arg, '--out-sql=')) {
        $outSql = substr($arg, 10);
    } elseif (str_starts_with($arg, '--out-csv=')) {
        $outCsv = substr($arg, 10);
    }
}

function read_dump_utf8(string $path): string
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("Cannot read {$path}");
    }

    if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
        $converted = iconv('UTF-16', 'UTF-8//IGNORE', $raw);
        if ($converted === false) {
            throw new RuntimeException("Failed to convert {$path} to UTF-8");
        }

        return $converted;
    }

    return $raw;
}

function each_value_tuple(string $values, callable $callback): void
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

function each_insert_row(string $sql, string $table, callable $callback): void
{
    $pattern = '/INSERT\s+INTO\s+`' . preg_quote($table, '/') . '`\s+VALUES\s*/i';
    $offset = 0;

    while (preg_match($pattern, $sql, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $start = $match[0][1] + strlen($match[0][0]);
        $end = strpos($sql, ';', $start);
        if ($end === false) {
            throw new RuntimeException("Unterminated {$table} INSERT");
        }

        each_value_tuple(substr($sql, $start, $end - $start), $callback);
        $offset = $end + 1;
    }
}

function clean_value(?string $value): ?string
{
    if ($value === null || strtoupper($value) === 'NULL' || $value === '') {
        return null;
    }

    return $value;
}

function date_part(?string $value): ?string
{
    $value = clean_value($value);
    return $value === null ? null : substr($value, 0, 10);
}

function norm_key(?string $value): string
{
    return mb_strtolower(trim((string) $value));
}

function sql_quote(?string $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    return "'" . str_replace(["\\", "'", "\0"], ["\\\\", "\\'", ''], $value) . "'";
}

function sql_int(?int $value): string
{
    return $value === null ? 'NULL' : (string) $value;
}

function sql_decimal(?string $value): string
{
    $value = clean_value($value);
    return $value === null ? 'NULL' : (string) (float) $value;
}

function map_role(?string $role): string
{
    return match ($role) {
        'superAdmin', 'admin' => 'admin',
        'areaManager' => 'area_manager',
        'manager' => 'branch_manager',
        'customerService' => 'customer_service',
        default => 'customer_service',
    };
}

function receipt_type(?string $type, ?string $renewType): ?string
{
    if ($type === 'fresh') {
        return 'new';
    }
    if ($type === 'renew') {
        return 'renew';
    }

    return clean_value($renewType) ?? clean_value($type);
}

function sanitize_attachment(?string $value): ?string
{
    $value = clean_value($value);
    if ($value === null) {
        return null;
    }

    $trimmed = trim($value);
    if ($trimmed === '[]' || $trimmed === '[null]' || strtolower($trimmed) === 'null') {
        return null;
    }

    if (str_starts_with($trimmed, '[')) {
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (is_string($item) && trim($item) !== '') {
                    return strip_upload_prefix($item);
                }
            }

            return null;
        }
    }

    return strip_upload_prefix($trimmed);
}

function strip_upload_prefix(string $path): ?string
{
    $path = trim($path, " \t\n\r\0\x0B\"'");
    $path = preg_replace('~^(uploads[\\\\/])+~i', '', $path);

    return $path === '' || strtolower($path) === 'null' ? null : $path;
}

function max_numeric_id(array $ids): int
{
    $max = 0;
    foreach ($ids as $id => $_) {
        if (preg_match('/^\d+$/', (string) $id)) {
            $max = max($max, (int) $id);
        }
    }

    return $max;
}

function audit_rows_from_history(array $receiptRow, int $receiptId, int $fallbackUserId): array
{
    $history = clean_value($receiptRow[27] ?? null);
    if ($history === null || $history === '[]') {
        return [];
    }

    $decoded = json_decode($history, true);
    if (!is_array($decoded)) {
        return [];
    }

    $rows = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];
        $changedBy = isset($changes['editorId']) && is_numeric($changes['editorId']) ? (int) $changes['editorId'] : $fallbackUserId;
        $timestamp = (string) ($entry['timestamp'] ?? $changes['edit_time'] ?? ($receiptRow[23] ?? $receiptRow[16] ?? ''));
        $changedAt = normalize_datetime($timestamp) ?? (date_part($receiptRow[23] ?? null) . ' 00:00:00');
        $summary = clean_value((string) ($changes['summary'] ?? ''));
        if ($summary === null) {
            $summary = json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $rows[] = [
            'receipt_id' => $receiptId,
            'changed_by' => $changedBy,
            'changed_at' => $changedAt,
            'role' => map_role(clean_value($receiptRow[25] ?? null)),
            'field_name' => 'receipt_edited',
            'old_value' => null,
            'new_value' => $summary,
        ];
    }

    return $rows;
}

function normalize_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'd/m/Y H:i:s', 'd-m-Y H:i:s', 'Y-m-d'];
    foreach ($formats as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date instanceof DateTimeImmutable) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    $time = strtotime($value);
    return $time === false ? null : date('Y-m-d H:i:s', $time);
}

echo "Reading dumps...\n";
$oldSql = read_dump_utf8($oldDump);
$migratedSql = read_dump_utf8($migratedDump);

$oldReceipts = [];
each_insert_row($oldSql, 'receipts', function (array $row) use (&$oldReceipts): void {
    $id = (int) ($row[0] ?? 0);
    if ($id > 0) {
        $oldReceipts[$id] = $row;
    }
});

$existingReceipts = [];
each_insert_row($migratedSql, 'receipts', function (array $row) use (&$existingReceipts): void {
    $id = (int) ($row[0] ?? 0);
    if ($id > 0) {
        $existingReceipts[$id] = true;
    }
});

$existingUsers = [];
each_insert_row($migratedSql, 'users', function (array $row) use (&$existingUsers): void {
    $id = (int) ($row[0] ?? 0);
    if ($id > 0) {
        $existingUsers[$id] = true;
    }
});

$existingClients = [];
$existingClientPhones = [];
each_insert_row($migratedSql, 'clients', function (array $row) use (&$existingClients, &$existingClientPhones): void {
    $id = (int) ($row[0] ?? 0);
    if ($id > 0) {
        $existingClients[$id] = true;
        $phone = clean_value($row[2] ?? null);
        if ($phone !== null) {
            $existingClientPhones[$phone] = $id;
        }
    }
});

$branchMap = [];
$existingBranchIds = [];
each_insert_row($migratedSql, 'branches', function (array $row) use (&$branchMap, &$existingBranchIds): void {
    $id = (int) ($row[0] ?? 0);
    $name = clean_value($row[1] ?? null);
    if ($id > 0) {
        $existingBranchIds[$id] = true;
    }
    if ($id > 0 && $name !== null) {
        $branchMap[norm_key($name)] = $id;
    }
});

$captainMap = [];
$existingCaptainIds = [];
each_insert_row($migratedSql, 'captains', function (array $row) use (&$captainMap, &$existingCaptainIds): void {
    $id = clean_value($row[0] ?? null);
    $name = clean_value($row[1] ?? null);
    if ($id !== null) {
        $existingCaptainIds[$id] = true;
    }
    if ($id !== null && $name !== null) {
        $captainMap[norm_key($name)] = $id;
    }
});

$missingIds = array_diff_key($oldReceipts, $existingReceipts);
ksort($missingIds);
$extraMigratedIds = array_diff_key($existingReceipts, $oldReceipts);
ksort($extraMigratedIds);

$nextBranchId = max(array_keys($existingBranchIds) ?: [0]) + 1;
$nextCaptainId = max_numeric_id($existingCaptainIds) + 1;

$insertUsers = [];
$insertClients = [];
$insertBranches = [];
$insertCaptains = [];
$insertReceipts = [];
$insertTransactions = [];
$insertAudits = [];
$reportRows = [];

foreach ($missingIds as $receiptId => $row) {
    $clientId = (int) ($row[1] ?? 0);
    $creatorId = (int) ($row[6] ?? 0);
    $createdAt = date_part($row[16] ?? null);

    if ($clientId <= 0 || $creatorId <= 0 || $createdAt === null) {
        $reportRows[] = [$receiptId, 'skipped_invalid', clean_value($row[2] ?? null), clean_value($row[16] ?? null), ''];
        continue;
    }

    if (!isset($existingUsers[$creatorId]) && !isset($insertUsers[$creatorId])) {
        $insertUsers[$creatorId] = [
            $creatorId,
            clean_value($row[5] ?? null) ?? "Migrated User {$creatorId}",
            "migrated.user.{$creatorId}@gmail.com",
            null,
            map_role(clean_value($row[25] ?? null)),
            null,
            1,
            $createdAt,
            null,
            1,
        ];
    }

    if (!isset($existingClients[$clientId]) && !isset($insertClients[$clientId])) {
        $phone = clean_value($row[3] ?? null) ?? "missing-phone-{$clientId}";
        if (isset($existingClientPhones[$phone]) && $existingClientPhones[$phone] !== $clientId) {
            $phone .= "#missing-client-{$clientId}";
        }
        $existingClientPhones[$phone] = $clientId;

        $insertClients[$clientId] = [
            $clientId,
            clean_value($row[2] ?? null) ?? "Migrated Client {$clientId}",
            $phone,
            $creatorId,
            clean_value($row[21] ?? null) !== null ? (int) $row[21] : null,
            null,
            $createdAt,
            "missing.client.{$clientId}@migration.local",
            1,
        ];
    }

    $branchName = clean_value($row[4] ?? null) ?? 'Unknown Branch';
    $branchKey = norm_key($branchName);
    if (!isset($branchMap[$branchKey])) {
        $branchMap[$branchKey] = $nextBranchId++;
        $insertBranches[$branchMap[$branchKey]] = [
            $branchMap[$branchKey],
            $branchName,
            $createdAt,
            1,
            null,
        ];
    }

    $captainName = clean_value($row[7] ?? null) ?? 'Unknown Captain';
    $captainKey = norm_key($captainName);
    if (!isset($captainMap[$captainKey])) {
        $captainMap[$captainKey] = (string) $nextCaptainId++;
        $insertCaptains[$captainMap[$captainKey]] = [
            $captainMap[$captainKey],
            $captainName,
            null,
            $createdAt,
            null,
            1,
        ];
    }

    $attachment = sanitize_attachment($row[18] ?? null);
    $insertReceipts[$receiptId] = [
        $receiptId,
        (string) $receiptId,
        $clientId,
        $creatorId,
        $captainMap[$captainKey],
        $branchMap[$branchKey],
        clean_value($row[8] ?? null),
        clean_value($row[9] ?? null),
        clean_value($row[10] ?? null),
        $createdAt,
        receipt_type(clean_value($row[20] ?? null), clean_value($row[22] ?? null)),
        clean_value($row[15] ?? null) ?? 'not_completed',
        clean_value($row[17] ?? null),
        null,
        clean_value($row[26] ?? null) !== null ? (int) $row[26] : null,
        $attachment,
        0,
    ];

    $paidAmount = clean_value($row[12] ?? null);
    if ($paidAmount !== null && (float) $paidAmount != 0.0) {
        $insertTransactions[] = [
            clean_value($row[11] ?? null),
            $paidAmount,
            $receiptId,
            $creatorId,
            $createdAt,
            $attachment,
            clean_value($row[19] ?? null) ?? 'Migrated from old receipt payment',
            'payment',
        ];
    }

    foreach (audit_rows_from_history($row, $receiptId, $creatorId) as $auditRow) {
        $insertAudits[] = $auditRow;
    }

    $reportRows[] = [$receiptId, 'included', clean_value($row[2] ?? null), $createdAt, $attachment ?? ''];
}

function values_sql(array $row): string
{
    $parts = [];
    foreach ($row as $value) {
        if (is_int($value)) {
            $parts[] = (string) $value;
        } elseif (is_float($value)) {
            $parts[] = (string) $value;
        } else {
            $parts[] = sql_quote($value);
        }
    }

    return '(' . implode(',', $parts) . ')';
}

function append_insert(array &$lines, string $table, array $columns, array $rows, bool $ignore = true): void
{
    if (!$rows) {
        return;
    }

    $verb = $ignore ? 'INSERT IGNORE INTO' : 'INSERT INTO';
    $lines[] = "{$verb} `{$table}` (`" . implode('`,`', $columns) . '`) VALUES';
    $last = count($rows) - 1;
    foreach (array_values($rows) as $i => $row) {
        $lines[] = values_sql($row) . ($i === $last ? ';' : ',');
    }
    $lines[] = '';
}

$lines = [
    '-- Repair missing receipts from old_db.sql into swimming_academy_migrated.sql',
    '-- Generated by migrations/generate_missing_receipts_repair_sql.php',
    '-- Attachment cleanup: removes leading uploads\\ and uploads/ from receipts.pdf_path and transactions.attachment.',
    'SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS;',
    'SET FOREIGN_KEY_CHECKS=0;',
    'START TRANSACTION;',
    '',
];

append_insert($lines, 'users', ['id', 'username', 'email', 'password_hash', 'role', 'phone', 'visible', 'created_at', 'last_login', 'is_active'], $insertUsers);
append_insert($lines, 'clients', ['id', 'client_name', 'phone', 'created_by', 'age', 'gender', 'created_at', 'email', 'visible'], $insertClients);
append_insert($lines, 'branches', ['id', 'branch_name', 'created_at', 'visible', 'country_id'], $insertBranches);
append_insert($lines, 'captains', ['id', 'captain_name', 'phone_number', 'created_at', 'created_by', 'visible'], $insertCaptains);
append_insert($lines, 'receipts', ['id', 'receipt_ref', 'client_id', 'creator_id', 'captain_id', 'branch_id', 'first_session', 'last_session', 'renewal_session', 'created_at', 'renewal_type', 'receipt_status', 'exercise_time', 'plan_id', 'level', 'pdf_path', 'is_refunded'], $insertReceipts);
append_insert($lines, 'transactions', ['payment_method', 'amount', 'receipt_id', 'created_by', 'created_at', 'attachment', 'notes', 'type'], $insertTransactions, false);

$auditValueRows = [];
foreach ($insertAudits as $audit) {
    $auditValueRows[] = [
        $audit['receipt_id'],
        $audit['changed_by'],
        $audit['changed_at'],
        $audit['role'],
        $audit['field_name'],
        $audit['old_value'],
        $audit['new_value'],
    ];
}
append_insert($lines, 'receipt_audit_log', ['receipt_id', 'changed_by', 'changed_at', 'role', 'field_name', 'old_value', 'new_value'], $auditValueRows, false);

$lines[] = 'COMMIT;';
$lines[] = 'SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;';
$lines[] = '';

file_put_contents($outSql, implode(PHP_EOL, $lines));

$csv = fopen($outCsv, 'wb');
if ($csv === false) {
    throw new RuntimeException("Cannot write {$outCsv}");
}
fputcsv($csv, ['receipt_id', 'status', 'client_name', 'created_at', 'sanitized_attachment']);
foreach ($reportRows as $row) {
    fputcsv($csv, $row);
}
fclose($csv);

echo 'Old receipts: ' . count($oldReceipts) . PHP_EOL;
echo 'Migrated receipts: ' . count($existingReceipts) . PHP_EOL;
echo 'Missing receipt IDs: ' . count($missingIds) . PHP_EOL;
echo 'Migrated-only receipt IDs: ' . count($extraMigratedIds) . PHP_EOL;
if ($extraMigratedIds) {
    echo 'Migrated-only first IDs: ' . implode(', ', array_slice(array_keys($extraMigratedIds), 0, 20)) . PHP_EOL;
}
echo 'Receipts to insert: ' . count($insertReceipts) . PHP_EOL;
echo 'Transactions to insert: ' . count($insertTransactions) . PHP_EOL;
echo 'Audit rows to insert: ' . count($insertAudits) . PHP_EOL;
echo 'Users to insert: ' . count($insertUsers) . PHP_EOL;
echo 'Clients to insert: ' . count($insertClients) . PHP_EOL;
echo 'Branches to insert: ' . count($insertBranches) . PHP_EOL;
echo 'Captains to insert: ' . count($insertCaptains) . PHP_EOL;
echo "Wrote SQL: {$outSql}\n";
echo "Wrote report: {$outCsv}\n";
