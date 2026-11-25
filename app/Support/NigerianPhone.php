<?php

namespace App\Support;

final class NigerianPhone
{
    public const string PATTERN = '/^\+234[1-9]\d{9}$/';

    /**
     * Store a Nigerian mobile number as +234 and 10 digits, without the trunk 0.
     */
    public static function normalize(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            $national = substr($digits, 3);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $national = substr($digits, 1);
        } elseif (strlen($digits) === 10) {
            $national = $digits;
        } else {
            return trim($value);
        }

        if (preg_match('/^[1-9]\d{9}$/', $national) !== 1) {
            return trim($value);
        }

        return '+234'.$national;
    }
}
