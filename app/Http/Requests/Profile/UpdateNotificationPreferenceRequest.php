<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasVerifiedEmail();
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Email address is not verified.');
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach ([
            'visit_reminders',
            'medication_reminders',
            'visit_reports_ready',
            'clinical_alerts',
            'promotions',
        ] as $field) {
            if ($this->exists($field)) {
                $merge[$field] = $this->booleanValue($this->input($field));
            }
        }

        $channels = $this->input('channels');

        if (is_array($channels)) {
            foreach (['email', 'sms', 'in_app'] as $channel) {
                if (array_key_exists($channel, $channels)) {
                    $channels[$channel] = $this->booleanValue($channels[$channel]);
                }
            }

            $merge['channels'] = $channels;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visit_reminders' => ['required', 'boolean'],
            'medication_reminders' => ['required', 'boolean'],
            'visit_reports_ready' => ['required', 'boolean'],
            'clinical_alerts' => ['required', 'boolean'],
            'channels' => ['required', 'array'],
            'channels.email' => ['required', 'boolean'],
            'channels.sms' => ['required', 'boolean'],
            'channels.in_app' => ['required', 'boolean'],
            'promotions' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'visit_reminders.required' => 'Visit reminders must be on or off.',
            'visit_reminders.boolean' => 'Visit reminders must be on or off.',
            'medication_reminders.required' => 'Medication reminders must be on or off.',
            'medication_reminders.boolean' => 'Medication reminders must be on or off.',
            'visit_reports_ready.required' => 'Visit reports must be on or off.',
            'visit_reports_ready.boolean' => 'Visit reports must be on or off.',
            'clinical_alerts.required' => 'Clinical alerts must be on or off.',
            'clinical_alerts.boolean' => 'Clinical alerts must be on or off.',
            'channels.required' => 'Choose the communication channels.',
            'channels.array' => 'Choose the communication channels.',
            'channels.email.required' => 'Email must be on or off.',
            'channels.email.boolean' => 'Email must be on or off.',
            'channels.sms.required' => 'SMS must be on or off.',
            'channels.sms.boolean' => 'SMS must be on or off.',
            'channels.in_app.required' => 'In-app must be on or off.',
            'channels.in_app.boolean' => 'In-app must be on or off.',
            'promotions.required' => 'Promotions and tips must be on or off.',
            'promotions.boolean' => 'Promotions and tips must be on or off.',
        ];
    }

    private function booleanValue(mixed $value): mixed
    {
        if (in_array($value, [true, 1, '1', 'true'], true)) {
            return true;
        }

        if (in_array($value, [false, 0, '0', 'false'], true)) {
            return false;
        }

        return $value;
    }
}
