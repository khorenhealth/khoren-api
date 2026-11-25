<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePrivacyConsentRequest extends FormRequest
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

        foreach (['care_consent', 'anonymised_data_sharing'] as $field) {
            if ($this->exists($field)) {
                $merge[$field] = $this->booleanValue($this->input($field));
            }
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
            'care_consent' => ['required', 'boolean'],
            'anonymised_data_sharing' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'care_consent.required' => 'Care-related consent must be accepted or saved for later.',
            'care_consent.boolean' => 'Care-related consent must be accepted or saved for later.',
            'anonymised_data_sharing.required' => 'Anonymised data sharing must be on or off.',
            'anonymised_data_sharing.boolean' => 'Anonymised data sharing must be on or off.',
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
