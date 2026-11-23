<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Code length
    |--------------------------------------------------------------------------
    |
    | Number of digits in the verification code shown on the email screen.
    |
    */

    'digits' => 6,

    /*
    |--------------------------------------------------------------------------
    | Code lifetime
    |--------------------------------------------------------------------------
    |
    | Minutes before a sent code can no longer be used.
    |
    */

    'expire_minutes' => 10,

    /*
    |--------------------------------------------------------------------------
    | Resend cooldown
    |--------------------------------------------------------------------------
    |
    | Seconds the client should wait before requesting another code. The
    | verification screen counts down to this instant.
    |
    */

    'resend_seconds' => 60,

    /*
    |--------------------------------------------------------------------------
    | Attempt limit
    |--------------------------------------------------------------------------
    |
    | Wrong codes accepted for the current code before it is discarded.
    |
    */

    'max_attempts' => 5,

];
