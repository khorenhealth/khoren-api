<?php

namespace App\Http\Resources;

use App\Models\EmergencyContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmergencyContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $contact = $this->resource instanceof EmergencyContact ? $this->resource : null;

        return [
            'full_name' => $contact?->full_name,
            'relationship' => $contact?->relationship?->value,
            'phone' => $contact?->phone,
            'permission_to_contact' => $contact?->permission_to_contact ?? false,
        ];
    }
}
