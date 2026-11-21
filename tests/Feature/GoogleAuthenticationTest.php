<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

test('the google redirect sends the browser to google', function () {
    Socialite::fake('google');

    $this->get(route('auth.google.redirect'))
        ->assertRedirect('https://socialite.fake/google/authorize');
});

test('the google callback creates a user and returns an api token', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'email_verified' => true,
    ]));

    $response = $this->get(route('auth.google.callback'));

    $response->assertOk()
        ->assertJsonPath('user.email', 'ada@example.com')
        ->assertJsonPath('user.name', 'Ada Lovelace')
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'email_verified_at']]);

    $user = User::query()->where('email', 'ada@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->google_id)->toBe('google-123')
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();

    $this->withToken($response->json('token'))
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.email', 'ada@example.com');
});

test('the google callback signs in an existing google account', function () {
    $user = User::factory()->create([
        'google_id' => 'google-123',
        'email' => 'ada@example.com',
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'email_verified' => true,
    ]));

    $this->get(route('auth.google.callback'))
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);

    expect(User::query()->count())->toBe(1);
});

test('the google callback links a verified email to an existing account', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'google_id' => null,
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'email_verified' => true,
    ]));

    $this->get(route('auth.google.callback'))
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);

    expect($user->fresh()->google_id)->toBe('google-123')
        ->and(User::query()->count())->toBe(1);
});

test('the google callback returns 409 when an unverified google email matches an existing account', function () {
    User::factory()->create([
        'email' => 'ada@example.com',
        'google_id' => null,
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'email_verified' => false,
    ]));

    $this->getJson(route('auth.google.callback'))
        ->assertConflict()
        ->assertJsonPath('message', 'An account with this email already exists.');

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->first()->google_id)->toBeNull();
});

test('the google callback returns 422 when google does not provide an email', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-123',
        'name' => 'Ada Lovelace',
        'email' => null,
    ]));

    $this->getJson(route('auth.google.callback'))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Google did not provide an email address.');

    expect(User::query()->count())->toBe(0);
});

test('the google callback returns 401 when google authentication fails', function () {
    Socialite::fake('google', function () {
        throw new RuntimeException('Google denied the request.');
    });

    $this->getJson(route('auth.google.callback'))
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Google authentication failed.');
});

test('the current user endpoint returns 401 when no token is provided', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});
