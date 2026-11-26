<?php

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
});

it('requires authentication to report a security incident', function () {
    $this->postJson('/api/v1/security/incident', [
        'incident_type' => 'COMPROMISED_ATTENDANCE_BLOCKED',
    ])->assertStatus(401);
});

it('records client security incident telemetry into audit_logs', function () {
    $location = Location::factory()->create();
    $employee = User::factory()->create(['location_id' => $location->id]);
    $employee->assignRole('Employee');

    $response = $this->actingAs($employee, 'sanctum')->postJson('/api/v1/security/incident', [
        'incident_type' => 'COMPROMISED_ATTENDANCE_BLOCKED',
        'timestamp' => now()->toIso8601String(),
        'integrity_status' => 'compromised',
        'is_rooted' => true,
        'is_hooked' => false,
        'is_emulator' => false,
        'is_debugger' => false,
        'is_developer_mode' => true,
        'fake_gps_app_installed' => false,
        'integrity_signals' => ['su_binary', 'developer_mode'],
        'details' => [
            'event_type' => 'START_SHIFT',
            'latitude' => -6.2,
            'longitude' => 106.8,
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('meta.success', true);

    $log = AuditLog::where('user_id', $employee->id)
        ->where('event', 'security_violation')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->after['incident_type'])->toBe('COMPROMISED_ATTENDANCE_BLOCKED');
    expect($log->after['is_rooted'])->toBeTrue();
    expect($log->after['integrity_signals'])->toBe(['su_binary', 'developer_mode']);
});

it('validates incident_type on security incident endpoint', function () {
    $location = Location::factory()->create();
    $employee = User::factory()->create(['location_id' => $location->id]);
    $employee->assignRole('Employee');

    $this->actingAs($employee, 'sanctum')
        ->postJson('/api/v1/security/incident', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('incident_type');
});
