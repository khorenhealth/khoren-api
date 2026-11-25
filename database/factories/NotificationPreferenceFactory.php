<?php

namespace Database\Factories;

use App\Models\NotificationPreference;
use App\Models\PatientProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_profile_id' => PatientProfile::factory(),
            'visit_reminders' => true,
            'medication_reminders' => true,
            'visit_reports_ready' => true,
            'clinical_alerts' => true,
            'channel_email' => true,
            'channel_sms' => true,
            'channel_in_app' => true,
            'promotions' => false,
        ];
    }
}
