<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class SlotItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'capacity' => (int) $this->capacity,
            'booked_count' => (int) $this->booked_count,
            'available' => $this->available,
            'guide_name' => $this->guide_name,
            'language' => $this->language,
            'status' => $this->status,
        ];
    }
}
