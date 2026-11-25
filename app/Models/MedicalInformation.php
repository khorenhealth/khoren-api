<?php

namespace App\Models;

use App\Enums\AllergyStatus;
use App\Enums\BloodGroup;
use Database\Factories\MedicalInformationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'primary_condition',
    'blood_group',
    'allergy_status',
    'known_allergies',
    'other_conditions',
])]
class MedicalInformation extends Model
{
    /** @use HasFactory<MedicalInformationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PatientProfile, $this>
     */
    public function patientProfile(): BelongsTo
    {
        return $this->belongsTo(PatientProfile::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blood_group' => BloodGroup::class,
            'allergy_status' => AllergyStatus::class,
        ];
    }
}
