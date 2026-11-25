<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Models\PatientProfile;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

#[Group('Profile', weight: 3)]
class ProfileController extends Controller
{
    /**
     * Return the authenticated patient's personal information.
     */
    #[Endpoint(
        title: 'Show profile',
        description: 'Requires a Sanctum Bearer token for a verified account. Personal information, the home visit address, medical information, and the emergency contact are null until each step is saved. Medications and supporting records are empty lists until the patient adds them. Notification preferences use the form defaults until saved: care updates on, email, SMS, and in-app on, and promotions off. Privacy choices default to care consent off and anonymised data sharing off. Setup is incomplete until the accuracy confirmation is accepted. Email is the sign-in address and is read-only.',
    )]
    #[Response(200, description: 'The patient profile, or empty personal-information fields when step 1 has not been saved.')]
    #[Response(401, description: 'Missing or invalid token.')]
    #[Response(403, description: 'The email address is not verified.')]
    public function show(Request $request): ProfileResource
    {
        $user = $this->verifiedUser($request);
        $user->load([
            'patientProfile.homeVisit',
            'patientProfile.medicalInformation',
            'patientProfile.medications',
            'patientProfile.supportingRecords',
            'patientProfile.emergencyContact',
            'patientProfile.notificationPreference',
            'patientProfile.privacyConsent',
            'patientProfile.profileCompletion',
        ]);

        return ProfileResource::make($user)->withProfile($user->patientProfile);
    }

    /**
     * Create or replace personal information for the authenticated patient.
     */
    #[Endpoint(
        title: 'Update profile',
        description: 'Saves step 1 (personal information). Send JSON with POST, PUT, or PATCH. Date of birth accepts DD/MM/YYYY or YYYY-MM-DD. Phone is a Nigerian number: 10 digits after +234, without the leading 0. Gender accepts female, male, non_binary, self_describe, prefer_not_to_say, or the form labels. Email is read-only; omit it or repeat the current address. Upload or remove the photo with POST or DELETE /api/profile/photo. Save the home visit address with /api/profile/home-visit. Save medical information with /api/profile/medical-information. Save medications with /api/profile/medications. Upload supporting records with POST /api/profile/records. Save the emergency contact with /api/profile/emergency-contact. Save notification preferences with /api/profile/notifications. Save privacy choices with /api/profile/privacy. Complete setup with /api/profile/completion.',
    )]
    #[Response(201, description: 'Personal information saved for the first time.')]
    #[Response(200, description: 'Personal information replaced.')]
    #[Response(401, description: 'Missing or invalid token.')]
    #[Response(403, description: 'The email address is not verified.')]
    #[Response(422, description: 'Validation failed.')]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->verifiedUser($request);
        $profile = $user->patientProfile ?? new PatientProfile;
        $created = ! $profile->exists;

        $attributes = $request->safe()->only([
            'first_name',
            'last_name',
            'date_of_birth',
            'gender',
            'phone',
        ]);

        DB::transaction(function () use ($profile, $user, $attributes): void {
            $profile->fill($attributes);
            $profile->user()->associate($user);
            $profile->save();

            $user->forceFill([
                'name' => $profile->first_name.' '.$profile->last_name,
            ])->save();
        });

        return ProfileResource::make($user)
            ->withProfile($profile)
            ->response()
            ->setStatusCode($created ? 201 : 200);
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
