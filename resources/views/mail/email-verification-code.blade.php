<x-mail::message>
# Email verification

Your verification code is **{{ $code }}**.

It expires in {{ config('email-verification.expire_minutes') }} minutes.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
