<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityIncidentController extends BaseController
{
    /**
     * Record a client-reported device integrity or anti-spoofing security incident.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'incident_type' => ['required', 'string', 'max:100'],
            'timestamp' => ['nullable', 'string', 'max:64'],
            'integrity_status' => ['nullable', 'string', 'max:50'],
            'is_rooted' => ['nullable', 'boolean'],
            'is_hooked' => ['nullable', 'boolean'],
            'is_emulator' => ['nullable', 'boolean'],
            'is_debugger' => ['nullable', 'boolean'],
            'is_developer_mode' => ['nullable', 'boolean'],
            'fake_gps_app_installed' => ['nullable', 'boolean'],
            'integrity_signals' => ['nullable', 'array'],
            'integrity_signals.*' => ['string', 'max:100'],
            'app_signature' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        AuditLog::create([
            'user_id' => $user->id,
            'event' => 'security_violation',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'before' => null,
            'after' => $validated,
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent() ?? 'MobileClient',
            'created_at' => now(),
        ]);

        return $this->created(null, 'Security incident recorded.');
    }
}
