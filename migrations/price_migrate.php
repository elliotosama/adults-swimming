<?php
/**
 * Detects and fixes CP437-corrupted Arabic values in captains.captain_name.
 *
 * Usage:
 *   php fix_captains_db.php            -> dry run (preview only, no writes)
 *   php fix_captains_db.php --apply    -> actually runs the UPDATE statements
 */

// ---- adjust to your actual DB config ----
$host = 'localhost';
$db   = 'swimming_academy_migrated';
$user = 'osama';
$pass = 'osamaisthebest';
$charset = 'utf8mb4';
// ------------------------------------------
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
 
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}
 
$dryRun = !in_array('--apply', $argv, true);
 
// Characters that ONLY appear in the CP437-corrupted mojibake, never in clean Arabic.
const CORRUPTION_MARKERS = ['┘', '╪', '╡'];
 
function isCorrupted(string $value): bool
{
    foreach (CORRUPTION_MARKERS as $marker) {
        if (mb_strpos($value, $marker) !== false) {
            return true;
        }
    }
    return false;
}
 
/**
 * Attempts to fix a CP437-corrupted string. Some stored values are genuinely
 * truncated mid-character (the last 1-3 bytes are missing, not just corrupted),
 * so if the full conversion fails, this trims back up to 3 trailing bytes to
 * salvage everything except the unrecoverable last character.
 *
 * Returns [fixedString|null, wasTruncated bool]
 */
function fixCorruptedText(string $value): array
{
    $bytes = @iconv('UTF-8', 'CP437', $value);
    if ($bytes === false) {
        return [null, false];
    }
 
    for ($i = 0; $i <= 3; $i++) {
        $candidate = ($i === 0) ? $bytes : substr($bytes, 0, -$i);
        if ($candidate !== '' && mb_check_encoding($candidate, 'UTF-8')) {
            return [$candidate, $i > 0];
        }
    }
 
    return [null, false];
}
 
// Fetch only the candidate rows using the same LIKE pattern as the SQL check
$stmt = $pdo->query("
    SELECT id, captain_name
    FROM captains
    WHERE captain_name LIKE '%┘%'
       OR captain_name LIKE '%╪%'
       OR captain_name LIKE '%╡%'
");
$rows = $stmt->fetchAll();
 
echo "Found " . count($rows) . " candidate corrupted rows.\n\n";
 
$updateStmt = $pdo->prepare("UPDATE captains SET captain_name = :new_name WHERE id = :id");
 
$fixedCount = 0;
$skippedCount = 0;
 
foreach ($rows as $row) {
    $original = $row['captain_name'];
 
    if (!isCorrupted($original)) {
        continue; // shouldn't happen given the WHERE clause, but just in case
    }
 
    [$fixed, $wasTruncated] = fixCorruptedText($original);
 
    if ($fixed === null || $fixed === $original) {
        echo "SKIP (couldn't fix) - ID {$row['id']}: $original\n";
        $skippedCount++;
        continue;
    }
 
    echo ($dryRun ? "[DRY RUN] " : "[APPLYING] ") . "ID {$row['id']}" . ($wasTruncated ? " (last character lost - data was truncated in storage)" : "") . ":\n";
    echo "  before: $original\n";
    echo "  after:  $fixed\n\n";
 
    if (!$dryRun) {
        $updateStmt->execute([
            'new_name' => $fixed,
            'id'       => $row['id'],
        ]);
    }
 
    $fixedCount++;
}
 
echo "----\n";
echo ($dryRun ? "Would fix" : "Fixed") . ": $fixedCount rows\n";
echo "Skipped (couldn't auto-fix): $skippedCount rows\n";
 
if ($dryRun) {
    echo "\nThis was a DRY RUN - no changes were made.\n";
    echo "Review the output above, then run again with --apply to commit the changes.\n";
}