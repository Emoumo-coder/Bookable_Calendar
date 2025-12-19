<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'slot_duration' => $this->slot_duration_minutes,
            'cleanup_break' => $this->cleanup_break_minutes,
            'max_clients_per_slot' => $this->max_clients_per_slot,
            'max_days_in_future' => $this->max_days_in_future,
            'is_active' => $this->is_active,
            // 'created_at' => $this->created_at->toISOString(),
            // 'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
