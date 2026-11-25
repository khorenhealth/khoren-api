<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicationsRequest extends FormRequest
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
        $medications = $this->input('medications');

        if (! is_array($medications)) {
            return;
        }

        $normalized = [];

        foreach ($medications as $medication) {
            if (! is_array($medication)) {
                $normalized[] = $medication;

                continue;
            }

            $name = $medication['name'] ?? null;
            $strength = $medication['strength'] ?? null;
            $frequency = $medication['frequency'] ?? null;

            $normalized[] = [
                'name' => is_string($name) ? trim($name) : $name,
                'strength' => is_string($strength) && trim($strength) !== '' ? trim($strength) : null,
                'frequency' => is_string($frequency) && trim($frequency) !== '' ? trim($frequency) : null,
            ];
        }

        $this->merge(['medications' => $normalized]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'medications' => ['present', 'array', 'max:30'],
            'medications.*.name' => ['bail', 'required', 'string', 'max:255'],
            'medications.*.strength' => ['nullable', 'string', 'max:255'],
            'medications.*.frequency' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'medications.present' => 'Medication list is required. Send an empty list when none are reported.',
            'medications.array' => 'Medication list is required. Send an empty list when none are reported.',
            'medications.max' => 'You can report up to 30 medications.',
            'medications.*.name.required' => 'Medication name is required.',
            'medications.*.name.max' => 'Medication name must be 255 characters or fewer.',
            'medications.*.strength.max' => 'Strength or dose must be 255 characters or fewer.',
            'medications.*.frequency.max' => 'Frequency must be 255 characters or fewer.',
        ];
    }
}
