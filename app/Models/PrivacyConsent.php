<?php

namespace App\Models;

use Database\Factories\PrivacyConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'care_consent',
    'care_consent_given_at',
    'anonymised_data_sharing',
])]
class PrivacyConsent extends Model
{
    /** @use HasFactory<PrivacyConsentFactory> */
    use HasFactory;

    /**
     * Choices shown before the patient saves this step.
     *
     * @return array{care_consent: bool, care_consent_given_at: null, anonymised_data_sharing: bool}
     */
    public static function defaults(): array
    {
        return [
            'care_consent' => false,
            'care_consent_given_at' => null,
            'anonymised_data_sharing' => false,
        ];
    }

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
            'care_consent' => 'boolean',
            'care_consent_given_at' => 'datetime',
            'anonymised_data_sharing' => 'boolean',
        ];
    }
}
