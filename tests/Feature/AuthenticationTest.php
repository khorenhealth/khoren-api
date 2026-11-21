<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('a user can sign in with email and password', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('Secret1!'),
    ]);

    $response = $this->postJson('/api/sign-in', [
        'email' => 'ada@example.com',
        'password' => 'Secret1!',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

    $this->withToken($response->json('token'))
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.email', 'ada@example.com');
});

test('sign in fails with invalid credentials', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('Secret1!'),
    ]);

    $this->postJson('/api/sign-in', [
        'email' => 'ada@example.com',
        'password' => 'WrongPass1!',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('a google-only account cannot sign in with a password', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
        'google_id' => 'google-123',
        'password' => null,
    ]);

    $this->postJson('/api/sign-in', [
        'email' => 'ada@example.com',
        'password' => 'Secret1!',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('an authenticated user can sign out and revoke the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/logout')
        ->assertNoContent();

    expect($user->tokens()->count())->toBe(0);

    // Each HTTP call in a test shares the app container; clear the guard so the
    // revoked token is re-checked instead of reusing the previous request's user.
    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertUnauthorized();
});

test('sign out requires authentication', function () {
    $this->postJson('/api/logout')->assertUnauthorized();
});
