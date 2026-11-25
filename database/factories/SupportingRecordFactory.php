<?php

namespace Database\Factories;

use App\Models\PatientProfile;
use App\Models\SupportingRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportingRecord>
 */
class SupportingRecordFactory extends Factory
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
            'original_name' => 'HospitalDischargeSummary.pdf',
            'path' => 'supporting-records/example.pdf',
            'mime_type' => 'application/pdf',
            'size' => 250880,
        ];
    }
}
