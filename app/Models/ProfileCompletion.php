<?php

namespace App\Models;

use Database\Factories\ProfileCompletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'accuracy_confirmed',
    'accuracy_confirmed_at',
    'setup_completed_at',
])]
class ProfileCompletion extends Model
{
    /** @use HasFactory<ProfileCompletionFactory> */
    use HasFactory;

    /**
     * State shown before the patient completes setup.
     *
     * @return array{accuracy_confirmed: bool, accuracy_confirmed_at: null, setup_completed_at: null}
     */
    public static function defaults(): array
    {
        return [
            'accuracy_confirmed' => false,
            'accuracy_confirmed_at' => null,
            'setup_completed_at' => null,
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
            'accuracy_confirmed' => 'boolean',
            'accuracy_confirmed_at' => 'datetime',
            'setup_completed_at' => 'datetime',
        ];
    }
}
