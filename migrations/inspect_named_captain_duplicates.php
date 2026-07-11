<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/database.php';

$names = [
    'يحيى سعيد',
    'مريم',
    'محمود هادي',
    'محمود محمد عبد السلام محمد',
    'محمود سامي',
    'محمد عبد القادر',
    'محمد عاف',
    'محمد عاطف',
    'محمد عاطغ',
    'محمد عادل',
    'محمد سيد',
    'محمد سعد',
    'محمد رمضان',
    'محمد جمال',
    'محمد احمد',
];

function loose_key(string $value): string {
    $value = trim(mb_strtolower($value, 'UTF-8'));
    return str_replace(
        [' ', "\t", "\r", "\n", 'ـ', 'أ', 'إ', 'آ', 'ة', 'ى', 'ؤ', 'ئ'],
        ['', '', '', '', '', 'ا', 'ا', 'ا', 'ه', 'ي', 'و', 'ي'],
        $value
    );
}

$db = get_db();
$captains = $db->query("
    SELECT c.id,
           c.captain_name,
           c.phone_number,
           COALESCE(r.receipts_count, 0) AS receipts_count
    FROM captains c
    LEFT JOIN (
        SELECT captain_id, COUNT(*) AS receipts_count
        FROM receipts
        GROUP BY captain_id
    ) r ON r.captain_id = c.id
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($names as $name) {
    $needle = loose_key($name);
    $matches = array_values(array_filter($captains, static function (array $captain) use ($needle): bool {
        $key = loose_key((string) $captain['captain_name']);
        return $key === $needle || str_contains($key, $needle) || str_contains($needle, $key);
    }));

    usort($matches, static fn(array $a, array $b): int => ((int) $b['receipts_count'] <=> (int) $a['receipts_count']) ?: strcmp((string) $a['id'], (string) $b['id']));

    echo "== {$name} ==" . PHP_EOL;
    foreach ($matches as $match) {
        echo $match['id'] . "\t"
            . $match['captain_name'] . "\tphone="
            . ($match['phone_number'] ?? '') . "\treceipts="
            . $match['receipts_count'] . PHP_EOL;
    }
}
