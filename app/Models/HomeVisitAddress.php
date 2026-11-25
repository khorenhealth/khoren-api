<?php

namespace App\Models;

use Database\Factories\HomeVisitAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'street_address',
    'state',
    'local_government_area',
    'country',
    'landmark',
    'access_directions',
])]
class HomeVisitAddress extends Model
{
    /** @use HasFactory<HomeVisitAddressFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PatientProfile, $this>
     */
    public function patientProfile(): BelongsTo
    {
        return $this->belongsTo(PatientProfile::class);
    }
}
