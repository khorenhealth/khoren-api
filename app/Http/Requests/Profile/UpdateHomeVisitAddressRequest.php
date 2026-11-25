<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use App\Support\HomeVisitLocations;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHomeVisitAddressRequest extends FormRequest
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

        $country = $this->input('country');

        if (is_string($country)) {
            $country = trim($country);
            $merge['country'] = strcasecmp($country, HomeVisitLocations::COUNTRY) === 0
                ? HomeVisitLocations::COUNTRY
                : $country;
        }

        $streetAddress = $this->input('street_address');

        if (is_string($streetAddress)) {
            $merge['street_address'] = trim($streetAddress);
        }

        $state = $this->input('state');

        if (is_string($state)) {
            $merge['state'] = HomeVisitLocations::canonicalState($state) ?? trim($state);
            $state = $merge['state'];
        }

        $area = $this->input('local_government_area');

        if (is_string($area)) {
            $canonicalArea = is_string($state)
                ? HomeVisitLocations::canonicalArea($state, $area)
                : null;
            $merge['local_government_area'] = $canonicalArea ?? trim($area);
        }

        foreach (['landmark', 'access_directions'] as $field) {
            $value = $this->input($field);

            if (! is_string($value) || trim($value) === '') {
                $merge[$field] = null;
            } else {
                $merge[$field] = trim($value);
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $country = $this->input('country');
        $countryIsNigeria = is_string($country) && strcasecmp($country, HomeVisitLocations::COUNTRY) === 0;
        $state = $this->input('state');
        $stateIsSupported = $countryIsNigeria
            && is_string($state)
            && HomeVisitLocations::canonicalState($state) !== null;

        $stateRules = ['bail', 'required', 'string', 'max:255'];
        $areaRules = ['bail', 'required', 'string', 'max:255'];

        if ($countryIsNigeria) {
            $stateRules[] = Rule::in(array_keys(HomeVisitLocations::supported()));
            $areaRules[] = Rule::excludeIf(! $stateIsSupported);
            $areaRules[] = Rule::in($stateIsSupported ? HomeVisitLocations::supported()[$state] : []);
        }

        return [
            'street_address' => ['bail', 'required', 'string', 'max:255'],
            'state' => $stateRules,
            'local_government_area' => $areaRules,
            'country' => ['bail', 'required', 'string', 'max:100'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'access_directions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'street_address.required' => 'Street address is required.',
            'street_address.max' => 'Street address must be 255 characters or fewer.',
            'country.required' => 'Country is required.',
            'country.max' => 'Country must be 100 characters or fewer.',
            'state.required' => 'State is required.',
            'state.max' => 'State must be 255 characters or fewer.',
            'state.in' => 'Home visits are currently available in supported Nigerian locations.',
            'local_government_area.required' => 'Local government area is required.',
            'local_government_area.in' => 'Choose a local government area in the selected state.',
            'landmark.max' => 'Landmark must be 255 characters or fewer.',
            'access_directions.max' => 'Access and accessibility directions must be 1000 characters or fewer.',
        ];
    }
}
