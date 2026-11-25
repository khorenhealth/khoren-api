<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateMedicationsRequest;
use App\Http\Resources\MedicationResource;
use App\Models\PatientProfile;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Group('Profile', weight: 3)]
class MedicationController extends Controller
{
    /**
     * Replace the medicines the patient currently reports taking.
     */
    #[Endpoint(
        title: 'Update medications',
        description: 'Saves step 4 medications. This step is optional: send an empty list when the patient is not reporting any medicine. Requires a Sanctum Bearer token for a verified account, and personal information must already be saved. Each item needs a medication name. Strength and frequency are optional. The list replaces any medicines already saved, in the order sent, up to 30. Saving this list does not check allergies, confirm that a medicine is safe, or create an appointment. Upload supporting records separately with POST /api/profile/records.',
    )]
    #[Response(201, description: 'Medications saved for the first time.')]
    #[Response(200, description: 'Medication list replaced, or an empty list was saved.')]
    #[Response(401, description: 'Missing or invalid token.')]
    #[Response(403, description: 'The email address is not verified.')]
    #[Response(422, description: 'Validation failed, or personal information has not been saved.')]
    public function update(UpdateMedicationsRequest $request): JsonResponse
    {
        $user = $this->verifiedUser($request);
        $profile = $user->patientProfile;

        if (! $profile instanceof PatientProfile) {
            throw ValidationException::withMessages([
                'medications' => 'Save your personal information before adding medications.',
            ]);
        }

        /** @var array<int, array{name: string, strength: ?string, frequency: ?string}> $medications */
        $medications = $request->validated('medications');
        $created = ! $profile->medications()->exists() && $medications !== [];

        DB::transaction(function () use ($profile, $medications): void {
            $profile->medications()->delete();

            foreach ($medications as $medication) {
                $profile->medications()->create([
                    'name' => $medication['name'],
                    'strength' => $medication['strength'] ?? null,
                    'frequency' => $medication['frequency'] ?? null,
                ]);
            }
        });

        $profile->unsetRelation('medications');

        return MedicationResource::collection($profile->medications()->orderBy('id')->get())
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
