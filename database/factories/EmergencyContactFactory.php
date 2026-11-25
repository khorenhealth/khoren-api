<?php

namespace Database\Factories;

use App\Enums\EmergencyContactRelationship;
use App\Models\EmergencyContact;
use App\Models\PatientProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmergencyContact>
 */
class EmergencyContactFactory extends Factory
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
            'full_name' => 'Mr. Chidi Okonkwo',
            'relationship' => EmergencyContactRelationship::Spouse,
            'phone' => '+2348095550142',
            'permission_to_contact' => true,
        ];
    }
}
