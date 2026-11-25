<?php

namespace App\Enums;

enum BloodGroup: string
{
    case APositive = 'a_positive';
    case ANegative = 'a_negative';
    case BPositive = 'b_positive';
    case BNegative = 'b_negative';
    case AbPositive = 'ab_positive';
    case AbNegative = 'ab_negative';
    case OPositive = 'o_positive';
    case ONegative = 'o_negative';
    case Unknown = 'unknown';

    /**
     * Accept the stored value or a label from the blood-group list.
     */
    public static function parse(string $value): ?self
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(["\u{2212}", "\u{2013}", "\u{2014}"], '-', $normalized);
        $normalized = str_replace(['+', '-'], ['_positive', '_negative'], $normalized);
        $normalized = str_replace(' ', '_', $normalized);
        $normalized = preg_replace('/_+/', '_', $normalized) ?? $normalized;
        $normalized = trim($normalized, '_');

        return self::tryFrom($normalized);
    }
}
