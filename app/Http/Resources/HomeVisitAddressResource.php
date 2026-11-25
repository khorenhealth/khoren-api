<?php

namespace App\Http\Resources;

use App\Models\HomeVisitAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeVisitAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $address = $this->resource instanceof HomeVisitAddress ? $this->resource : null;

        return [
            'street_address' => $address?->street_address,
            'state' => $address?->state,
            'local_government_area' => $address?->local_government_area,
            'country' => $address?->country,
            'landmark' => $address?->landmark,
            'access_directions' => $address?->access_directions,
        ];
    }
}
