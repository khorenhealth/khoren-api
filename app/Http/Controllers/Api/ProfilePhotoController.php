<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UploadProfilePhotoRequest;
use App\Http\Resources\ProfileResource;
use App\Models\PatientProfile;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

#[Group('Profile', weight: 3)]
class ProfilePhotoController extends Controller
{
    /**
     * Store or replace the authenticated patient's profile photo.
     */
    #[Endpoint(
        title: 'Upload profile photo',
        description: 'Requires a Sanctum Bearer token for a verified account. Personal information must already be saved. Send multipart/form-data with a JPG or PNG up to 5 MB in the photo field.',
    )]
    #[Response(201, description: 'Photo saved for the first time.')]
    #[Response(200, description: 'Photo replaced.')]
    #[Response(401, description: 'Missing or invalid token.')]
    #[Response(403, description: 'The email address is not verified.')]
    #[Response(422, description: 'The file is missing, is not a JPG or PNG, is larger than 5 MB, or personal information has not been saved.')]
    public function store(UploadProfilePhotoRequest $request): JsonResponse
    {
        $user = $this->verifiedUser($request);
        $profile = $user->patientProfile;

        if (! $profile instanceof PatientProfile) {
            throw ValidationException::withMessages([
                'photo' => 'Save your personal information before uploading a photo.',
            ]);
        }

        $file = $request->file('photo');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'photo' => 'Profile photo could not be saved.',
            ]);
        }

        $previousPhotoPath = $profile->photo_path;
        $storedPhotoPath = $file->store('profile-photos', PatientProfile::PHOTO_DISK);

        if (! is_string($storedPhotoPath)) {
            throw ValidationException::withMessages([
                'photo' => 'Profile photo could not be saved.',
            ]);
        }

        try {
            $profile->forceFill(['photo_path' => $storedPhotoPath])->save();
        } catch (Throwable $exception) {
            Storage::disk(PatientProfile::PHOTO_DISK)->delete($storedPhotoPath);

            throw $exception;
        }

        if (is_string($previousPhotoPath) && $previousPhotoPath !== '' && $previousPhotoPath !== $storedPhotoPath) {
            Storage::disk(PatientProfile::PHOTO_DISK)->delete($previousPhotoPath);
        }

        $replaced = is_string($previousPhotoPath) && $previousPhotoPath !== '';

        return ProfileResource::make($user)
            ->withProfile($profile)
            ->response()
            ->setStatusCode($replaced ? 200 : 201);
    }

    /**
     * Delete the authenticated patient's profile photo.
     */
    #[Endpoint(
        title: 'Remove profile photo',
        description: 'Requires a Sanctum Bearer token for a verified account. Deletes the stored profile photo.',
    )]
    #[Response(200, description: 'Photo removed, or there was no photo to remove.')]
    #[Response(401, description: 'Missing or invalid token.')]
    #[Response(403, description: 'The email address is not verified.')]
    public function destroy(Request $request): ProfileResource
    {
        $user = $this->verifiedUser($request);
        $profile = $user->patientProfile;

        if ($profile instanceof PatientProfile && is_string($profile->photo_path) && $profile->photo_path !== '') {
            Storage::disk(PatientProfile::PHOTO_DISK)->delete($profile->photo_path);
            $profile->forceFill(['photo_path' => null])->save();
        }

        return ProfileResource::make($user)->withProfile($profile);
    }

    private function verifiedUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        if (! $user->hasVerifiedEmail()) {
            throw new AuthorizationException('Email address is not verified.');
        }

        return $user;
    }
}
