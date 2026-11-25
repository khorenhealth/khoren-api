<?php

namespace App\Http\Resources;

use App\Models\ProfileCompletion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileCompletionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $completion = $this->resource instanceof ProfileCompletion ? $this->resource : null;

        if (! $completion instanceof ProfileCompletion) {
            return ProfileCompletion::defaults();
        }

        return [
            'accuracy_confirmed' => $completion->accuracy_confirmed,
            'accuracy_confirmed_at' => $completion->accuracy_confirmed_at,
            'setup_completed_at' => $completion->setup_completed_at,
        ];
    }
}
