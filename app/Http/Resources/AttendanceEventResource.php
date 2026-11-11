<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceEventResource extends JsonResource
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
            'event_type' => $this->event_type,
            'timestamp' => $this->timestamp,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'accuracy' => $this->accuracy !== null ? (float) $this->accuracy : null,
            'is_mocked' => (bool) $this->is_mocked,
            'notes' => $this->notes,
            'selfie_path' => $this->selfie_path,
            'selfie_url' => $this->selfie_url,
        ];
    }
}
