<?php

use App\Models\PatientProfile;
use App\Models\SupportingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('supporting record upload returns 401 when no token is provided', function () {
    $this->postJson('/api/profile/records')->assertUnauthorized();

    expect(SupportingRecord::query()->count())->toBe(0);
});

test('supporting record upload returns 403 when the email is not verified', function () {
    Storage::fake('local');

    $user = User::factory()->unverified()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile/records', [
            'file' => UploadedFile::fake()->create('HospitalDischargeSummary.pdf', 245, 'application/pdf'),
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Email address is not verified.');

    expect(SupportingRecord::query()->count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('supporting-records');
});

test('supporting record upload returns 422 when personal information has not been saved', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile/records', [
            'file' => UploadedFile::fake()->create('HospitalDischargeSummary.pdf', 245, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'file' => 'Save your personal information before uploading a supporting record.',
        ]);

    Storage::disk('local')->assertDirectoryEmpty('supporting-records');
});

test('supporting record upload returns 422 when the file is missing', function () {
    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile/records', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'file' => 'Choose a supporting record to upload.',
        ]);
});

test('supporting record upload returns 422 when the file is not a pdf or image', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile/records', [
            'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'file' => 'Supporting records must be a PDF, JPG, or PNG.',
        ]);

    expect(SupportingRecord::query()->count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('supporting-records');
});

test('supporting record upload returns 422 when the file is larger than 10 MB', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile/records', [
            'file' => UploadedFile::fake()->create('HospitalDischargeSummary.pdf', 10241, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'file' => 'Supporting records must be 10 MB or smaller.',
        ]);

    expect(SupportingRecord::query()->count())->toBe(0);
});

test('supporting record upload stores a pdf and returns 201', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);

    $response = $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile/records', [
            'file' => UploadedFile::fake()->create('HospitalDischargeSummary.pdf', 245, 'application/pdf'),
        ]);

    $record = $profile->fresh()->supportingRecords->first();

    $response->assertCreated()
        ->assertJsonPath('data.id', $record->id)
        ->assertJsonPath('data.name', 'HospitalDischargeSummary.pdf')
        ->assertJsonPath('data.mime_type', 'application/pdf')
        ->assertJsonPath('data.size', 250880)
        ->assertJsonPath('data.url', '/api/profile/records/'.$record->id);

    Storage::disk('local')->assertExists($record->path);
    expect($record->path)->toStartWith('supporting-records/')
        ->and($record->path)->not->toContain('HospitalDischargeSummary')
        ->and($record->patient_profile_id)->toBe($profile->id);

    $this->app['auth']->forgetGuards();

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('data.supporting_records.0.name', 'HospitalDischargeSummary.pdf')
        ->assertJsonPath('data.supporting_records.0.url', '/api/profile/records/'.$record->id);
});

test('supporting record upload adds a png without replacing an existing file', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('auth')->plainTextToken;

    $this->withToken($token)
        ->post('/api/profile/records', [
            'file' => UploadedFile::fake()->create('HospitalDischargeSummary.pdf', 20, 'application/pdf'),
        ])
        ->assertCreated();

    $this->withToken($token)
        ->post('/api/profile/records', [
            'file' => UploadedFile::fake()->image('Pre-opBloodTestResults.png'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Pre-opBloodTestResults.png');

    $records = $profile->fresh()->supportingRecords;

    expect($records)->toHaveCount(2)
        ->and($records[0]->original_name)->toBe('HospitalDischargeSummary.pdf')
        ->and($records[1]->original_name)->toBe('Pre-opBloodTestResults.png');

    Storage::disk('local')->assertExists($records[0]->path);
    Storage::disk('local')->assertExists($records[1]->path);
});

test('supporting record upload stores only the file name', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->post('/api/profile/records', [
            'file' => UploadedFile::fake()->create('../../HospitalDischargeSummary.pdf', 12, 'application/pdf'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'HospitalDischargeSummary.pdf');

    expect($profile->fresh()->supportingRecords->first()->path)->not->toContain('..');
});

test('supporting record view streams the file to its owner', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('auth')->plainTextToken;

    $id = $this->withToken($token)
        ->post('/api/profile/records', [
            'file' => UploadedFile::fake()->create('HospitalDischargeSummary.pdf', 12, 'application/pdf'),
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->get('/api/profile/records/'.$id)
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition');

    $disposition = (string) $this->withToken($token)
        ->get('/api/profile/records/'.$id)
        ->headers->get('content-disposition');

    expect($disposition)->toContain('inline')
        ->and($disposition)->toContain('HospitalDischargeSummary.pdf');
});

test('supporting record view returns 401 when no token is provided', function () {
    $record = SupportingRecord::factory()->create();

    $this->getJson('/api/profile/records/'.$record->id)->assertUnauthorized();
});

test('supporting record view returns 403 when the email is not verified', function () {
    $user = User::factory()->unverified()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    $record = SupportingRecord::factory()->create(['patient_profile_id' => $profile->id]);

    $this->withToken($user->createToken('auth')->plainTextToken)
        ->getJson('/api/profile/records/'.$record->id)
        ->assertForbidden()
        ->assertJsonPath('message', 'Email address is not verified.');
});

test('supporting record view returns 404 for another patient', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $owner->id]);
    $other = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $other->id]);

    $id = $this->withToken($owner->createToken('auth')->plainTextToken)
        ->post('/api/profile/records', [
            'file' => UploadedFile::fake()->create('HospitalDischargeSummary.pdf', 12, 'application/pdf'),
        ])
        ->json('data.id');

    // The previous request cached the owner on the auth guard.
    $this->app['auth']->forgetGuards();

    $this->withToken($other->createToken('auth')->plainTextToken)
        ->getJson('/api/profile/records/'.$id)
        ->assertNotFound();
});

test('supporting record delete removes the file and returns the remaining list', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $profile = PatientProfile::factory()->create(['user_id' => $user->id]);
    $token = $user->createToken('auth')->plainTextToken;

    $first = $this->withToken($token)
        ->post('/api/profile/records', [
            'file' => UploadedFile::fake()->create('HospitalDischargeSummary.pdf', 12, 'application/pdf'),
        ])
        ->json('data.id');

    $second = $this->withToken($token)
        ->post('/api/profile/records', [
            'file' => UploadedFile::fake()->create('SurgicalProcedureNotes.pdf', 12, 'application/pdf'),
        ])
        ->json('data.id');

    $removed = SupportingRecord::query()->findOrFail($first);

    $this->withToken($token)
        ->deleteJson('/api/profile/records/'.$first)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $second)
        ->assertJsonPath('data.0.name', 'SurgicalProcedureNotes.pdf');

    Storage::disk('local')->assertMissing($removed->path);
    expect($profile->fresh()->supportingRecords)->toHaveCount(1);
});

test('supporting record delete returns 404 for another patient', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    PatientProfile::factory()->create(['user_id' => $owner->id]);
    $other = User::factory()->create();

    $id = $this->withToken($owner->createToken('auth')->plainTextToken)
        ->post('/api/profile/records', [
            'file' => UploadedFile::fake()->create('HospitalDischargeSummary.pdf', 12, 'application/pdf'),
        ])
        ->json('data.id');

    $path = SupportingRecord::query()->findOrFail($id)->path;

    // The previous request cached the owner on the auth guard.
    $this->app['auth']->forgetGuards();

    $this->withToken($other->createToken('auth')->plainTextToken)
        ->deleteJson('/api/profile/records/'.$id)
        ->assertNotFound();

    Storage::disk('local')->assertExists($path);
    expect(SupportingRecord::query()->find($id))->not->toBeNull();
});
