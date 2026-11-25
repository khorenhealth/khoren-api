<?php

namespace App\Http\Resources;

use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationPreferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $preference = $this->resource instanceof NotificationPreference ? $this->resource : null;

        if (! $preference instanceof NotificationPreference) {
            return NotificationPreference::defaults();
        }

        return [
            'visit_reminders' => $preference->visit_reminders,
            'medication_reminders' => $preference->medication_reminders,
            'visit_reports_ready' => $preference->visit_reports_ready,
            'clinical_alerts' => $preference->clinical_alerts,
            'channels' => [
                'email' => $preference->channel_email,
                'sms' => $preference->channel_sms,
                'in_app' => $preference->channel_in_app,
            ],
            'promotions' => $preference->promotions,
        ];
    }
}
