<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'start_time' => $this['start_time'],
            'end_time' => $this['end_time'],
            'available_spots' => $this['available_spots'],
            'is_available' => $this['available_spots'] > 0,
        ];
    }
}
