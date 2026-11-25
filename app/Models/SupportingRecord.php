<?php

namespace App\Models;

use Database\Factories\SupportingRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'original_name',
    'path',
    'mime_type',
    'size',
])]
class SupportingRecord extends Model
{
    public const string DISK = 'local';

    public const string DIRECTORY = 'supporting-records';

    /** @use HasFactory<SupportingRecordFactory> */
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
            'size' => 'integer',
        ];
    }
}
