<?php

namespace App\Enums;

enum Gender: string
{
    case Female = 'female';
    case Male = 'male';
    case NonBinary = 'non_binary';
    case SelfDescribe = 'self_describe';
    case PreferNotToSay = 'prefer_not_to_say';

    /**
     * Accept the stored value or the label shown in the personal-information form.
     */
    public static function parse(string $value): ?self
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(['-', ' '], '_', $normalized);

        if ($normalized === 'nonbinary') {
            $normalized = 'non_binary';
        }

        return self::tryFrom($normalized);
    }
}
