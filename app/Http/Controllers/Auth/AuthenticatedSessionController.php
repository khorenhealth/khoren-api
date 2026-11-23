<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

#[Group('Authentication', weight: 1)]
class AuthenticatedSessionController extends Controller
{
    /**
     * Sign in with email and password and return an API token.
     */
    #[Endpoint(
        title: 'Sign in',
        description: 'Authenticates with email and password, then returns a Sanctum personal access token.',
    )]
    #[Response(
        200,
        description: 'Authenticated. Store the token and send it as `Authorization: Bearer {token}`.',
        type: 'array{token: string, user: App\Http\Resources\UserResource}',
    )]
    #[Response(403, description: 'The password is correct, but the email address is not verified.')]
    #[Response(422, description: 'Invalid credentials or validation failed.')]
    public function store(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if (! $user instanceof User || $user->password === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->email_verified_at === null) {
            return response()->json([
                'message' => 'Email address is not verified.',
                'email' => $user->email,
            ], 403);
        }

        return response()->json([
            'token' => $user->createToken('auth')->plainTextToken,
            'user' => UserResource::make($user),
        ]);
    }

    /**
     * Revoke the current Sanctum token.
     */
    #[Endpoint(
        title: 'Sign out',
        description: 'Revokes the Bearer token used for this request.',
    )]
    #[Response(204, description: 'Token revoked.')]
    public function destroy(Request $request): HttpResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }
}
