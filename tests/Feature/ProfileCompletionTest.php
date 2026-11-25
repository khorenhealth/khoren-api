<?php

use App\Models\HomeVisitAddress;
use App\Models\Medication;
use App\Models\PatientProfile;
use App\Models\PrivacyConsent;
use App\Models\ProfileCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('profile completion returns 401 when no token is provided', function () {
    $this->putJson('/api/profile/completion', profileCompletion())
        ->assertUnauthorized();

    expect(ProfileCompletion::query()->count())->toBe(0);
});

test('profile completion returns 403 when the email is not verified', function () {
    $user = User::factory()->unverified()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/completion', profileCompletion())
        ->assertForbidden()
        ->assertJsonPath('message', 'Email address is not verified.');

    expect(ProfileCompletion::query()->count())->toBe(0);
});

test('profile completion returns 422 when personal information has not been saved', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/completion', profileCompletion())
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'accuracy_confirmed' => 'Save your personal information before completing setup.',
        ]);

    expect(ProfileCompletion::query()->count())->toBe(0);
});

test('profile completion returns 422 when care consent has not been saved', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/completion', profileCompletion())
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'accuracy_confirmed' => 'Accept care-related consent before completing setup.',
        ]);

    expect(ProfileCompletion::query()->count())->toBe(0);
});

test('profile completion returns 422 when care consent has not been accepted', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    PrivacyConsent::factory()->create([
        'patient_profile_id' => $profile->id,
        'care_consent' => false,
        'care_consent_given_at' => null,
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/completion', profileCompletion())
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'accuracy_confirmed' => 'Accept care-related consent before completing setup.',
        ]);

    expect(ProfileCompletion::query()->count())->toBe(0);
});

test('profile completion returns 422 when the accuracy confirmation is not accepted', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    PrivacyConsent::factory()->create(['patient_profile_id' => $profile->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/completion', profileCompletion([
            'accuracy_confirmed' => false,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'accuracy_confirmed' => 'Confirm that the information you provided is accurate.',
        ]);

    expect(ProfileCompletion::query()->count())->toBe(0);
});

test('profile completion completes setup and ignores a client supplied time', function () {
    $this->freezeTime();

    $user = User::factory()->create([
        'email' => 'adaeze.okonkwo@email.com',
    ]);
    $profile = PatientProfile::factory()->create([
        'user_id' => $user->id,
        'phone' => '+2348012345678',
    ]);
    PrivacyConsent::factory()->create([
        'patient_profile_id' => $profile->id,
        'anonymised_data_sharing' => false,
    ]);

    $response = $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/completion', profileCompletion([
            'accuracy_confirmed_at' => '2020-01-01T00:00:00Z',
            'setup_completed_at' => '2020-01-01T00:00:00Z',
        ]));

    $completion = $profile->fresh()->profileCompletion;
    $confirmedAt = $completion->accuracy_confirmed_at?->toJSON();

    $response->assertCreated()
        ->assertJsonPath('data.accuracy_confirmed', true)
        ->assertJsonPath('data.accuracy_confirmed_at', $confirmedAt)
        ->assertJsonPath('data.setup_completed_at', $completion->setup_completed_at?->toJSON());

    expect($completion->accuracy_confirmed_at?->eq(now()->startOfSecond()))->toBeTrue()
        ->and($completion->setup_completed_at?->eq($completion->accuracy_confirmed_at))->toBeTrue()
        ->and($completion->patient_profile_id)->toBe($profile->id)
        ->and($user->fresh()->email)->toBe('adaeze.okonkwo@email.com')
        ->and($profile->fresh()->phone)->toBe('+2348012345678')
        ->and(Medication::query()->count())->toBe(0)
        ->and(HomeVisitAddress::query()->count())->toBe(0);

    $this->app['auth']->forgetGuards();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('data.completion.accuracy_confirmed', true)
        ->assertJsonPath('data.completion.accuracy_confirmed_at', $confirmedAt)
        ->assertJsonPath('data.completion.setup_completed_at', $confirmedAt);
});

test('profile completion keeps the original completion time', function (string $method) {
    $this->freezeTime();

    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    PrivacyConsent::factory()->create(['patient_profile_id' => $profile->id]);
    $token = $user->createToken('auth')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/profile/completion', profileCompletion())
        ->assertCreated();

    $confirmedAt = $profile->fresh()->profileCompletion->accuracy_confirmed_at?->toJSON();

    $this->app['auth']->forgetGuards();
    $this->travel(3)->days();

    $this->withToken($token)
        ->json($method, '/api/profile/completion', profileCompletion())
        ->assertOk()
        ->assertJsonPath('data.accuracy_confirmed', true)
        ->assertJsonPath('data.accuracy_confirmed_at', $confirmedAt)
        ->assertJsonPath('data.setup_completed_at', $confirmedAt);

    expect(ProfileCompletion::query()->count())->toBe(1)
        ->and($profile->fresh()->profileCompletion->setup_completed_at?->toJSON())->toBe($confirmedAt);
})->with(['PUT', 'PATCH']);

test('profile completion ignores a client supplied profile id', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    PrivacyConsent::factory()->create(['patient_profile_id' => $profile->id]);
    $otherProfile = PatientProfile::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/completion', profileCompletion([
            'patient_profile_id' => $otherProfile->id,
        ]))
        ->assertCreated();

    expect($profile->fresh()->profileCompletion)->not->toBeNull()
        ->and($profile->fresh()->profileCompletion->patient_profile_id)->toBe($profile->id)
        ->and($otherProfile->fresh()->profileCompletion)->toBeNull();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function profileCompletion(array $overrides = []): array
{
    return array_merge([
        'accuracy_confirmed' => true,
    ], $overrides);
}
