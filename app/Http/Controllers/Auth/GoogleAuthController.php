<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

#[Group('Authentication', weight: 1)]
class GoogleAuthController extends Controller
{
    /**
     * Send the browser to Google.
     */
    #[Endpoint(
        title: 'Redirect to Google',
        description: 'Starts the stateless Google OAuth flow. Open this URL in a browser or webview; it redirects to Google.',
    )]
    #[Response(302, description: 'Redirect to Google authorization.', mediaType: 'text/html')]
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Create or reuse the user for this Google account and issue an API token.
     */
    #[Endpoint(
        title: 'Google OAuth callback',
        description: 'Google redirects here after consent. Creates or links a user, then returns a Sanctum personal access token.',
    )]
    #[Response(
        200,
        description: 'Authenticated. Store the token and send it as `Authorization: Bearer {token}`.',
        type: 'array{token: string, user: App\Http\Resources\UserResource}',
        examples: [
            [
                'token' => '1|plainTextSanctumToken',
                'user' => [
                    'id' => 1,
                    'name' => 'Ada Lovelace',
                    'email' => 'ada@example.com',
                    'email_verified_at' => '2026-11-17T12:00:00.000000Z',
                ],
            ],
        ],
    )]
    #[Response(
        401,
        description: 'Google handshake failed or Google ID was empty.',
        type: 'array{message: string}',
        examples: [['message' => 'Google authentication failed.']],
    )]
    #[Response(
        409,
        description: 'Email matches an existing account but Google did not verify the email, so linking is refused.',
        type: 'array{message: string}',
        examples: [['message' => 'An account with this email already exists.']],
    )]
    #[Response(
        422,
        description: 'Google did not return an email address.',
        type: 'array{message: string}',
        examples: [['message' => 'Google did not provide an email address.']],
    )]
    public function callback(): JsonResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (Exception) {
            return response()->json([
                'message' => 'Google authentication failed.',
            ], 401);
        }

        $email = $googleUser->getEmail();

        if (! is_string($email) || $email === '') {
            return response()->json([
                'message' => 'Google did not provide an email address.',
            ], 422);
        }

        $googleId = (string) $googleUser->getId();

        if ($googleId === '') {
            return response()->json([
                'message' => 'Google authentication failed.',
            ], 401);
        }

        $user = $this->findOrCreateUser($googleUser, $googleId, $email);

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'An account with this email already exists.',
            ], 409);
        }

        return response()->json([
            'token' => $user->createToken('google')->plainTextToken,
            'user' => UserResource::make($user),
        ]);
    }

    /**
     * Match an existing Google account, link a verified email, or create a user.
     */
    private function findOrCreateUser(SocialiteUser $googleUser, string $googleId, string $email): ?User
    {
        $user = User::query()->where('google_id', $googleId)->first();

        if ($user instanceof User) {
            return $user;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing instanceof User) {
            if (! $this->emailIsVerified($googleUser)) {
                return null;
            }

            $existing->forceFill([
                'google_id' => $googleId,
                'email_verified_at' => $existing->email_verified_at ?? now(),
            ])->save();

            return $existing;
        }

        return User::query()->forceCreate([
            'google_id' => $googleId,
            'name' => $this->nameFromGoogle($googleUser, $email),
            'email' => $email,
            'email_verified_at' => $this->emailIsVerified($googleUser) ? now() : null,
            'password' => null,
        ]);
    }

    private function emailIsVerified(SocialiteUser $googleUser): bool
    {
        $raw = $googleUser->user ?? [];

        return (bool) ($raw['email_verified'] ?? $raw['verified_email'] ?? false);
    }

    private function nameFromGoogle(SocialiteUser $googleUser, string $email): string
    {
        $name = $googleUser->getName();

        if (is_string($name) && $name !== '') {
            return $name;
        }

        $local = strstr($email, '@', true);

        return is_string($local) && $local !== '' ? $local : 'Google user';
    }
}
