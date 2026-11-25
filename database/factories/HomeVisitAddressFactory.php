<?php

namespace Database\Factories;

use App\Models\HomeVisitAddress;
use App\Models\PatientProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeVisitAddress>
 */
class HomeVisitAddressFactory extends Factory
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
            'street_address' => '5 Admiralty Way, Lekki Phase 1, Lagos',
            'state' => 'Lagos',
            'local_government_area' => 'Eti-Osa',
            'country' => 'Nigeria',
            'landmark' => null,
            'access_directions' => null,
        ];
    }
}
