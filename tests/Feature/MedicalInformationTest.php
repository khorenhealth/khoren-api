<?php

use App\Enums\AllergyStatus;
use App\Enums\BloodGroup;
use App\Models\MedicalInformation;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('medical information update returns 401 when no token is provided', function () {
    $this->putJson('/api/profile/medical-information', medicalInformation())
        ->assertUnauthorized();

    expect(MedicalInformation::query()->count())->toBe(0);
});

test('medical information update returns 403 when the email is not verified', function () {
    $user = User::factory()->unverified()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/medical-information', medicalInformation())
        ->assertForbidden()
        ->assertJsonPath('message', 'Email address is not verified.');

    expect(MedicalInformation::query()->count())->toBe(0);
});

test('medical information update returns 422 when personal information has not been saved', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/medical-information', medicalInformation())
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'primary_condition' => 'Save your personal information before adding medical information.',
        ]);

    expect(MedicalInformation::query()->count())->toBe(0);
});

test('medical information update returns 422 when required fields are blank', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/medical-information', [
            'primary_condition' => ' ',
            'blood_group' => '',
            'allergy_status' => '',
            'known_allergies' => ' ',
        ])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors([
            'primary_condition' => 'Primary condition or reason for care is required.',
            'blood_group' => 'Blood group is required.',
            'allergy_status' => 'Allergy status is required.',
        ]);

    expect(MedicalInformation::query()->count())->toBe(0);
});

test('medical information update returns 422 when the blood group or allergy status is invalid', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/medical-information', medicalInformation([
            'blood_group' => 'C+',
            'allergy_status' => 'severe',
        ]))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors([
            'blood_group' => 'Choose A+, A-, B+, B-, AB+, AB-, O+, O-, or Unknown.',
            'allergy_status' => 'Choose Known allergies, None known, or Unknown.',
        ]);

    expect(MedicalInformation::query()->count())->toBe(0);
});

test('medical information update returns 422 when known allergies are missing', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/medical-information', medicalInformation([
            'known_allergies' => ' ',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'known_allergies' => 'Known allergies are required.',
        ]);

    expect(MedicalInformation::query()->count())->toBe(0);
});

test('medical information update returns 422 when the primary condition is too long', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/medical-information', medicalInformation([
            'primary_condition' => str_repeat('A', 256),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'primary_condition' => 'Primary condition or reason for care must be 255 characters or fewer.',
        ]);

    expect(MedicalInformation::query()->count())->toBe(0);
});

test('medical information update returns 422 when known allergies are too long', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/medical-information', medicalInformation([
            'known_allergies' => str_repeat('A', 1001),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'known_allergies' => 'Known allergies must be 1000 characters or fewer.',
        ]);

    expect(MedicalInformation::query()->count())->toBe(0);
});

test('medical information update returns 422 when other conditions are too long', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/medical-information', medicalInformation([
            'other_conditions' => str_repeat('A', 2001),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'other_conditions' => 'Other relevant conditions must be 2000 characters or fewer.',
        ]);

    expect(MedicalInformation::query()->count())->toBe(0);
});

test('medical information update creates the record and returns 201', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Adaeze',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/medical-information', medicalInformation([
            'other_conditions' => 'Mild asthma',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.primary_condition', 'Post-Appendectomy Recovery')
        ->assertJsonPath('data.blood_group', 'o_positive')
        ->assertJsonPath('data.allergy_status', 'known_allergies')
        ->assertJsonPath('data.known_allergies', 'Penicillin')
        ->assertJsonPath('data.other_conditions', 'Mild asthma');

    $record = $profile->fresh()->medicalInformation;

    expect($record)->not->toBeNull()
        ->and($record->patient_profile_id)->toBe($profile->id)
        ->and($record->primary_condition)->toBe('Post-Appendectomy Recovery')
        ->and($record->blood_group)->toBe(BloodGroup::OPositive)
        ->and($record->allergy_status)->toBe(AllergyStatus::KnownAllergies)
        ->and($record->known_allergies)->toBe('Penicillin')
        ->and($record->other_conditions)->toBe('Mild asthma')
        ->and($profile->fresh()->first_name)->toBe('Adaeze');

    $this->app['auth']->forgetGuards();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('data.medical_information.primary_condition', 'Post-Appendectomy Recovery')
        ->assertJsonPath('data.medical_information.blood_group', 'o_positive')
        ->assertJsonPath('data.medical_information.known_allergies', 'Penicillin');
});

test('medical information update returns 200 and replaces the record', function (string $method) {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    MedicalInformation::factory()->create([
        'patient_profile_id' => $profile->id,
        'other_conditions' => 'Old note',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->json($method, '/api/profile/medical-information', medicalInformation([
            'primary_condition' => 'Wound review',
            'blood_group' => 'Unknown',
            'allergy_status' => 'None known',
            'known_allergies' => 'Penicillin',
        ]))
        ->assertOk()
        ->assertJsonPath('data.primary_condition', 'Wound review')
        ->assertJsonPath('data.blood_group', 'unknown')
        ->assertJsonPath('data.allergy_status', 'none_known')
        ->assertJsonPath('data.known_allergies', null)
        ->assertJsonPath('data.other_conditions', null);

    $record = $profile->fresh()->medicalInformation;

    expect($record->primary_condition)->toBe('Wound review')
        ->and($record->blood_group)->toBe(BloodGroup::Unknown)
        ->and($record->allergy_status)->toBe(AllergyStatus::NoneKnown)
        ->and($record->known_allergies)->toBeNull()
        ->and($record->other_conditions)->toBeNull();
})->with(['PUT', 'PATCH']);

test('medical information update stores unknown allergies without a list', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/medical-information', medicalInformation([
            'blood_group' => "O\u{2212}",
            'allergy_status' => 'unknown',
            'known_allergies' => 'Peanuts',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.blood_group', 'o_negative')
        ->assertJsonPath('data.allergy_status', 'unknown')
        ->assertJsonPath('data.known_allergies', null);

    expect($profile->fresh()->medicalInformation->blood_group)->toBe(BloodGroup::ONegative)
        ->and($profile->fresh()->medicalInformation->known_allergies)->toBeNull();
});

test('medical information update ignores a client supplied profile id', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    $other = User::factory()->create();
    $otherProfile = PatientProfile::factory()->create(['user_id' => $other->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/medical-information', medicalInformation([
            'patient_profile_id' => $otherProfile->id,
        ]))
        ->assertCreated();

    expect($profile->fresh()->medicalInformation)->not->toBeNull()
        ->and($profile->fresh()->medicalInformation->patient_profile_id)->toBe($profile->id)
        ->and($otherProfile->fresh()->medicalInformation)->toBeNull();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function medicalInformation(array $overrides = []): array
{
    return array_merge([
        'primary_condition' => 'Post-Appendectomy Recovery',
        'blood_group' => 'O+',
        'allergy_status' => 'Known allergies',
        'known_allergies' => 'Penicillin',
    ], $overrides);
}
