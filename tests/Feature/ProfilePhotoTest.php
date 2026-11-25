<?php

use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('profile photo upload returns 401 when no token is provided', function () {
    $this->postJson('/api/profile/photo')->assertUnauthorized();
});

test('profile photo upload returns 403 when the email is not verified', function () {
    $user = User::factory()->unverified()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->image('avatar.png'),
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Email address is not verified.');
});

test('profile photo upload returns 422 when personal information has not been saved', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->image('avatar.png'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'photo' => 'Save your personal information before uploading a photo.',
        ]);

    Storage::disk('public')->assertDirectoryEmpty('profile-photos');
});

test('profile photo upload returns 422 when the photo is missing', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile/photo', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'photo' => 'Profile photo is required.',
        ]);
});

test('profile photo upload stores a png and returns 201', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Adaeze',
        'last_name' => 'Okonkwo',
    ]);

    $response = $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->image('avatar.png'),
        ]);

    $profile->refresh();

    $response->assertCreated()
        ->assertJsonPath('data.first_name', 'Adaeze')
        ->assertJsonPath('data.last_name', 'Okonkwo')
        ->assertJsonPath('data.photo_url', Storage::disk('public')->url($profile->photo_path));

    Storage::disk('public')->assertExists($profile->photo_path);
    expect($profile->photo_path)->toStartWith('profile-photos/');

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('data.photo_url', Storage::disk('public')->url($profile->photo_path));
});

test('profile photo upload returns 422 when the photo is not a jpg or png', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->image('avatar.gif'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'photo' => 'Profile photo must be a JPG or PNG.',
        ]);

    expect($user->fresh()->patientProfile->photo_path)->toBeNull();
    Storage::disk('public')->assertDirectoryEmpty('profile-photos');
});

test('profile photo upload returns 422 when the photo is larger than 5 MB', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->image('avatar.jpg')->size(5121),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'photo' => 'Profile photo must be 5 MB or smaller.',
        ]);

    expect($user->fresh()->patientProfile->photo_path)->toBeNull();
});

test('profile photo upload replaces the previous photo and returns 200', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/old.jpg', 'old-photo');

    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Adaeze',
        'photo_path' => 'profile-photos/old.jpg',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->image('avatar.jpg'),
        ])
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Adaeze');

    $profile->refresh();

    Storage::disk('public')->assertMissing('profile-photos/old.jpg');
    Storage::disk('public')->assertExists($profile->photo_path);
    expect($profile->photo_path)->not->toBe('profile-photos/old.jpg');
});

test('profile photo upload does not change another patient photo', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/other.jpg', 'other-photo');

    $user = User::factory()->create();
    $other = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);
    $otherProfile = PatientProfile::factory()->create([
        'user_id' => $other->id,
        'photo_path' => 'profile-photos/other.jpg',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile/photo', [
            'photo' => UploadedFile::fake()->image('avatar.png'),
        ])
        ->assertCreated();

    expect($otherProfile->fresh()->photo_path)->toBe('profile-photos/other.jpg');
    Storage::disk('public')->assertExists('profile-photos/other.jpg');
});

test('profile photo removal returns 401 when no token is provided', function () {
    $this->deleteJson('/api/profile/photo')->assertUnauthorized();
});

test('profile photo removal returns 403 when the email is not verified', function () {
    $user = User::factory()->unverified()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->deleteJson('/api/profile/photo')
        ->assertForbidden()
        ->assertJsonPath('message', 'Email address is not verified.');
});

test('profile photo removal deletes the stored photo', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/old.jpg', 'old-photo');

    $user = User::factory()->create();
    PatientProfile::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Adaeze',
        'photo_path' => 'profile-photos/old.jpg',
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->deleteJson('/api/profile/photo')
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Adaeze')
        ->assertJsonPath('data.photo_url', null);

    expect($user->fresh()->patientProfile->photo_path)->toBeNull();
    Storage::disk('public')->assertMissing('profile-photos/old.jpg');
});

test('profile photo removal returns 200 when there is no photo', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create([
        'user_id' => $user->id,
        'photo_path' => null,
    ]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->deleteJson('/api/profile/photo')
        ->assertOk()
        ->assertJsonPath('data.photo_url', null);
});
