<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a guest can register with a valid email and password', function () {
    $response = $this->postJson('/api/sign-up', [
        'email' => 'youremail@gmail.com',
        'password' => 'Secret1!',
        'password_confirmation' => 'Secret1!',
        'terms' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.email', 'youremail@gmail.com')
        ->assertJsonPath('user.name', 'youremail')
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'email_verified_at']]);

    $user = User::query()->where('email', 'youremail@gmail.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->password)->not->toBeNull();

    $this->withToken($response->json('token'))
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.email', 'youremail@gmail.com');
});

test('registration accepts an optional name', function () {
    $this->postJson('/api/sign-up', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'Secret1!',
        'password_confirmation' => 'Secret1!',
        'terms' => true,
    ])->assertCreated()
        ->assertJsonPath('user.name', 'Ada Lovelace');
});

test('registration requires matching passwords', function () {
    $this->postJson('/api/sign-up', [
        'email' => 'youremail@gmail.com',
        'password' => 'Secret1!',
        'password_confirmation' => 'Different1!',
        'terms' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password'])
        ->assertJsonPath('errors.password.0', "Password doesn't match!");

    expect(User::query()->count())->toBe(0);
});

test('registration requires accepted terms', function () {
    $this->postJson('/api/sign-up', [
        'email' => 'youremail@gmail.com',
        'password' => 'Secret1!',
        'password_confirmation' => 'Secret1!',
        'terms' => false,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['terms']);
});

test('registration rejects a weak password', function () {
    $this->postJson('/api/sign-up', [
        'email' => 'youremail@gmail.com',
        'password' => 'secret',
        'password_confirmation' => 'secret',
        'terms' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('registration rejects a duplicate email', function () {
    User::factory()->create(['email' => 'youremail@gmail.com']);

    $this->postJson('/api/sign-up', [
        'email' => 'youremail@gmail.com',
        'password' => 'Secret1!',
        'password_confirmation' => 'Secret1!',
        'terms' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
