<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\EmailVerificationService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

#[Group('Authentication', weight: 1)]
class RegisteredUserController extends Controller
{
    /**
     * Create a password-based account and return an API token.
     */
    #[Endpoint(
        title: 'Sign up',
        description: 'Registers with email and password and emails a 6-digit verification code. Password must be at least 8 characters and include uppercase, lowercase, a number, and a special character. `terms` must be accepted. No API token is returned until the code is verified.',
    )]
    #[Response(
        201,
        description: 'Account created and a verification code was emailed. `resend_available_at` is when another code can be requested.',
        type: 'array{email: string, expires_at: string, resend_available_at: string}',
        examples: [
            [
                'email' => 'youremail@gmail.com',
                'expires_at' => '2026-11-23T08:10:00.000000Z',
                'resend_available_at' => '2026-11-23T08:01:00.000000Z',
            ],
        ],
    )]
    #[Response(422, description: 'Validation failed (email taken, weak password, mismatch, or terms not accepted).')]
    public function store(RegisterRequest $request, EmailVerificationService $codes): JsonResponse
    {
        $email = $request->string('email')->toString();
        $name = $request->string('name')->toString();

        if ($name === '') {
            $local = strstr($email, '@', true);
            $name = is_string($local) && $local !== '' ? $local : 'User';
        }

        $user = DB::transaction(function () use ($request, $codes, $email, $name): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $request->string('password')->toString(),
            ]);

            $codes->send($user);

            return $user;
        });

        return response()->json($codes->pendingPayload($user), 201);
    }
}
