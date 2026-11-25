<?php

namespace App\Http\Requests\Profile;

use App\Enums\EmergencyContactRelationship;
use App\Models\User;
use App\Support\NigerianPhone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum as EnumRule;
use Illuminate\Validation\Validator;

class UpdateEmergencyContactRequest extends FormRequest
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

        $fullName = $this->input('full_name');

        if (is_string($fullName)) {
            $fullName = trim($fullName);
            $merge['full_name'] = $fullName === '' ? null : $fullName;
        }

        $relationship = $this->input('relationship');

        if (is_string($relationship)) {
            $relationship = trim($relationship);
            $parsed = $relationship === '' ? null : EmergencyContactRelationship::parse($relationship);
            $merge['relationship'] = $parsed?->value ?? ($relationship === '' ? null : $relationship);
        }

        $phone = $this->input('phone');

        if (is_string($phone)) {
            $phone = trim($phone);
            $merge['phone'] = $phone === '' ? null : NigerianPhone::normalize($phone);
        }

        $permission = $this->input('permission_to_contact');

        if (in_array($permission, [true, 1, '1', 'true'], true)) {
            $merge['permission_to_contact'] = true;
        } elseif ($permission === null || $permission === '' || in_array($permission, [false, 0, '0', 'false'], true)) {
            $merge['permission_to_contact'] = false;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $permissionGranted = $this->boolean('permission_to_contact');

        return [
            'full_name' => [
                Rule::when(
                    $permissionGranted,
                    ['bail', 'required', 'string', 'max:255'],
                    ['bail', 'nullable', 'string', 'max:255'],
                ),
            ],
            'relationship' => [
                Rule::when(
                    $permissionGranted,
                    ['bail', 'required', 'string', new EnumRule(EmergencyContactRelationship::class)],
                    ['bail', 'nullable', 'string', new EnumRule(EmergencyContactRelationship::class)],
                ),
            ],
            'phone' => [
                Rule::when(
                    $permissionGranted,
                    ['bail', 'required', 'string', 'regex:'.NigerianPhone::PATTERN],
                    ['bail', 'nullable', 'string', 'regex:'.NigerianPhone::PATTERN],
                ),
            ],
            'permission_to_contact' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $phone = $this->input('phone');
                $patientPhone = $this->user()?->patientProfile?->phone;

                if (! is_string($phone) || ! is_string($patientPhone) || preg_match(NigerianPhone::PATTERN, $phone) !== 1) {
                    return;
                }

                if ($phone === $patientPhone) {
                    $validator->errors()->add('phone', 'Enter a separate number belonging to your emergency contact.');
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
            'full_name.required' => 'Contact full name is required.',
            'full_name.max' => 'Contact full name must be 255 characters or fewer.',
            'relationship.required' => 'Relationship is required.',
            'relationship.'.EnumRule::class => 'Choose Spouse, Parent, Sibling, Friend, Carer, or Other.',
            'phone.required' => 'Enter a valid contact phone number.',
            'phone.regex' => 'Enter 10 digits after +234 and omit the leading 0.',
            'permission_to_contact.required' => 'Confirm whether Khoren may contact this person in an emergency.',
            'permission_to_contact.boolean' => 'Confirm whether Khoren may contact this person in an emergency.',
        ];
    }
}
