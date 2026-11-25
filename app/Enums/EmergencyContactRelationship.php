<?php

namespace App\Enums;

enum EmergencyContactRelationship: string
{
    case Spouse = 'spouse';
    case Parent = 'parent';
    case Sibling = 'sibling';
    case Friend = 'friend';
    case Carer = 'carer';
    case Other = 'other';

    /**
     * Accept the stored value or the label shown in the relationship list.
     */
    public static function parse(string $value): ?self
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(['-', ' '], '_', $normalized);

        return self::tryFrom($normalized);
    }
}
