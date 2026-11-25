<?php

use App\Models\HomeVisitAddress;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('home visit address update returns 401 when no token is provided', function () {
    $this->putJson('/api/profile/home-visit', homeVisitAddress())
        ->assertUnauthorized();

    expect(HomeVisitAddress::query()->count())->toBe(0);
});

test('home visit address update returns 403 when the email is not verified', function () {
    $user = User::factory()->unverified()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/home-visit', homeVisitAddress())
        ->assertForbidden()
        ->assertJsonPath('message', 'Email address is not verified.');

    expect(HomeVisitAddress::query()->count())->toBe(0);
});

test('home visit address update returns 422 when personal information has not been saved', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/home-visit', homeVisitAddress())
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'street_address' => 'Save your personal information before adding a home visit address.',
        ]);

    expect(HomeVisitAddress::query()->count())->toBe(0);
});

test('home visit address update returns 422 when required fields are blank', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/home-visit', [
            'street_address' => ' ',
            'state' => '',
            'local_government_area' => '',
            'country' => ' ',
        ])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors([
            'street_address' => 'Street address is required.',
            'state' => 'State is required.',
            'local_government_area' => 'Local government area is required.',
            'country' => 'Country is required.',
        ]);

    expect(HomeVisitAddress::query()->count())->toBe(0);
});

test('home visit address update creates the address and returns 201', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Adaeze',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/home-visit', homeVisitAddress([
            'country' => 'Nigeria',
            'landmark' => 'Opposite the admiralty gate',
            'access_directions' => 'Use the side gate. There is a lift to the third floor.',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.street_address', '5 Admiralty Way, Lekki Phase 1, Lagos')
        ->assertJsonPath('data.state', 'Lagos')
        ->assertJsonPath('data.local_government_area', 'Eti-Osa')
        ->assertJsonPath('data.country', 'Nigeria')
        ->assertJsonPath('data.landmark', 'Opposite the admiralty gate')
        ->assertJsonPath('data.access_directions', 'Use the side gate. There is a lift to the third floor.');

    $address = $profile->fresh()->homeVisit;

    expect($address)->not->toBeNull()
        ->and($address->patient_profile_id)->toBe($profile->id)
        ->and($address->street_address)->toBe('5 Admiralty Way, Lekki Phase 1, Lagos')
        ->and($address->state)->toBe('Lagos')
        ->and($address->local_government_area)->toBe('Eti-Osa')
        ->and($address->country)->toBe('Nigeria')
        ->and($profile->fresh()->first_name)->toBe('Adaeze');

    // The previous request cached the profile before the address existed.
    $this->app['auth']->forgetGuards();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('data.home_visit.street_address', '5 Admiralty Way, Lekki Phase 1, Lagos')
        ->assertJsonPath('data.home_visit.country', 'Nigeria');
});

test('home visit address update returns 200 and replaces the address', function (string $method) {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    HomeVisitAddress::factory()->create([
        'patient_profile_id' => $profile->id,
        'landmark' => 'Old landmark',
        'access_directions' => 'Old directions',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->json($method, '/api/profile/home-visit', homeVisitAddress([
            'street_address' => '12 Bourdillon Road, Ikoyi',
            'local_government_area' => 'Lagos Island',
        ]))
        ->assertOk()
        ->assertJsonPath('data.street_address', '12 Bourdillon Road, Ikoyi')
        ->assertJsonPath('data.local_government_area', 'Lagos Island')
        ->assertJsonPath('data.landmark', null)
        ->assertJsonPath('data.access_directions', null);

    $address = $profile->fresh()->homeVisit;

    expect($address->street_address)->toBe('12 Bourdillon Road, Ikoyi')
        ->and($address->state)->toBe('Lagos')
        ->and($address->local_government_area)->toBe('Lagos Island')
        ->and($address->landmark)->toBeNull()
        ->and($address->access_directions)->toBeNull();
})->with(['PUT', 'PATCH']);

test('home visit address update stores the canonical state and local government area', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/home-visit', homeVisitAddress([
            'state' => 'lagos',
            'local_government_area' => 'eti-osa',
            'country' => 'nigeria',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.state', 'Lagos')
        ->assertJsonPath('data.local_government_area', 'Eti-Osa')
        ->assertJsonPath('data.country', 'Nigeria');

    expect($profile->fresh()->homeVisit->state)->toBe('Lagos')
        ->and($profile->fresh()->homeVisit->local_government_area)->toBe('Eti-Osa');
});

test('home visit address update returns 422 when the state is not supported', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/home-visit', homeVisitAddress([
            'state' => 'Oyo',
            'local_government_area' => 'Ibadan North',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'state' => 'Home visits are currently available in supported Nigerian locations.',
        ])
        ->assertJsonMissingValidationErrors(['local_government_area']);

    expect(HomeVisitAddress::query()->count())->toBe(0);
});

test('home visit address update returns 422 when the local government area is outside the state', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/home-visit', homeVisitAddress([
            'local_government_area' => 'Ibadan North',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'local_government_area' => 'Choose a local government area in the selected state.',
        ]);

    expect(HomeVisitAddress::query()->count())->toBe(0);
});

test('home visit address update stores a country outside Nigeria', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/home-visit', homeVisitAddress([
            'street_address' => '14 Independence Avenue, Accra',
            'state' => 'Greater Accra',
            'local_government_area' => 'Accra Metropolitan',
            'country' => 'Ghana',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.street_address', '14 Independence Avenue, Accra')
        ->assertJsonPath('data.state', 'Greater Accra')
        ->assertJsonPath('data.local_government_area', 'Accra Metropolitan')
        ->assertJsonPath('data.country', 'Ghana');

    expect($profile->fresh()->homeVisit->country)->toBe('Ghana')
        ->and($profile->fresh()->homeVisit->state)->toBe('Greater Accra');
});

test('home visit address update returns 422 when the street address is too long', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->putJson('/api/profile/home-visit', homeVisitAddress([
            'street_address' => str_repeat('A', 256),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'street_address' => 'Street address must be 255 characters or fewer.',
        ]);

    expect(HomeVisitAddress::query()->count())->toBe(0);
});

test('home visit address update ignores a client supplied profile id', function () {
    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    $other = User::factory()->create();
    $otherProfile = PatientProfile::factory()->create(['user_id' => $other->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->postJson('/api/profile/home-visit', homeVisitAddress([
            'patient_profile_id' => $otherProfile->id,
        ]))
        ->assertCreated();

    expect($profile->fresh()->homeVisit)->not->toBeNull()
        ->and($profile->fresh()->homeVisit->patient_profile_id)->toBe($profile->id)
        ->and($otherProfile->fresh()->homeVisit)->toBeNull();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function homeVisitAddress(array $overrides = []): array
{
    return array_merge([
        'street_address' => '5 Admiralty Way, Lekki Phase 1, Lagos',
        'state' => 'Lagos',
        'local_government_area' => 'Eti-Osa',
        'country' => 'Nigeria',
    ], $overrides);
}
