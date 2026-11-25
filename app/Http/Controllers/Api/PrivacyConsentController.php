<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePrivacyConsentRequest;
use App\Http\Resources\PrivacyConsentResource;
use App\Models\PatientProfile;
use App\Models\PrivacyConsent;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

#[Group('Profile', weight: 3)]
class PrivacyConsentController extends Controller
{
    /**
     * Create or replace the patient's privacy choices.
     */
    #[Endpoint(
        title: 'Update privacy consent',
        description: 'Saves step 7 (privacy and consent). Requires a Sanctum Bearer token for a verified account, and personal information must already be saved. care_consent may be false when the patient saves and exits. It must be true to finish the profile. The time of the latest acceptance is stored and cannot be set by the client. anonymised_data_sharing is optional and defaults to off. Leaving it off does not affect care. Saving this step does not assign a care provider. Account terms accepted at sign-up are separate from this care consent.',
    )]
    #[Response(201, description: 'Privacy choices saved for the first time.')]
    #[Response(200, description: 'Privacy choices replaced.')]
    #[Response(401, description: 'Missing or invalid token.')]
    #[Response(403, description: 'The email address is not verified.')]
    #[Response(422, description: 'Validation failed, or personal information has not been saved.')]
    public function update(UpdatePrivacyConsentRequest $request): JsonResponse
    {
        $user = $this->verifiedUser($request);
        $profile = $user->patientProfile;

        if (! $profile instanceof PatientProfile) {
            throw ValidationException::withMessages([
                'care_consent' => 'Save your personal information before saving privacy choices.',
            ]);
        }

        $consent = $profile->privacyConsent()->first() ?? new PrivacyConsent;
        $created = ! $consent->exists;
        $careConsent = $request->boolean('care_consent');

        $consent->fill([
            'care_consent' => $careConsent,
            'care_consent_given_at' => $this->careConsentGivenAt($consent, $careConsent),
            'anonymised_data_sharing' => $request->boolean('anonymised_data_sharing'),
        ]);
        $consent->patientProfile()->associate($profile);
        $consent->save();

        return PrivacyConsentResource::make($consent)
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    private function careConsentGivenAt(PrivacyConsent $consent, bool $careConsent): ?\DateTimeInterface
    {
        if (! $careConsent) {
            return null;
        }

        if ($consent->exists && $consent->care_consent && $consent->care_consent_given_at !== null) {
            return $consent->care_consent_given_at;
        }

        return now();
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
