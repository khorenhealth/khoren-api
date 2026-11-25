<?php

use App\Models\HomeVisitAddress;
use App\Models\Medication;
use App\Models\NotificationPreference;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('notification preference update returns 401 when no token is provided', function () {
    $this->putJson('/api/profile/notifications', notificationPreferences())
        ->assertUnauthorized();

    expect(NotificationPreference::query()->count())->toBe(0);
});

test('notification preference update returns 403 when the email is not verified', function () {
    $user = User::factory()->unverified()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/notifications', notificationPreferences())
        ->assertForbidden()
        ->assertJsonPath('message', 'Email address is not verified.');

    expect(NotificationPreference::query()->count())->toBe(0);
});

test('notification preference update returns 422 when personal information has not been saved', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/notifications', notificationPreferences())
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'visit_reminders' => 'Save your personal information before choosing notification preferences.',
        ]);

    expect(NotificationPreference::query()->count())->toBe(0);
});

test('notification preference update returns 422 when a toggle is missing or invalid', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/notifications', [
            'visit_reminders' => 'yes',
            'channels' => [
                'email' => true,
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'visit_reminders' => 'Visit reminders must be on or off.',
            'medication_reminders' => 'Medication reminders must be on or off.',
            'visit_reports_ready' => 'Visit reports must be on or off.',
            'clinical_alerts' => 'Clinical alerts must be on or off.',
            'channels.sms' => 'SMS must be on or off.',
            'channels.in_app' => 'In-app must be on or off.',
            'promotions' => 'Promotions and tips must be on or off.',
        ]);

    expect(NotificationPreference::query()->count())->toBe(0);
});

test('notification preference update creates the preferences and returns 201', function () {
    $user = User::factory()->create([
        'email' => 'adaeze.okonkwo@email.com',
    ]);
    $profile = PatientProfile::factory()->create([
        'user_id' => $user->id,
        'phone' => '+2348012345678',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/notifications', notificationPreferences())
        ->assertCreated()
        ->assertJsonPath('data.visit_reminders', true)
        ->assertJsonPath('data.medication_reminders', true)
        ->assertJsonPath('data.visit_reports_ready', true)
        ->assertJsonPath('data.clinical_alerts', true)
        ->assertJsonPath('data.channels.email', true)
        ->assertJsonPath('data.channels.sms', true)
        ->assertJsonPath('data.channels.in_app', true)
        ->assertJsonPath('data.promotions', false);

    $preference = $profile->fresh()->notificationPreference;

    expect($preference)->not->toBeNull()
        ->and($preference->patient_profile_id)->toBe($profile->id)
        ->and($preference->promotions)->toBeFalse()
        ->and($preference->channel_email)->toBeTrue()
        ->and($user->fresh()->email)->toBe('adaeze.okonkwo@email.com')
        ->and($profile->fresh()->phone)->toBe('+2348012345678')
        ->and(Medication::query()->count())->toBe(0)
        ->and(HomeVisitAddress::query()->count())->toBe(0);

    $this->app['auth']->forgetGuards();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('data.notifications.visit_reminders', true)
        ->assertJsonPath('data.notifications.promotions', false)
        ->assertJsonPath('data.email', 'adaeze.okonkwo@email.com')
        ->assertJsonPath('data.phone', '+2348012345678');
});

test('notification preference update returns 200 and replaces the preferences', function (string $method) {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    NotificationPreference::factory()->create([
        'patient_profile_id' => $profile->id,
        'visit_reminders' => true,
        'promotions' => false,
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->json($method, '/api/profile/notifications', notificationPreferences([
            'visit_reminders' => false,
            'medication_reminders' => false,
            'channels' => [
                'email' => true,
                'sms' => false,
                'in_app' => true,
            ],
            'promotions' => true,
        ]))
        ->assertOk()
        ->assertJsonPath('data.visit_reminders', false)
        ->assertJsonPath('data.medication_reminders', false)
        ->assertJsonPath('data.visit_reports_ready', true)
        ->assertJsonPath('data.clinical_alerts', true)
        ->assertJsonPath('data.channels.email', true)
        ->assertJsonPath('data.channels.sms', false)
        ->assertJsonPath('data.channels.in_app', true)
        ->assertJsonPath('data.promotions', true);

    $preference = $profile->fresh()->notificationPreference;

    expect(NotificationPreference::query()->count())->toBe(1)
        ->and($preference->visit_reminders)->toBeFalse()
        ->and($preference->channel_sms)->toBeFalse()
        ->and($preference->promotions)->toBeTrue();
})->with(['PUT', 'PATCH']);

test('notification preference update ignores a client supplied profile id', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    $otherProfile = PatientProfile::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/notifications', notificationPreferences([
            'patient_profile_id' => $otherProfile->id,
        ]))
        ->assertCreated();

    expect($profile->fresh()->notificationPreference)->not->toBeNull()
        ->and($profile->fresh()->notificationPreference->patient_profile_id)->toBe($profile->id)
        ->and($otherProfile->fresh()->notificationPreference)->toBeNull();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function notificationPreferences(array $overrides = []): array
{
    return array_replace_recursive(NotificationPreference::defaults(), $overrides);
}
