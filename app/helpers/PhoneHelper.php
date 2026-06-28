<?php
// app/Helpers/PhoneHelper.php

class PhoneHelper
{
    /**
     * Country code → normalisation rules.
     * Add more entries as needed.
     */
    private static array $rules = [
        '+20'  => ['strip_prefix' => ['0'], 'local_digits' => 10],  // Egypt  01xxxxxxxxx → 1xxxxxxxxx
        '+966' => ['strip_prefix' => ['0'], 'local_digits' => 9],   // KSA    05xxxxxxxx  → 5xxxxxxxx
    ];

    // ─────────────────────────────────────────────────────────────────────
    // normalize()
    //
    // Accepts any of these formats and returns a canonical E.164 string:
    //   +201012345678
    //   00201012345678
    //   201012345678
    //   01012345678   (Egyptian local — needs countryCode hint)
    //   1012345678    (Egyptian local without leading 0 — needs countryCode hint)
    //
    // Returns null when the input is clearly garbage.
    // ─────────────────────────────────────────────────────────────────────
    public static function normalize(string $raw, string $countryCode = ''): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if (!$digits) return null;

        // Already contains a full country code prefix?
        foreach (array_keys(self::$rules) as $cc) {
            $ccDigits = ltrim($cc, '+');
            if (str_starts_with($digits, $ccDigits)) {
                return '+' . $digits;
            }
            // 00 prefix variant
            if (str_starts_with($digits, '00' . $ccDigits)) {
                return '+' . substr($digits, 2);
            }
        }

        // Use the supplied country code hint
        if ($countryCode) {
            $cc     = ltrim($countryCode, '+');
            $rule   = self::$rules['+' . $cc] ?? null;
            $local  = $digits;

            // Strip leading 0 if present
            if ($rule && str_starts_with($local, '0')) {
                $local = substr($local, 1);
            }

            return '+' . $cc . $local;
        }

        // Fallback: return as-is with + prefix
        return '+' . $digits;
    }

    // ─────────────────────────────────────────────────────────────────────
    // searchVariants()
    //
    // Returns all plausible DB values for a raw search string so that
    // WHERE phone IN (v1, v2, v3 …) or LIKE patterns catch every format
    // stored historically.
    // ─────────────────────────────────────────────────────────────────────
    public static function searchVariants(string $raw): array
    {
        $digits = preg_replace('/\D/', '', $raw);
        if (!$digits) return [];

        $variants = [];

        foreach (self::$rules as $cc => $rule) {
            $ccDigits = ltrim($cc, '+');

            // Strip country code prefix → get local part
            $local = $digits;
            if (str_starts_with($local, $ccDigits))        $local = substr($local, strlen($ccDigits));
            elseif (str_starts_with($local, '00' . $ccDigits)) $local = substr($local, 2 + strlen($ccDigits));

            // Ensure local starts correctly (some systems strip the leading 0)
            $localWith0    = str_starts_with($local, '0') ? $local : '0' . $local;
            $localWithout0 = ltrim($local, '0');

            // Build all stored-format candidates
            $variants[] = '+' . $ccDigits . $localWithout0;   // +201012345678
            $variants[] = $ccDigits . $localWithout0;          // 201012345678
            $variants[] = $localWith0;                         // 01012345678
            $variants[] = $localWithout0;                      // 1012345678
            $variants[] = '00' . $ccDigits . $localWithout0;   // 00201012345678
        }

        // Add the raw digits themselves as a final catch-all
        $variants[] = $digits;
        $variants[] = '+' . $digits;

        return array_unique(array_filter($variants));
    }

    // ─────────────────────────────────────────────────────────────────────
    // buildSearchCondition()
    //
    // Returns [sql_fragment, params_array] for use in a PDO query.
    // Example:
    //   [$sql, $params] = PhoneHelper::buildSearchCondition('01012345678');
    //   $stmt = $db->prepare("SELECT * FROM clients WHERE {$sql}");
    //   $stmt->execute($params);
    // ─────────────────────────────────────────────────────────────────────
    public static function buildSearchCondition(string $raw): array
    {
        $variants  = self::searchVariants($raw);
        if (!$variants) return ['1=0', []];

        $placeholders = implode(',', array_fill(0, count($variants), '?'));
        // Also add a LIKE clause for partial suffix matching
        $digits = preg_replace('/\D/', '', $raw);

        $sql    = "(phone IN ({$placeholders}) OR phone LIKE ?)";
        $params = array_merge($variants, ['%' . $digits]);

        return [$sql, $params];
    }
}