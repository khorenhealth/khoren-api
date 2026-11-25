<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class UploadSupportingRecordRequest extends FormRequest
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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['bail', 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a supporting record to upload.',
            'file.file' => 'Supporting records must be a PDF, JPG, or PNG.',
            'file.mimes' => 'Supporting records must be a PDF, JPG, or PNG.',
            'file.extensions' => 'Supporting records must be a PDF, JPG, or PNG.',
            'file.max' => 'Supporting records must be 10 MB or smaller.',
        ];
    }
}
