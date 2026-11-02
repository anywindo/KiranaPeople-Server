<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
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
            'user_id' => $this->user_id,
            'shift_id' => $this->shift_id,
            'date' => $this->getRawOriginal('date') ?? ($this->date instanceof \Carbon\Carbon ? $this->date->format('Y-m-d') : substr((string)$this->date, 0, 10)),
            'status' => $this->relationLoaded('events') && $this->relationLoaded('shift') && $this->shift
                ? ($this->shift_status['label'] ?? ($this->status?->value ?? (string)$this->status))
                : ($this->status?->value ?? (string)$this->status),
            'shift_status' => $this->relationLoaded('events') && $this->relationLoaded('shift') ? ($this->shift_status['label'] ?? null) : null,
            'break_status' => $this->relationLoaded('events') && $this->relationLoaded('shift') ? ($this->break_status['label'] ?? '-') : '-',
            'events' => AttendanceEventResource::collection($this->whenLoaded('events')),
            'shift' => $this->relationLoaded('shift') && $this->shift ? [
                'id' => $this->shift->id,
                'name' => $this->shift->shift_name ?? $this->shift->name,
                'start_time' => $this->shift->default_start_time ?? ($this->shift->start_time ?? '08:00:00'),
                'end_time' => $this->shift->default_end_time ?? ($this->shift->end_time ?? '17:00:00'),
                'break_start' => $this->shift->break_start ? \Carbon\Carbon::parse($this->shift->break_start)->format('H:i:s') : null,
                'break_end' => $this->shift->break_end ? \Carbon\Carbon::parse($this->shift->break_end)->format('H:i:s') : null,
                'break_start_time' => $this->shift->break_start ? \Carbon\Carbon::parse($this->shift->break_start)->format('H:i:s') : null,
                'break_end_time' => $this->shift->break_end ? \Carbon\Carbon::parse($this->shift->break_end)->format('H:i:s') : null,
                'attendance_rule_enabled' => (bool) \App\Models\Setting::get('attendance_rule_enabled', false),
                'attendance_rule_minutes' => (int) \App\Models\Setting::get('attendance_rule_minutes', 15),
            ] : null,
        ];
    }
}
