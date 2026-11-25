<?php

namespace Database\Factories;

use App\Enums\AllergyStatus;
use App\Enums\BloodGroup;
use App\Models\MedicalInformation;
use App\Models\PatientProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalInformation>
 */
class MedicalInformationFactory extends Factory
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
            'primary_condition' => 'Post-Appendectomy Recovery',
            'blood_group' => BloodGroup::OPositive,
            'allergy_status' => AllergyStatus::KnownAllergies,
            'known_allergies' => 'Penicillin',
            'other_conditions' => null,
        ];
    }
}
