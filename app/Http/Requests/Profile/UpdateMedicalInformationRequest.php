<?php

namespace App\Http\Requests\Profile;

use App\Enums\AllergyStatus;
use App\Enums\BloodGroup;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum as EnumRule;

class UpdateMedicalInformationRequest extends FormRequest
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

        $primaryCondition = $this->input('primary_condition');

        if (is_string($primaryCondition)) {
            $merge['primary_condition'] = trim($primaryCondition);
        }

        $bloodGroup = $this->input('blood_group');

        if (is_string($bloodGroup)) {
            $bloodGroup = trim($bloodGroup);
            $merge['blood_group'] = $bloodGroup === ''
                ? ''
                : (BloodGroup::parse($bloodGroup)?->value ?? $bloodGroup);
        }

        $allergyStatus = $this->input('allergy_status');
        $parsedStatus = null;

        if (is_string($allergyStatus)) {
            $allergyStatus = trim($allergyStatus);
            $parsedStatus = $allergyStatus === '' ? null : AllergyStatus::parse($allergyStatus);
            $merge['allergy_status'] = $parsedStatus?->value ?? $allergyStatus;
        }

        $knownAllergies = $this->input('known_allergies');

        if ($parsedStatus === AllergyStatus::NoneKnown || $parsedStatus === AllergyStatus::Unknown) {
            $merge['known_allergies'] = null;
        } elseif (is_string($knownAllergies)) {
            $merge['known_allergies'] = trim($knownAllergies);
        }

        $otherConditions = $this->input('other_conditions');

        if (! is_string($otherConditions) || trim($otherConditions) === '') {
            $merge['other_conditions'] = null;
        } else {
            $merge['other_conditions'] = trim($otherConditions);
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'primary_condition' => ['bail', 'required', 'string', 'max:255'],
            'blood_group' => ['bail', 'required', 'string', new EnumRule(BloodGroup::class)],
            'allergy_status' => ['bail', 'required', 'string', new EnumRule(AllergyStatus::class)],
            'known_allergies' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf($this->input('allergy_status') === AllergyStatus::KnownAllergies->value),
            ],
            'other_conditions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_condition.required' => 'Primary condition or reason for care is required.',
            'primary_condition.max' => 'Primary condition or reason for care must be 255 characters or fewer.',
            'blood_group.required' => 'Blood group is required.',
            'blood_group.'.EnumRule::class => 'Choose A+, A-, B+, B-, AB+, AB-, O+, O-, or Unknown.',
            'allergy_status.required' => 'Allergy status is required.',
            'allergy_status.'.EnumRule::class => 'Choose Known allergies, None known, or Unknown.',
            'known_allergies.required' => 'Known allergies are required.',
            'known_allergies.max' => 'Known allergies must be 1000 characters or fewer.',
            'other_conditions.max' => 'Other relevant conditions must be 2000 characters or fewer.',
        ];
    }
}
