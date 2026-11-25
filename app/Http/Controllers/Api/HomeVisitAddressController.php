<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateHomeVisitAddressRequest;
use App\Http\Resources\HomeVisitAddressResource;
use App\Models\HomeVisitAddress;
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
class HomeVisitAddressController extends Controller
{
    /**
     * Create or replace the address used for home visits.
     */
    #[Endpoint(
        title: 'Update home visit address',
        description: 'Saves step 2 (home visit address). Requires a Sanctum Bearer token for a verified account, and personal information must already be saved. Country is required. When the country is Nigeria, state and local government area must be a supported location. Other countries accept a state and local government area as text. Landmark and access directions are optional. Saving an address does not create an appointment.',
    )]
    #[Response(201, description: 'Home visit address saved for the first time.')]
    #[Response(200, description: 'Home visit address replaced.')]
    #[Response(401, description: 'Missing or invalid token.')]
    #[Response(403, description: 'The email address is not verified.')]
    #[Response(422, description: 'Validation failed, or personal information has not been saved.')]
    public function update(UpdateHomeVisitAddressRequest $request): JsonResponse
    {
        $user = $this->verifiedUser($request);
        $profile = $user->patientProfile;

        if (! $profile instanceof PatientProfile) {
            throw ValidationException::withMessages([
                'street_address' => 'Save your personal information before adding a home visit address.',
            ]);
        }

        $address = $profile->homeVisit ?? new HomeVisitAddress;
        $created = ! $address->exists;

        $address->fill($request->safe()->only([
            'street_address',
            'state',
            'local_government_area',
            'country',
            'landmark',
            'access_directions',
        ]));
        $address->patientProfile()->associate($profile);
        $address->save();

        return HomeVisitAddressResource::make($address)
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
