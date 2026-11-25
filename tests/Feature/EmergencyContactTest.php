<?php

use App\Enums\EmergencyContactRelationship;
use App\Models\EmergencyContact;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('emergency contact update returns 401 when no token is provided', function () {
    $this->putJson('/api/profile/emergency-contact', emergencyContact())
        ->assertUnauthorized();

    expect(EmergencyContact::query()->count())->toBe(0);
});

test('emergency contact update returns 403 when the email is not verified', function () {
    $user = User::factory()->unverified()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/emergency-contact', emergencyContact())
        ->assertForbidden()
        ->assertJsonPath('message', 'Email address is not verified.');

    expect(EmergencyContact::query()->count())->toBe(0);
});

test('emergency contact update returns 422 when personal information has not been saved', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/emergency-contact', emergencyContact())
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'full_name' => 'Save your personal information before adding an emergency contact.',
        ]);

    expect(EmergencyContact::query()->count())->toBe(0);
});

test('emergency contact update saves an incomplete contact', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create([
        'user_id' => $user->id,
        'phone' => '+2348012345678',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/emergency-contact', [
            'full_name' => 'Mr. Chidi Okonkwo',
            'relationship' => 'Spouse',
            'phone' => '',
            'permission_to_contact' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.full_name', 'Mr. Chidi Okonkwo')
        ->assertJsonPath('data.relationship', 'spouse')
        ->assertJsonPath('data.phone', null)
        ->assertJsonPath('data.permission_to_contact', false);

    $contact = $profile->fresh()->emergencyContact;

    expect($contact)->not->toBeNull()
        ->and($contact->phone)->toBeNull()
        ->and($contact->phone)->not->toBe($profile->phone)
        ->and($contact->permission_to_contact)->toBeFalse();
});

test('emergency contact update returns 422 when permission is granted without the contact details', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/emergency-contact', [
            'full_name' => ' ',
            'relationship' => '',
            'phone' => '',
            'permission_to_contact' => true,
        ])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors([
            'full_name' => 'Contact full name is required.',
            'relationship' => 'Relationship is required.',
            'phone' => 'Enter a valid contact phone number.',
        ]);

    expect(EmergencyContact::query()->count())->toBe(0);
});

test('emergency contact update returns 422 when the relationship is not in the list', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/emergency-contact', emergencyContact([
            'relationship' => 'Cousin',
            'permission_to_contact' => false,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'relationship' => 'Choose Spouse, Parent, Sibling, Friend, Carer, or Other.',
        ]);

    expect(EmergencyContact::query()->count())->toBe(0);
});

test('emergency contact update returns 422 when the phone number is not a 10-digit Nigerian number', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create([
        'user_id' => $user->id,
        'phone' => '+2348012345678',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/emergency-contact', emergencyContact([
            'phone' => '+23408095550142',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'phone' => 'Enter 10 digits after +234 and omit the leading 0.',
        ]);

    expect(EmergencyContact::query()->count())->toBe(0);
});

test('emergency contact update returns 422 when the phone matches the patient phone', function (string $phone) {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create([
        'user_id' => $user->id,
        'phone' => '+2348012345678',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/emergency-contact', emergencyContact([
            'phone' => $phone,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'phone' => 'Enter a separate number belonging to your emergency contact.',
        ]);

    expect($profile->fresh()->emergencyContact)->toBeNull();
})->with([
    'same stored number' => ['+2348012345678'],
    'same number with spaces' => ['+234 801 234 5678'],
    'same number with a trunk zero' => ['08012345678'],
]);

test('emergency contact update creates the contact and returns 201', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Adaeze',
        'phone' => '+2348012345678',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/emergency-contact', emergencyContact())
        ->assertCreated()
        ->assertJsonPath('data.full_name', 'Mr. Chidi Okonkwo')
        ->assertJsonPath('data.relationship', 'spouse')
        ->assertJsonPath('data.phone', '+2348095550142')
        ->assertJsonPath('data.permission_to_contact', true);

    $contact = $profile->fresh()->emergencyContact;

    expect($contact)->not->toBeNull()
        ->and($contact->patient_profile_id)->toBe($profile->id)
        ->and($contact->relationship)->toBe(EmergencyContactRelationship::Spouse)
        ->and($contact->phone)->toBe('+2348095550142')
        ->and($contact->permission_to_contact)->toBeTrue()
        ->and($profile->fresh()->first_name)->toBe('Adaeze')
        ->and($profile->fresh()->phone)->toBe('+2348012345678');

    $this->app['auth']->forgetGuards();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('data.emergency_contact.full_name', 'Mr. Chidi Okonkwo')
        ->assertJsonPath('data.emergency_contact.phone', '+2348095550142')
        ->assertJsonPath('data.phone', '+2348012345678');
});

test('emergency contact update returns 200 and replaces the contact', function (string $method) {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create([
        'user_id' => $user->id,
        'phone' => '+2348012345678',
    ]);
    EmergencyContact::factory()->create([
        'patient_profile_id' => $profile->id,
        'full_name' => 'Old contact',
        'phone' => '+2348090000000',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->json($method, '/api/profile/emergency-contact', emergencyContact([
            'full_name' => ' Mrs. Amaka Okonkwo ',
            'relationship' => 'Parent',
            'phone' => '0903 222 4455',
        ]))
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Mrs. Amaka Okonkwo')
        ->assertJsonPath('data.relationship', 'parent')
        ->assertJsonPath('data.phone', '+2349032224455');

    expect($profile->fresh()->emergencyContact)->not->toBeNull()
        ->and(EmergencyContact::query()->count())->toBe(1)
        ->and($profile->fresh()->emergencyContact->full_name)->toBe('Mrs. Amaka Okonkwo');
})->with(['PUT', 'PATCH']);

test('emergency contact update returns 422 when the name is too long', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/emergency-contact', emergencyContact([
            'full_name' => str_repeat('A', 256),
            'permission_to_contact' => false,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'full_name' => 'Contact full name must be 255 characters or fewer.',
        ]);

    expect(EmergencyContact::query()->count())->toBe(0);
});

test('emergency contact update ignores a client supplied profile id', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create([
        'user_id' => $user->id,
        'phone' => '+2348012345678',
    ]);
    $otherProfile = PatientProfile::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/emergency-contact', emergencyContact([
            'patient_profile_id' => $otherProfile->id,
        ]))
        ->assertCreated();

    expect($profile->fresh()->emergencyContact)->not->toBeNull()
        ->and($profile->fresh()->emergencyContact->patient_profile_id)->toBe($profile->id)
        ->and($otherProfile->fresh()->emergencyContact)->toBeNull();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function emergencyContact(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Mr. Chidi Okonkwo',
        'relationship' => 'Spouse',
        'phone' => '+234 809 555 0142',
        'permission_to_contact' => true,
    ], $overrides);
}
