<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateMedicalInformationRequest;
use App\Http\Resources\MedicalInformationResource;
use App\Models\MedicalInformation;
use App\Models\PatientProfile;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

#[Group('Profile', weight: 3)]
class MedicalInformationController extends Controller
{
    /**
     * Create or replace self-reported medical information.
     */
    #[Endpoint(
        title: 'Update medical information',
        description: 'Saves step 3 (medical information). Requires a Sanctum Bearer token for a verified account, and personal information must already be saved. These details are self-reported and are not clinically verified. Blood group accepts A+, A-, B+, B-, AB+, AB-, O+, O-, Unknown, or the stored values a_positive, a_negative, b_positive, b_negative, ab_positive, ab_negative, o_positive, o_negative, and unknown. Allergy status accepts Known allergies, None known, Unknown, or known_allergies, none_known, and unknown. Known allergies are required only when the status is Known allergies. Choosing None known or Unknown clears any previously saved allergies. Other relevant conditions are optional. Saving this does not create an appointment.',
    )]
    #[Response(201, description: 'Medical information saved for the first time.')]
    #[Response(200, description: 'Medical information replaced.')]
    #[Response(401, description: 'Missing or invalid token.')]
    #[Response(403, description: 'The email address is not verified.')]
    #[Response(422, description: 'Validation failed, or personal information has not been saved.')]
    public function update(UpdateMedicalInformationRequest $request): JsonResponse
    {
        $user = $this->verifiedUser($request);
        $profile = $user->patientProfile;

        if (! $profile instanceof PatientProfile) {
            throw ValidationException::withMessages([
                'primary_condition' => 'Save your personal information before adding medical information.',
            ]);
        }

        $record = $profile->medicalInformation ?? new MedicalInformation;
        $created = ! $record->exists;

        $record->fill($request->safe()->only([
            'primary_condition',
            'blood_group',
            'allergy_status',
            'known_allergies',
            'other_conditions',
        ]));
        $record->patientProfile()->associate($profile);
        $record->save();

        return MedicalInformationResource::make($record)
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
