<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateNotificationPreferenceRequest;
use App\Http\Resources\NotificationPreferenceResource;
use App\Models\NotificationPreference;
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
class NotificationPreferenceController extends Controller
{
    /**
     * Create or replace the patient's notification preferences.
     */
    #[Endpoint(
        title: 'Update notification preferences',
        description: 'Saves step 6 (notification preferences). Requires a Sanctum Bearer token for a verified account, and personal information must already be saved. Send every toggle. Care updates default to on. Email, SMS, and in-app default to on. Promotions and tips default to off. These preferences do not create visits or medication schedules, and they do not change the account email or phone. Essential account and service messages may still be sent.',
    )]
    #[Response(201, description: 'Notification preferences saved for the first time.')]
    #[Response(200, description: 'Notification preferences replaced.')]
    #[Response(401, description: 'Missing or invalid token.')]
    #[Response(403, description: 'The email address is not verified.')]
    #[Response(422, description: 'Validation failed, or personal information has not been saved.')]
    public function update(UpdateNotificationPreferenceRequest $request): JsonResponse
    {
        $user = $this->verifiedUser($request);
        $profile = $user->patientProfile;

        if (! $profile instanceof PatientProfile) {
            throw ValidationException::withMessages([
                'visit_reminders' => 'Save your personal information before choosing notification preferences.',
            ]);
        }

        $preference = $profile->notificationPreference ?? new NotificationPreference;
        $created = ! $preference->exists;

        /** @var array{email: bool, sms: bool, in_app: bool} $channels */
        $channels = $request->validated('channels');

        $preference->fill([
            'visit_reminders' => $request->boolean('visit_reminders'),
            'medication_reminders' => $request->boolean('medication_reminders'),
            'visit_reports_ready' => $request->boolean('visit_reports_ready'),
            'clinical_alerts' => $request->boolean('clinical_alerts'),
            'channel_email' => $channels['email'],
            'channel_sms' => $channels['sms'],
            'channel_in_app' => $channels['in_app'],
            'promotions' => $request->boolean('promotions'),
        ]);
        $preference->patientProfile()->associate($profile);
        $preference->save();

        return NotificationPreferenceResource::make($preference)
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
