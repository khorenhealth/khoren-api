<?php

namespace Database\Factories;

use App\Models\PatientProfile;
use App\Models\ProfileCompletion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileCompletion>
 */
class ProfileCompletionFactory extends Factory
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
            'accuracy_confirmed' => true,
            'accuracy_confirmed_at' => '2026-11-25 05:00:00',
            'setup_completed_at' => '2026-11-25 05:00:00',
        ];
    }
}
