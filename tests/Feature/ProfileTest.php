<?php

use App\Enums\Gender;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('profile show returns 401 when no token is provided', function () {
    $this->getJson('/api/profile')->assertUnauthorized();
});

test('profile show returns 403 when the email is not verified', function () {
    $user = User::factory()->unverified()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->getJson('/api/profile')
        ->assertForbidden()
        ->assertJsonPath('message', 'Email address is not verified.');
});

test('profile show returns empty personal information before it is saved', function () {
    $user = User::factory()->create([
        'email' => 'adaeze.okonkwo@email.com',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('data.first_name', null)
        ->assertJsonPath('data.last_name', null)
        ->assertJsonPath('data.email', 'adaeze.okonkwo@email.com')
        ->assertJsonPath('data.email_verified_at', $user->email_verified_at->toJSON())
        ->assertJsonPath('data.date_of_birth', null)
        ->assertJsonPath('data.gender', null)
        ->assertJsonPath('data.phone', null)
        ->assertJsonPath('data.photo_url', null)
        ->assertJsonPath('data.home_visit.street_address', null)
        ->assertJsonPath('data.home_visit.state', null)
        ->assertJsonPath('data.home_visit.local_government_area', null)
        ->assertJsonPath('data.home_visit.country', null)
        ->assertJsonPath('data.home_visit.landmark', null)
        ->assertJsonPath('data.home_visit.access_directions', null)
        ->assertJsonPath('data.medical_information.primary_condition', null)
        ->assertJsonPath('data.medical_information.blood_group', null)
        ->assertJsonPath('data.medical_information.allergy_status', null)
        ->assertJsonPath('data.medical_information.known_allergies', null)
        ->assertJsonPath('data.medical_information.other_conditions', null)
        ->assertJsonPath('data.medications', [])
        ->assertJsonPath('data.supporting_records', [])
        ->assertJsonPath('data.emergency_contact.full_name', null)
        ->assertJsonPath('data.emergency_contact.relationship', null)
        ->assertJsonPath('data.emergency_contact.phone', null)
        ->assertJsonPath('data.emergency_contact.permission_to_contact', false)
        ->assertJsonPath('data.notifications.visit_reminders', true)
        ->assertJsonPath('data.notifications.medication_reminders', true)
        ->assertJsonPath('data.notifications.visit_reports_ready', true)
        ->assertJsonPath('data.notifications.clinical_alerts', true)
        ->assertJsonPath('data.notifications.channels.email', true)
        ->assertJsonPath('data.notifications.channels.sms', true)
        ->assertJsonPath('data.notifications.channels.in_app', true)
        ->assertJsonPath('data.notifications.promotions', false)
        ->assertJsonPath('data.privacy.care_consent', false)
        ->assertJsonPath('data.privacy.care_consent_given_at', null)
        ->assertJsonPath('data.privacy.anonymised_data_sharing', false)
        ->assertJsonPath('data.completion.accuracy_confirmed', false)
        ->assertJsonPath('data.completion.accuracy_confirmed_at', null)
        ->assertJsonPath('data.completion.setup_completed_at', null);
});

test('profile show returns the saved personal information', function () {
    $user = User::factory()->create([
        'email' => 'adaeze.okonkwo@email.com',
    ]);

    PatientProfile::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Adaeze',
        'last_name' => 'Okonkwo',
        'date_of_birth' => '1990-08-14',
        'gender' => Gender::Female,
        'phone' => '+2348012345678',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Adaeze')
        ->assertJsonPath('data.last_name', 'Okonkwo')
        ->assertJsonPath('data.email', 'adaeze.okonkwo@email.com')
        ->assertJsonPath('data.date_of_birth', '1990-08-14')
        ->assertJsonPath('data.gender', 'female')
        ->assertJsonPath('data.phone', '+2348012345678')
        ->assertJsonPath('data.photo_url', null);
});

test('profile update returns 401 when no token is provided', function () {
    $this->postJson('/api/profile', patientPersonalInformation())
        ->assertUnauthorized();

    expect(PatientProfile::query()->count())->toBe(0);
});

test('profile update returns 403 when the email is not verified', function () {
    $user = User::factory()->unverified()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile', patientPersonalInformation())
        ->assertForbidden()
        ->assertJsonPath('message', 'Email address is not verified.');

    expect($user->fresh()->patientProfile)->toBeNull();
});

test('profile update creates personal information and returns 201', function (string $dateOfBirth) {
    $user = User::factory()->create([
        'name' => 'adaeze.okonkwo',
        'email' => 'adaeze.okonkwo@email.com',
    ]);

    $response = $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile', patientPersonalInformation([
            'date_of_birth' => $dateOfBirth,
            'email' => 'adaeze.okonkwo@email.com',
        ]));

    $response->assertCreated()
        ->assertJsonPath('data.first_name', 'Adaeze')
        ->assertJsonPath('data.last_name', 'Okonkwo')
        ->assertJsonPath('data.email', 'adaeze.okonkwo@email.com')
        ->assertJsonPath('data.email_verified_at', $user->email_verified_at->toJSON())
        ->assertJsonPath('data.date_of_birth', '1990-08-14')
        ->assertJsonPath('data.gender', 'female')
        ->assertJsonPath('data.phone', '+2348012345678')
        ->assertJsonPath('data.photo_url', null);

    $profile = $user->fresh()->patientProfile;

    expect($profile)->not->toBeNull()
        ->and($profile->first_name)->toBe('Adaeze')
        ->and($profile->last_name)->toBe('Okonkwo')
        ->and($profile->date_of_birth->toDateString())->toBe('1990-08-14')
        ->and($profile->gender)->toBe(Gender::Female)
        ->and($profile->phone)->toBe('+2348012345678')
        ->and($profile->user_id)->toBe($user->id)
        ->and($user->fresh()->name)->toBe('Adaeze Okonkwo')
        ->and($user->fresh()->email)->toBe('adaeze.okonkwo@email.com');
})->with([
    'day month year' => '14/08/1990',
    'iso' => '1990-08-14',
]);

test('profile update returns 200 and replaces personal information', function (string $method) {
    $user = User::factory()->create([
        'email' => 'adaeze.okonkwo@email.com',
    ]);

    PatientProfile::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Adaeze',
        'last_name' => 'Okonkwo',
        'photo_path' => 'profile-photos/keep.jpg',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->json($method, '/api/profile', patientPersonalInformation([
            'last_name' => 'Adeyemi',
            'gender' => 'male',
        ]))
        ->assertOk()
        ->assertJsonPath('data.last_name', 'Adeyemi')
        ->assertJsonPath('data.gender', 'male')
        ->assertJsonPath('data.photo_url', Storage::disk('public')->url('profile-photos/keep.jpg'));

    $profile = $user->fresh()->patientProfile;

    expect($profile->last_name)->toBe('Adeyemi')
        ->and($profile->gender)->toBe(Gender::Male)
        ->and($profile->photo_path)->toBe('profile-photos/keep.jpg')
        ->and($user->fresh()->name)->toBe('Adaeze Adeyemi');
})->with(['PUT', 'PATCH']);

test('profile update returns 422 when required fields are blank', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile', [
            'first_name' => '',
            'last_name' => ' ',
            'date_of_birth' => '',
            'gender' => '',
            'phone' => '',
        ])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors([
            'first_name' => 'First name is required.',
            'last_name' => 'Last name is required.',
            'date_of_birth' => 'Date of birth is required.',
            'gender' => 'Gender is required.',
            'phone' => 'Phone number is required.',
        ]);

    expect(PatientProfile::query()->count())->toBe(0);
});

test('profile update returns 422 when a name is longer than 100 characters', function (string $field, string $message) {
    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile', patientPersonalInformation([
            $field => str_repeat('A', 101),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            $field => $message,
        ]);

    expect(PatientProfile::query()->count())->toBe(0);
})->with([
    'first name' => ['first_name', 'First name must be 100 characters or fewer.'],
    'last name' => ['last_name', 'Last name must be 100 characters or fewer.'],
]);

test('profile update returns 422 when the date of birth is not a real date', function (string $dateOfBirth) {
    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile', patientPersonalInformation([
            'date_of_birth' => $dateOfBirth,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'date_of_birth' => 'Use DD/MM/YYYY.',
        ]);

    expect(PatientProfile::query()->count())->toBe(0);
})->with([
    'impossible day' => '31/02/1990',
    'slashes in iso order' => '1990/08/14',
]);

test('profile update returns 422 when the date of birth is in the future', function () {
    $this->travelTo('2026-11-24 12:00:00');

    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile', patientPersonalInformation([
            'date_of_birth' => '25/11/2026',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'date_of_birth' => 'Date of birth cannot be in the future.',
        ]);

    expect(PatientProfile::query()->count())->toBe(0);
});

test('profile update accepts a date of birth of today', function () {
    $this->travelTo('2026-11-24 12:00:00');

    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile', patientPersonalInformation([
            'date_of_birth' => '24/11/2026',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.date_of_birth', '2026-11-24');

    expect($user->fresh()->patientProfile->date_of_birth->toDateString())->toBe('2026-11-24');
});

test('profile update accepts a date of birth 120 years ago', function () {
    $this->travelTo('2026-11-24 12:00:00');

    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile', patientPersonalInformation([
            'date_of_birth' => '24/11/1906',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.date_of_birth', '1906-11-24');
});

test('profile update returns 422 when the date of birth is more than 120 years ago', function () {
    $this->travelTo('2026-11-24 12:00:00');

    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile', patientPersonalInformation([
            'date_of_birth' => '23/11/1906',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'date_of_birth' => 'Date of birth is too far in the past.',
        ]);

    expect(PatientProfile::query()->count())->toBe(0);
});

test('profile update stores each gender option', function (string $input, string $stored) {
    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile', patientPersonalInformation([
            'gender' => $input,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.gender', $stored);

    expect($user->fresh()->patientProfile->gender->value)->toBe($stored);
})->with([
    'female' => ['Female', 'female'],
    'male' => ['Male', 'male'],
    'non-binary' => ['Non-binary', 'non_binary'],
    'self-describe' => ['Self-describe', 'self_describe'],
    'prefer not to say' => ['Prefer not to say', 'prefer_not_to_say'],
]);

test('profile update returns 422 when gender is not one of the options', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile', patientPersonalInformation([
            'gender' => 'unknown',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'gender' => 'Choose Female, Male, Non-binary, Self-describe, or Prefer not to say.',
        ]);

    expect(PatientProfile::query()->count())->toBe(0);
});

test('profile update stores a Nigerian phone number without the trunk zero', function (string $phone) {
    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile', patientPersonalInformation([
            'phone' => $phone,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.phone', '+2348012345678');

    expect($user->fresh()->patientProfile->phone)->toBe('+2348012345678');
})->with([
    'national without zero' => '8012345678',
    'national with zero' => '08012345678',
    'country code without plus' => '2348012345678',
]);

test('profile update returns 422 for a phone number that is not a 10-digit Nigerian number', function (string $phone) {
    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile', patientPersonalInformation([
            'phone' => $phone,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'phone' => 'Enter 10 digits after +234 and omit the leading 0.',
        ]);

    expect(PatientProfile::query()->count())->toBe(0);
})->with([
    'too short' => '801234567',
    'other country' => '+15551234567',
    'trunk zero kept after the country code' => '+2340801234567',
]);

test('profile update returns 422 when the account email is changed', function () {
    $user = User::factory()->create([
        'email' => 'adaeze.okonkwo@email.com',
        'name' => 'adaeze.okonkwo',
    ]);

    $profile = PatientProfile::factory()->create([
        'user_id' => $user->id,
        'last_name' => 'Okonkwo',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile', patientPersonalInformation([
            'last_name' => 'Adeyemi',
            'email' => 'other@example.com',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email' => 'Account email cannot be changed here.',
        ]);

    expect($user->fresh()->email)->toBe('adaeze.okonkwo@email.com')
        ->and($user->fresh()->name)->toBe('adaeze.okonkwo')
        ->and($profile->fresh()->last_name)->toBe('Okonkwo');
});

test('profile update ignores a client supplied user id and photo path', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile', patientPersonalInformation([
            'user_id' => $other->id,
            'photo_path' => 'profile-photos/evil.jpg',
            'email_verified_at' => null,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.photo_url', null);

    $profile = $user->fresh()->patientProfile;

    expect($profile->user_id)->toBe($user->id)
        ->and($profile->photo_path)->toBeNull()
        ->and($user->fresh()->email_verified_at)->not->toBeNull()
        ->and($other->fresh()->patientProfile)->toBeNull();
});

test('profile update does not change another patient profile', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherProfile = PatientProfile::factory()->create([
        'user_id' => $other->id,
        'first_name' => 'Other',
        'last_name' => 'Person',
        'phone' => '+2348099999999',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile', patientPersonalInformation())
        ->assertCreated();

    expect($otherProfile->fresh()->first_name)->toBe('Other')
        ->and($otherProfile->fresh()->last_name)->toBe('Person')
        ->and($otherProfile->fresh()->phone)->toBe('+2348099999999')
        ->and($user->fresh()->patientProfile->user_id)->toBe($user->id);
});

test('profile update returns 422 when a photo upload is included', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/keep.jpg', 'keep');

    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Adaeze',
        'last_name' => 'Okonkwo',
        'photo_path' => 'profile-photos/keep.jpg',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile', [
            ...patientPersonalInformation([
                'last_name' => 'Adeyemi',
            ]),
            'photo' => UploadedFile::fake()->image('avatar.png'),
            'remove_photo' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'photo' => 'Upload the profile photo with POST /api/profile/photo.',
            'remove_photo' => 'Remove the profile photo with DELETE /api/profile/photo.',
        ]);

    expect($profile->fresh()->last_name)->toBe('Okonkwo')
        ->and($profile->fresh()->photo_path)->toBe('profile-photos/keep.jpg');

    expect(Storage::disk('public')->allFiles())->toBe(['profile-photos/keep.jpg']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function patientPersonalInformation(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Adaeze',
        'last_name' => 'Okonkwo',
        'date_of_birth' => '14/08/1990',
        'gender' => 'Female',
        'phone' => '+234 801 234 5678',
    ], $overrides);
}
