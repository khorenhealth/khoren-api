<?php

namespace App\Services;

use App\EmailVerificationStatus;
use App\Mail\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class EmailVerificationService
{
    /**
     * Store a new code and email it to the user's current address.
     *
     * Delivery waits until the surrounding transaction commits. There is no
     * queue worker in this app, so the message is sent in the request.
     */
    public function send(User $user): void
    {
        $code = $this->generateCode();

        $user->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes($this->expireMinutes()),
            'email_verification_sent_at' => now(),
            'email_verification_attempts' => 0,
        ])->save();

        DB::afterCommit(function () use ($user, $code): void {
            Mail::to($user)->send(new EmailVerificationCode($code));
        });
    }

    /**
     * Payload for the verification screen.
     *
     * @return array{email: string, expires_at: string|null, resend_available_at: string|null}
     */
    public function pendingPayload(User $user): array
    {
        return [
            'email' => $user->email,
            'expires_at' => $user->email_verification_expires_at?->toISOString(),
            'resend_available_at' => $this->resendAvailableAt($user)?->toISOString(),
        ];
    }

    public function isCoolingDown(User $user): bool
    {
        $availableAt = $this->resendAvailableAt($user);

        return $availableAt !== null && $availableAt->isFuture();
    }

    public function resendAvailableAt(User $user): ?Carbon
    {
        return $user->email_verification_sent_at?->copy()->addSeconds($this->resendSeconds());
    }

    public function verify(User $user, string $code): EmailVerificationStatus
    {
        if ($user->email_verified_at !== null) {
            return EmailVerificationStatus::Invalid;
        }

        if ($user->email_verification_code === null || $user->email_verification_expires_at === null) {
            return EmailVerificationStatus::Invalid;
        }

        if ($user->email_verification_expires_at->isPast()) {
            $this->clear($user);

            return EmailVerificationStatus::Expired;
        }

        if (! Hash::check($code, $user->email_verification_code)) {
            return $this->recordFailedAttempt($user);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
            'email_verification_sent_at' => null,
            'email_verification_attempts' => 0,
        ])->save();

        return EmailVerificationStatus::Verified;
    }

    private function recordFailedAttempt(User $user): EmailVerificationStatus
    {
        $attempts = (int) $user->email_verification_attempts + 1;

        if ($attempts >= $this->maxAttempts()) {
            $user->forceFill([
                'email_verification_code' => null,
                'email_verification_expires_at' => null,
                'email_verification_attempts' => 0,
            ])->save();

            return EmailVerificationStatus::Locked;
        }

        $user->forceFill([
            'email_verification_attempts' => $attempts,
        ])->save();

        return EmailVerificationStatus::Invalid;
    }

    private function clear(User $user): void
    {
        $user->forceFill([
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
            'email_verification_sent_at' => null,
            'email_verification_attempts' => 0,
        ])->save();
    }

    private function generateCode(): string
    {
        $digits = max(1, (int) config('email-verification.digits'));
        $maximum = (10 ** $digits) - 1;

        return str_pad((string) random_int(0, $maximum), $digits, '0', STR_PAD_LEFT);
    }

    private function expireMinutes(): int
    {
        return (int) config('email-verification.expire_minutes');
    }

    private function resendSeconds(): int
    {
        return (int) config('email-verification.resend_seconds');
    }

    private function maxAttempts(): int
    {
        return (int) config('email-verification.max_attempts');
    }
}
