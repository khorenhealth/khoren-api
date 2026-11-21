<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group('Authentication', weight: 1)]
class RegisteredUserController extends Controller
{
    /**
     * Create a password-based account and return an API token.
     */
    #[Endpoint(
        title: 'Sign up',
        description: 'Registers with email and password. Password must be at least 8 characters and include uppercase, lowercase, a number, and a special character. `terms` must be accepted.',
    )]
    #[Response(
        201,
        description: 'Account created. Store the Sanctum token as `Authorization: Bearer {token}`.',
        type: 'array{token: string, user: App\Http\Resources\UserResource}',
        examples: [
            [
                'token' => '1|plainTextSanctumToken',
                'user' => [
                    'id' => 1,
                    'name' => 'youremail',
                    'email' => 'youremail@gmail.com',
                    'email_verified_at' => null,
                ],
            ],
        ],
    )]
    #[Response(422, description: 'Validation failed (email taken, weak password, mismatch, or terms not accepted).')]
    public function store(RegisterRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();
        $name = $request->string('name')->toString();

        if ($name === '') {
            $local = strstr($email, '@', true);
            $name = is_string($local) && $local !== '' ? $local : 'User';
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $request->string('password')->toString(),
        ]);

        return response()->json([
            'token' => $user->createToken('auth')->plainTextToken,
            'user' => UserResource::make($user),
        ], 201);
    }
}
