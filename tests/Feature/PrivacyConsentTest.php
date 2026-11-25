<?php

use App\Models\PatientProfile;
use App\Models\PrivacyConsent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('privacy update returns 401 when no token is provided', function () {
    $this->putJson('/api/profile/privacy', privacyChoices())
        ->assertUnauthorized();

    expect(PrivacyConsent::query()->count())->toBe(0);
});

test('privacy update returns 403 when the email is not verified', function () {
    $user = User::factory()->unverified()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/privacy', privacyChoices())
        ->assertForbidden()
        ->assertJsonPath('message', 'Email address is not verified.');

    expect(PrivacyConsent::query()->count())->toBe(0);
});

test('privacy update returns 422 when personal information has not been saved', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/privacy', privacyChoices())
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'care_consent' => 'Save your personal information before saving privacy choices.',
        ]);

    expect(PrivacyConsent::query()->count())->toBe(0);
});

test('privacy update returns 422 when a choice is missing or invalid', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/privacy', [
            'care_consent' => 'yes',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'care_consent' => 'Care-related consent must be accepted or saved for later.',
            'anonymised_data_sharing' => 'Anonymised data sharing must be on or off.',
        ]);

    expect(PrivacyConsent::query()->count())->toBe(0);
});

test('privacy update saves an exit without care consent', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/privacy', privacyChoices([
            'care_consent' => false,
            'anonymised_data_sharing' => true,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.care_consent', false)
        ->assertJsonPath('data.care_consent_given_at', null)
        ->assertJsonPath('data.anonymised_data_sharing', true);

    $consent = $profile->fresh()->privacyConsent;

    expect($consent)->not->toBeNull()
        ->and($consent->care_consent)->toBeFalse()
        ->and($consent->care_consent_given_at)->toBeNull()
        ->and($consent->anonymised_data_sharing)->toBeTrue();
});

test('privacy update records care consent and ignores a client supplied time', function () {
    $this->freezeTime();

    $user = User::factory()->create([
        'email' => 'adaeze.okonkwo@email.com',
    ]);
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);

    $response = $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/privacy', privacyChoices([
            'care_consent_given_at' => '2020-01-01T00:00:00Z',
        ]));

    $consent = $profile->fresh()->privacyConsent;
    $givenAt = $consent->care_consent_given_at?->toJSON();

    $response->assertCreated()
        ->assertJsonPath('data.care_consent', true)
        ->assertJsonPath('data.care_consent_given_at', $givenAt)
        ->assertJsonPath('data.anonymised_data_sharing', false);

    expect($givenAt)->not->toBeNull()
        ->and($consent->care_consent_given_at?->eq(now()->startOfSecond()))->toBeTrue()
        ->and($consent->patient_profile_id)->toBe($profile->id)
        ->and($user->fresh()->email)->toBe('adaeze.okonkwo@email.com');

    $this->app['auth']->forgetGuards();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('data.privacy.care_consent', true)
        ->assertJsonPath('data.privacy.care_consent_given_at', $givenAt)
        ->assertJsonPath('data.privacy.anonymised_data_sharing', false);
});

test('privacy update keeps the original care consent time until it is withdrawn', function (string $method) {
    $this->freezeTime();

    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('auth')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/profile/privacy', privacyChoices())
        ->assertCreated();

    $givenAt = $profile->fresh()->privacyConsent->care_consent_given_at?->toJSON();

    $this->app['auth']->forgetGuards();
    $this->travel(2)->days();

    $this->withToken($token)
        ->json($method, '/api/profile/privacy', privacyChoices([
            'anonymised_data_sharing' => true,
        ]))
        ->assertOk()
        ->assertJsonPath('data.care_consent', true)
        ->assertJsonPath('data.care_consent_given_at', $givenAt)
        ->assertJsonPath('data.anonymised_data_sharing', true);

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->putJson('/api/profile/privacy', privacyChoices([
            'care_consent' => false,
        ]))
        ->assertOk()
        ->assertJsonPath('data.care_consent', false)
        ->assertJsonPath('data.care_consent_given_at', null);

    $this->app['auth']->forgetGuards();
    $this->travel(1)->hours();

    $response = $this->withToken($token)
        ->putJson('/api/profile/privacy', privacyChoices())
        ->assertOk();

    $renewedAt = $profile->fresh()->privacyConsent->care_consent_given_at?->toJSON();

    $response->assertJsonPath('data.care_consent_given_at', $renewedAt);

    expect($renewedAt)->not->toBe($givenAt)
        ->and($profile->fresh()->privacyConsent->care_consent_given_at?->eq(now()->startOfSecond()))->toBeTrue()
        ->and(PrivacyConsent::query()->count())->toBe(1);
})->with(['PUT', 'PATCH']);

test('privacy update ignores a client supplied profile id', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    $otherProfile = PatientProfile::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/privacy', privacyChoices([
            'patient_profile_id' => $otherProfile->id,
        ]))
        ->assertCreated();

    expect($profile->fresh()->privacyConsent)->not->toBeNull()
        ->and($profile->fresh()->privacyConsent->patient_profile_id)->toBe($profile->id)
        ->and($otherProfile->fresh()->privacyConsent)->toBeNull();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function privacyChoices(array $overrides = []): array
{
    return array_merge([
        'care_consent' => true,
        'anonymised_data_sharing' => false,
    ], $overrides);
}
