<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class CompleteProfileRequest extends FormRequest
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
        if (! $this->exists('accuracy_confirmed')) {
            return;
        }

        $value = $this->input('accuracy_confirmed');

        if (in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true)) {
            $this->merge(['accuracy_confirmed' => true]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'accuracy_confirmed' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accuracy_confirmed.required' => 'Confirm that the information you provided is accurate.',
            'accuracy_confirmed.accepted' => 'Confirm that the information you provided is accurate.',
        ];
    }
}
