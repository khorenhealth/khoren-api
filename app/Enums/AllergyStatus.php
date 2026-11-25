<?php

namespace App\Enums;

enum AllergyStatus: string
{
    case KnownAllergies = 'known_allergies';
    case NoneKnown = 'none_known';
    case Unknown = 'unknown';

    /**
     * Accept the stored value or the label shown on the allergy-status buttons.
     */
    public static function parse(string $value): ?self
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(['-', ' '], '_', $normalized);
        $normalized = preg_replace('/_+/', '_', $normalized) ?? $normalized;
        $normalized = trim($normalized, '_');

        return self::tryFrom($normalized);
    }
}
