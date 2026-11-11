<?php

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;

test('creating a model records audit log and filters sensitive fields', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'johndoe_audit@example.com',
        'password' => bcrypt('Secret123!'),
    ]);

    $log = AuditLog::where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->where('event', 'created')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->after)->toBeArray();
    expect($log->after)->toHaveKey('name', 'John Doe');
    expect($log->after)->toHaveKey('email', 'johndoe_audit@example.com');
    // Sensitive fields must NOT be in the log
    expect($log->after)->not->toHaveKey('password');
    expect($log->after)->not->toHaveKey('remember_token');
});

test('updating a model records only changed fields in audit log', function () {
    $location = Location::create([
        'name' => 'Kantor Lama',
        'latitude' => -6.175392,
        'longitude' => 106.827153,
        'radius' => 100,
    ]);

    $location->update([
        'name' => 'Kantor Baru',
        'radius' => 150,
    ]);

    $log = AuditLog::where('auditable_type', Location::class)
        ->where('auditable_id', $location->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->before)->toBeArray();
    expect($log->after)->toBeArray();

    expect($log->before)->toHaveKey('name', 'Kantor Lama');
    expect($log->before)->toHaveKey('radius', 100);
    expect($log->after)->toHaveKey('name', 'Kantor Baru');
    expect($log->after)->toHaveKey('radius', 150);

    // Unchanged attributes like latitude/longitude should NOT clutter the diff
    expect($log->after)->not->toHaveKey('latitude');
    expect($log->after)->not->toHaveKey('longitude');
});

test('deleting a model records audit log with before attributes', function () {
    $shift = Shift::create([
        'shift_name' => 'Shift Malam Khusus',
        'default_start_time' => '22:00:00',
        'default_end_time' => '06:00:00',
    ]);

    $shiftId = $shift->id;
    $shift->delete();

    $log = AuditLog::where('auditable_type', Shift::class)
        ->where('auditable_id', $shiftId)
        ->where('event', 'deleted')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->before)->toBeArray();
    expect($log->before)->toHaveKey('shift_name', 'Shift Malam Khusus');
    expect($log->after)->toBeNull();
});

test('updating model with only ignored attributes does not create duplicate audit log', function () {
    $user = User::factory()->create();
    $initialLogCount = AuditLog::where('auditable_type', User::class)->where('auditable_id', $user->id)->count();

    // Touch only updated_at
    $user->touch();

    $newLogCount = AuditLog::where('auditable_type', User::class)->where('auditable_id', $user->id)->count();
    expect($newLogCount)->toBe($initialLogCount);
});
