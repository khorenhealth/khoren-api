<?php

namespace App\Http\Resources;

use App\Models\MedicalInformation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicalInformationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $record = $this->resource instanceof MedicalInformation ? $this->resource : null;

        return [
            'primary_condition' => $record?->primary_condition,
            'blood_group' => $record?->blood_group?->value,
            'allergy_status' => $record?->allergy_status?->value,
            'known_allergies' => $record?->known_allergies,
            'other_conditions' => $record?->other_conditions,
        ];
    }
}
