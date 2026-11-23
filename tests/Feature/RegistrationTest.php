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
        ->assertJsonPath('email', 'youremail@gmail.com')
        ->assertJsonStructure(['email', 'expires_at', 'resend_available_at'])
        ->assertJsonMissingPath('token');

    $user = User::query()->where('email', 'youremail@gmail.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('youremail')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->password)->not->toBeNull();
});

test('registration accepts an optional name', function () {
    $this->postJson('/api/sign-up', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'Secret1!',
        'password_confirmation' => 'Secret1!',
        'terms' => true,
    ])->assertCreated()
        ->assertJsonPath('email', 'ada@example.com');

    expect(User::query()->where('email', 'ada@example.com')->value('name'))->toBe('Ada Lovelace');
});

test('registration ignores a client supplied verification timestamp', function () {
    $this->postJson('/api/sign-up', [
        'email' => 'youremail@gmail.com',
        'password' => 'Secret1!',
        'password_confirmation' => 'Secret1!',
        'terms' => true,
        'email_verified_at' => '2026-11-23T08:00:00.000000Z',
    ])->assertCreated();

    expect(User::query()->where('email', 'youremail@gmail.com')->first()?->email_verified_at)->toBeNull();
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
