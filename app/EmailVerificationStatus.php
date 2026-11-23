<?php

namespace App;

enum EmailVerificationStatus
{
    case Verified;
    case Invalid;
    case Expired;
    case Locked;

    public function message(): string
    {
        return match ($this) {
            self::Verified => 'Email address verified.',
            self::Invalid => 'The verification code is incorrect.',
            self::Expired => 'The verification code has expired.',
            self::Locked => 'The verification code is no longer valid. Request a new one.',
        };
    }
}
