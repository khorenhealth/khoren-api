<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EmailVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeUnverifiedEmailRequest;
use App\Http\Requests\Auth\ResendEmailVerificationRequest;
use App\Http\Requests\Auth\VerifyEmailCodeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\EmailVerificationService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

#[Group('Authentication', weight: 1)]
class EmailVerificationController extends Controller
{
    private const GENERIC_RESEND_MESSAGE = 'If an unverified account exists for that email, a new code is on the way.';

    public function __construct(private EmailVerificationService $codes) {}

    /**
     * Accept a sign-up code and return an API token.
     */
    #[Endpoint(
        title: 'Verify email',
        description: 'Checks the 6-digit code sent at sign-up. A correct code marks the email verified and returns a Sanctum token.',
    )]
    #[Response(
        200,
        description: 'Email verified. Store the Sanctum token as `Authorization: Bearer {token}`.',
        type: 'array{token: string, user: App\Http\Resources\UserResource}',
    )]
    #[Response(422, description: 'The code is missing, incorrect, expired, or no longer valid.')]
    public function verify(VerifyEmailCodeRequest $request): JsonResponse
    {
        $user = $this->unverifiedUser($request->string('email')->toString());
        $status = $user instanceof User
            ? $this->codes->verify($user, $request->string('code')->toString())
            : EmailVerificationStatus::Invalid;

        if ($status !== EmailVerificationStatus::Verified || ! $user instanceof User) {
            throw ValidationException::withMessages([
                'code' => [$status->message()],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('auth')->plainTextToken,
            'user' => UserResource::make($user),
        ]);
    }

    /**
     * Send a fresh code when the resend countdown has finished.
     */
    #[Endpoint(
        title: 'Resend verification code',
        description: 'Emails a new code for an unverified account. Unknown and already-verified addresses get the same response and no email.',
    )]
    #[Response(
        200,
        description: 'A code was sent, or the address is unknown or already verified.',
        type: 'array{email?: string, expires_at?: string, resend_available_at?: string, message?: string}',
    )]
    #[Response(429, description: 'The resend countdown has not finished. `resend_available_at` is when the next code can be requested.')]
    public function resend(ResendEmailVerificationRequest $request): JsonResponse
    {
        $user = $this->unverifiedUser($request->string('email')->toString());

        if (! $user instanceof User) {
            return response()->json([
                'message' => self::GENERIC_RESEND_MESSAGE,
            ]);
        }

        if ($this->codes->isCoolingDown($user)) {
            return response()->json([
                'message' => 'Please wait before requesting a new code.',
                'resend_available_at' => $this->codes->resendAvailableAt($user)?->toISOString(),
            ], 429);
        }

        $this->codes->send($user);

        return response()->json($this->codes->pendingPayload($user));
    }

    /**
     * Point an unverified account at a different inbox and send a new code.
     */
    #[Endpoint(
        title: 'Change unverified email',
        description: 'Requires the account password. Updates the email only while it is still unverified, then sends a new code.',
    )]
    #[Response(
        200,
        description: 'Email updated and a new code sent.',
        type: 'array{email: string, expires_at: string, resend_available_at: string}',
    )]
    #[Response(422, description: 'The password is wrong, the address is taken, or the email is already verified.')]
    public function change(ChangeUnverifiedEmailRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if (! $user instanceof User || $user->password === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'email' => ['Email address is already verified.'],
            ]);
        }

        DB::transaction(function () use ($request, $user): void {
            $user->forceFill([
                'email' => $request->string('new_email')->toString(),
            ])->save();

            $this->codes->send($user);
        });

        return response()->json($this->codes->pendingPayload($user));
    }

    private function unverifiedUser(string $email): ?User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User || $user->email_verified_at !== null) {
            return null;
        }

        return $user;
    }
}
