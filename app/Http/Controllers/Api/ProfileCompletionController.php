<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\CompleteProfileRequest;
use App\Http\Resources\ProfileCompletionResource;
use App\Models\PatientProfile;
use App\Models\PrivacyConsent;
use App\Models\ProfileCompletion;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

#[Group('Profile', weight: 3)]
class ProfileCompletionController extends Controller
{
    /**
     * Confirm the profile is accurate and complete setup.
     */
    #[Endpoint(
        title: 'Complete profile setup',
        description: 'Saves step 8 and completes setup. Requires a Sanctum Bearer token for a verified account, saved personal information, and accepted care-related consent. accuracy_confirmed must be true. The confirmation time and setup completion time are stored by the server. Sending the confirmation again keeps the original times. Completing setup marks the profile complete. It does not assign a care provider, create a visit, or take payment. A care team reviews self-reported health information separately.',
    )]
    #[Response(201, description: 'Setup completed.')]
    #[Response(200, description: 'Setup was already complete. The original completion time is unchanged.')]
    #[Response(401, description: 'Missing or invalid token.')]
    #[Response(403, description: 'The email address is not verified.')]
    #[Response(422, description: 'The confirmation was not accepted, personal information is missing, or care consent has not been accepted.')]
    public function update(CompleteProfileRequest $request): JsonResponse
    {
        $user = $this->verifiedUser($request);
        $profile = $user->patientProfile;

        if (! $profile instanceof PatientProfile) {
            throw ValidationException::withMessages([
                'accuracy_confirmed' => 'Save your personal information before completing setup.',
            ]);
        }

        $consent = $profile->privacyConsent()->first();

        if (! $consent instanceof PrivacyConsent || ! $consent->care_consent) {
            throw ValidationException::withMessages([
                'accuracy_confirmed' => 'Accept care-related consent before completing setup.',
            ]);
        }

        $completion = $profile->profileCompletion()->first() ?? new ProfileCompletion;
        $created = ! $completion->exists;
        $confirmedAt = $completion->accuracy_confirmed_at ?? now();

        $completion->fill([
            'accuracy_confirmed' => true,
            'accuracy_confirmed_at' => $confirmedAt,
            'setup_completed_at' => $completion->setup_completed_at ?? $confirmedAt,
        ]);
        $completion->patientProfile()->associate($profile);
        $completion->save();

        return ProfileCompletionResource::make($completion)
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
