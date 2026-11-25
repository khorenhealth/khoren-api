<?php

namespace Database\Factories;

use App\Models\Medication;
use App\Models\PatientProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medication>
 */
class MedicationFactory extends Factory
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
            'name' => 'Amoxicillin',
            'strength' => '500 mg',
            'frequency' => 'Twice daily',
        ];
    }
}
