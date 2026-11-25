<?php

namespace App\Models;

use App\Enums\Gender;
use Database\Factories\PatientProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'first_name',
    'last_name',
    'date_of_birth',
    'gender',
    'phone',
    'photo_path',
])]
class PatientProfile extends Model
{
    public const string PHOTO_DISK = 'public';

    /** @use HasFactory<PatientProfileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<HomeVisitAddress, $this>
     */
    public function homeVisit(): HasOne
    {
        return $this->hasOne(HomeVisitAddress::class);
    }

    /**
     * @return HasOne<MedicalInformation, $this>
     */
    public function medicalInformation(): HasOne
    {
        return $this->hasOne(MedicalInformation::class);
    }

    /**
     * @return HasMany<Medication, $this>
     */
    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class)->orderBy('id');
    }

    /**
     * @return HasMany<SupportingRecord, $this>
     */
    public function supportingRecords(): HasMany
    {
        return $this->hasMany(SupportingRecord::class)->orderBy('id');
    }

    /**
     * @return HasOne<EmergencyContact, $this>
     */
    public function emergencyContact(): HasOne
    {
        return $this->hasOne(EmergencyContact::class);
    }

    /**
     * @return HasOne<NotificationPreference, $this>
     */
    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    /**
     * @return HasOne<PrivacyConsent, $this>
     */
    public function privacyConsent(): HasOne
    {
        return $this->hasOne(PrivacyConsent::class);
    }

    /**
     * @return HasOne<ProfileCompletion, $this>
     */
    public function profileCompletion(): HasOne
    {
        return $this->hasOne(ProfileCompletion::class);
    }

    public function photoUrl(): ?string
    {
        if (! is_string($this->photo_path) || $this->photo_path === '') {
            return null;
        }

        return Storage::disk(self::PHOTO_DISK)->url($this->photo_path);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
        ];
    }
}
