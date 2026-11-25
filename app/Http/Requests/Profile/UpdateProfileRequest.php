<?php

namespace App\Http\Requests\Profile;

use App\Enums\Gender;
use App\Models\User;
use App\Support\NigerianPhone;
use DateTimeImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum as EnumRule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    private const int MAX_AGE_YEARS = 120;

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

        foreach (['first_name', 'last_name'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $merge[$field] = trim($value);
            }
        }

        $dateOfBirth = $this->input('date_of_birth');

        if (is_string($dateOfBirth)) {
            $normalized = $this->normalizeDateOfBirth($dateOfBirth);

            if ($normalized !== null) {
                $merge['date_of_birth'] = $normalized;
            }
        }

        $gender = $this->input('gender');

        if (is_string($gender)) {
            $parsed = Gender::parse($gender);

            if ($parsed instanceof Gender) {
                $merge['gender'] = $parsed->value;
            }
        }

        $phone = $this->input('phone');

        if (is_string($phone)) {
            $merge['phone'] = NigerianPhone::normalize($phone);
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
            'first_name' => ['bail', 'required', 'string', 'max:100'],
            'last_name' => ['bail', 'required', 'string', 'max:100'],
            'date_of_birth' => [
                'bail',
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
                'after_or_equal:'.now()->subYears(self::MAX_AGE_YEARS)->toDateString(),
            ],
            'gender' => ['bail', 'required', new EnumRule(Gender::class)],
            'phone' => ['bail', 'required', 'string', 'regex:'.NigerianPhone::PATTERN],
            'photo' => ['prohibited'],
            'remove_photo' => ['prohibited'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->exists('email')) {
                    return;
                }

                $email = $this->input('email');
                $current = $this->user()?->email;

                if (! is_string($email) || ! is_string($current) || strcasecmp(trim($email), $current) !== 0) {
                    $validator->errors()->add('email', 'Account email cannot be changed here.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'first_name.max' => 'First name must be 100 characters or fewer.',
            'last_name.required' => 'Last name is required.',
            'last_name.max' => 'Last name must be 100 characters or fewer.',
            'date_of_birth.required' => 'Date of birth is required.',
            'date_of_birth.date_format' => 'Use DD/MM/YYYY.',
            'date_of_birth.before_or_equal' => 'Date of birth cannot be in the future.',
            'date_of_birth.after_or_equal' => 'Date of birth is too far in the past.',
            'gender.required' => 'Gender is required.',
            'gender.'.EnumRule::class => 'Choose Female, Male, Non-binary, Self-describe, or Prefer not to say.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Enter 10 digits after +234 and omit the leading 0.',
            'photo.prohibited' => 'Upload the profile photo with POST /api/profile/photo.',
            'remove_photo.prohibited' => 'Remove the profile photo with DELETE /api/profile/photo.',
        ];
    }

    private function normalizeDateOfBirth(string $value): ?string
    {
        $value = trim($value);

        foreach (['!d/m/Y', '!Y-m-d'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value);

            if ($parsed === false) {
                continue;
            }

            $errors = DateTimeImmutable::getLastErrors();

            if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            if ($parsed->format(ltrim($format, '!')) !== $value) {
                continue;
            }

            return $parsed->format('Y-m-d');
        }

        return null;
    }
}
