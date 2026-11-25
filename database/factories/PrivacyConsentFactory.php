<?php

namespace Database\Factories;

use App\Models\PatientProfile;
use App\Models\PrivacyConsent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivacyConsent>
 */
class PrivacyConsentFactory extends Factory
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
            'care_consent' => true,
            'care_consent_given_at' => '2026-11-24 16:00:00',
            'anonymised_data_sharing' => false,
        ];
    }
}
