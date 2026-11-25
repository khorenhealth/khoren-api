<?php

use App\Models\Medication;
use App\Models\PatientProfile;
use App\Models\SupportingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('medication update returns 401 when no token is provided', function () {
    $this->putJson('/api/profile/medications', medications())
        ->assertUnauthorized();

    expect(Medication::query()->count())->toBe(0);
});

test('medication update returns 403 when the email is not verified', function () {
    $user = User::factory()->unverified()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/medications', medications())
        ->assertForbidden()
        ->assertJsonPath('message', 'Email address is not verified.');

    expect(Medication::query()->count())->toBe(0);
});

test('medication update returns 422 when personal information has not been saved', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/medications', medications())
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'medications' => 'Save your personal information before adding medications.',
        ]);

    expect(Medication::query()->count())->toBe(0);
});

test('medication update returns 422 when the list is missing', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/medications', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'medications' => 'Medication list is required. Send an empty list when none are reported.',
        ]);
});

test('medication update returns 422 when a medication name is blank', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/medications', [
            'medications' => [
                ['name' => ' ', 'strength' => '500 mg', 'frequency' => 'Twice daily'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'medications.0.name' => 'Medication name is required.',
        ]);

    expect(Medication::query()->count())->toBe(0);
});

test('medication update returns 422 when a field is too long', function (string $field, string $message) {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/medications', [
            'medications' => [
                array_merge([
                    'name' => 'Amoxicillin',
                    'strength' => '500 mg',
                    'frequency' => 'Twice daily',
                ], [
                    $field => str_repeat('A', 256),
                ]),
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'medications.0.'.$field => $message,
        ]);

    expect(Medication::query()->count())->toBe(0);
})->with([
    'name' => ['name', 'Medication name must be 255 characters or fewer.'],
    'strength' => ['strength', 'Strength or dose must be 255 characters or fewer.'],
    'frequency' => ['frequency', 'Frequency must be 255 characters or fewer.'],
]);

test('medication update returns 422 when more than 30 medications are sent', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/medications', [
            'medications' => array_fill(0, 31, ['name' => 'Amoxicillin']),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'medications' => 'You can report up to 30 medications.',
        ]);

    expect(Medication::query()->count())->toBe(0);
});

test('medication update accepts an empty list', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    Medication::factory()->create([
        'patient_profile_id' => $profile->id,
        'name' => 'Amoxicillin',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/medications', ['medications' => []])
        ->assertOk()
        ->assertJsonPath('data', []);

    expect($profile->fresh()->medications)->toHaveCount(0);
});

test('medication update creates the list and returns 201', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Adaeze',
    ]);
    $record = SupportingRecord::factory()->create(['patient_profile_id' => $profile->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/medications', [
            'medications' => [
                ['name' => 'Amoxicillin', 'strength' => '500 mg', 'frequency' => 'Twice daily'],
                ['name' => 'Paracetamol'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.0.name', 'Amoxicillin')
        ->assertJsonPath('data.0.strength', '500 mg')
        ->assertJsonPath('data.0.frequency', 'Twice daily')
        ->assertJsonPath('data.1.name', 'Paracetamol')
        ->assertJsonPath('data.1.strength', null)
        ->assertJsonPath('data.1.frequency', null);

    $saved = $profile->fresh()->medications;

    expect($saved)->toHaveCount(2)
        ->and($saved[0]->name)->toBe('Amoxicillin')
        ->and($saved[0]->patient_profile_id)->toBe($profile->id)
        ->and($saved[1]->name)->toBe('Paracetamol')
        ->and($profile->fresh()->first_name)->toBe('Adaeze')
        ->and(SupportingRecord::query()->find($record->id))->not->toBeNull();

    $this->app['auth']->forgetGuards();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('data.medications.0.name', 'Amoxicillin')
        ->assertJsonPath('data.medications.1.name', 'Paracetamol');
});

test('medication update returns 200 and replaces the list', function (string $method) {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    Medication::factory()->create([
        'patient_profile_id' => $profile->id,
        'name' => 'Old medicine',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->json($method, '/api/profile/medications', medications([
            'medications' => [
                ['name' => ' Amoxicillin ', 'strength' => ' 500 mg ', 'frequency' => ' '],
            ],
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Amoxicillin')
        ->assertJsonPath('data.0.strength', '500 mg')
        ->assertJsonPath('data.0.frequency', null);

    $saved = $profile->fresh()->medications;

    expect($saved)->toHaveCount(1)
        ->and($saved[0]->name)->toBe('Amoxicillin')
        ->and(Medication::query()->where('name', 'Old medicine')->exists())->toBeFalse();
})->with(['PUT', 'PATCH']);

test('medication update ignores a client supplied profile id', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    $otherProfile = PatientProfile::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/medications', [
            'medications' => [
                [
                    'name' => 'Amoxicillin',
                    'patient_profile_id' => $otherProfile->id,
                ],
            ],
        ])
        ->assertCreated();

    expect($profile->fresh()->medications)->toHaveCount(1)
        ->and($profile->fresh()->medications[0]->patient_profile_id)->toBe($profile->id)
        ->and($otherProfile->fresh()->medications)->toHaveCount(0);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function medications(array $overrides = []): array
{
    return array_replace_recursive([
        'medications' => [
            [
                'name' => 'Amoxicillin',
                'strength' => '500 mg',
                'frequency' => 'Twice daily',
            ],
        ],
    ], $overrides);
}
