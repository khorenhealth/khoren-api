<?php

namespace App\Http\Resources;

use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public ?PatientProfile $patientProfile = null;

    public function withProfile(?PatientProfile $patientProfile): self
    {
        $this->patientProfile = $patientProfile;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'first_name' => $this->patientProfile?->first_name,
            'last_name' => $this->patientProfile?->last_name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'date_of_birth' => $this->patientProfile?->date_of_birth?->toDateString(),
            'gender' => $this->patientProfile?->gender?->value,
            'phone' => $this->patientProfile?->phone,
            'photo_url' => $this->patientProfile?->photoUrl(),
            'home_visit' => HomeVisitAddressResource::make($this->patientProfile?->homeVisit)->resolve(),
            'medical_information' => MedicalInformationResource::make($this->patientProfile?->medicalInformation)->resolve(),
            'medications' => MedicationResource::collection($this->patientProfile?->medications ?? [])->resolve(),
            'supporting_records' => SupportingRecordResource::collection($this->patientProfile?->supportingRecords ?? [])->resolve(),
            'emergency_contact' => EmergencyContactResource::make($this->patientProfile?->emergencyContact)->resolve(),
            'notifications' => NotificationPreferenceResource::make($this->patientProfile?->notificationPreference)->resolve(),
            'privacy' => PrivacyConsentResource::make($this->patientProfile?->privacyConsent)->resolve(),
            'completion' => ProfileCompletionResource::make($this->patientProfile?->profileCompletion)->resolve(),
        ];
    }
}
