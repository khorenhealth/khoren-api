<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateEmergencyContactRequest;
use App\Http\Resources\EmergencyContactResource;
use App\Models\EmergencyContact;
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
class EmergencyContactController extends Controller
{
    /**
     * Create or replace the patient's emergency contact.
     */
    #[Endpoint(
        title: 'Update emergency contact',
        description: 'Saves step 5 (emergency contact). Requires a Sanctum Bearer token for a verified account, and personal information must already be saved. An incomplete contact can be saved and finished later: leave full name, relationship, or phone empty and send permission_to_contact false. To finish the step, send all three fields and permission_to_contact true. Relationship accepts Spouse, Parent, Sibling, Friend, Carer, Other, or spouse, parent, sibling, friend, carer, and other. Phone is the contact\'s own Nigerian number: 10 digits after +234, without the leading 0. It must be different from the patient\'s phone. The patient\'s number is not copied. This contact is not given access to the patient\'s health records.',
    )]
    #[Response(201, description: 'Emergency contact saved for the first time.')]
    #[Response(200, description: 'Emergency contact replaced.')]
    #[Response(401, description: 'Missing or invalid token.')]
    #[Response(403, description: 'The email address is not verified.')]
    #[Response(422, description: 'Validation failed, or personal information has not been saved.')]
    public function update(UpdateEmergencyContactRequest $request): JsonResponse
    {
        $user = $this->verifiedUser($request);
        $profile = $user->patientProfile;

        if (! $profile instanceof PatientProfile) {
            throw ValidationException::withMessages([
                'full_name' => 'Save your personal information before adding an emergency contact.',
            ]);
        }

        $contact = $profile->emergencyContact ?? new EmergencyContact;
        $created = ! $contact->exists;

        $contact->fill($request->safe()->only([
            'full_name',
            'relationship',
            'phone',
            'permission_to_contact',
        ]));
        $contact->patientProfile()->associate($profile);
        $contact->save();

        return EmergencyContactResource::make($contact)
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
