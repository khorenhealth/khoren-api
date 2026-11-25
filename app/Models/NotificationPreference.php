<?php

namespace App\Models;

use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'visit_reminders',
    'medication_reminders',
    'visit_reports_ready',
    'clinical_alerts',
    'channel_email',
    'channel_sms',
    'channel_in_app',
    'promotions',
])]
class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;

    /**
     * Preferences shown before the patient saves this step.
     *
     * @return array{
     *     visit_reminders: bool,
     *     medication_reminders: bool,
     *     visit_reports_ready: bool,
     *     clinical_alerts: bool,
     *     channels: array{email: bool, sms: bool, in_app: bool},
     *     promotions: bool
     * }
     */
    public static function defaults(): array
    {
        return [
            'visit_reminders' => true,
            'medication_reminders' => true,
            'visit_reports_ready' => true,
            'clinical_alerts' => true,
            'channels' => [
                'email' => true,
                'sms' => true,
                'in_app' => true,
            ],
            'promotions' => false,
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
            'visit_reminders' => 'boolean',
            'medication_reminders' => 'boolean',
            'visit_reports_ready' => 'boolean',
            'clinical_alerts' => 'boolean',
            'channel_email' => 'boolean',
            'channel_sms' => 'boolean',
            'channel_in_app' => 'boolean',
            'promotions' => 'boolean',
        ];
    }
}
