<?php

namespace App\Http\Resources\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'employee_id' => $this->employee_id,
            'nik' => $this->nik,
            'dob' => $this->dob ? $this->dob->format('Y-m-d') : ($this->nik ? User::parseDobDateFromNik($this->nik) : null),
            'phone' => $this->phone,
            'location_id' => $this->location_id,
            'must_change_password' => $this->mustChangePassword(),
            'location' => $this->location ? [
                'id' => $this->location->id,
                'name' => $this->location->name,
                'latitude' => (float) $this->location->latitude,
                'longitude' => (float) $this->location->longitude,
                'radius' => (float) $this->location->radius,
            ] : null,
            'avatar' => $this->avatar,
            'avatar_url' => $this->avatar_url,
            'is_active' => $this->is_active,
            'department' => $this->whenLoaded('teams', fn () => $this->teams->first()?->name),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->getAllPermissions()->pluck('name')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
