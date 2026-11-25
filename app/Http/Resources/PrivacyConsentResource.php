<?php

namespace App\Http\Resources;

use App\Models\PrivacyConsent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrivacyConsentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $consent = $this->resource instanceof PrivacyConsent ? $this->resource : null;

        if (! $consent instanceof PrivacyConsent) {
            return PrivacyConsent::defaults();
        }

        return [
            'care_consent' => $consent->care_consent,
            'care_consent_given_at' => $consent->care_consent_given_at,
            'anonymised_data_sharing' => $consent->anonymised_data_sharing,
        ];
    }
}
