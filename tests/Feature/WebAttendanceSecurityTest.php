<?php

use App\Models\Attendance;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
    Storage::fake('public');
    Storage::fake('r2');
});

it('enforces sequential attendance state machine on web attendance store', function () {
    $location = Location::factory()->create([
        'latitude' => -6.200000,
        'longitude' => 106.816666,
        'radius' => 500,
    ]);
    Shift::factory()->create();

    $employee = User::factory()->create([
        'location_id' => $location->id,
        'password_changed_at' => now(),
    ]);
    $employee->assignRole('Employee');

    // Cannot END_BREAK before START_SHIFT
    $this->actingAs($employee)->post(route('attendance.store'), [
        'event_type' => 'END_BREAK',
        'latitude' => -6.200000,
        'longitude' => 106.816666,
        'selfie' => UploadedFile::fake()->image('selfie.jpg'),
    ])->assertSessionHas('error');

    expect(Attendance::where('user_id', $employee->id)->count())->toBe(0);

    // Valid START_SHIFT succeeds
    $this->actingAs($employee)->post(route('attendance.store'), [
        'event_type' => 'START_SHIFT',
        'latitude' => -6.200000,
        'longitude' => 106.816666,
        'selfie' => UploadedFile::fake()->image('selfie.jpg'),
    ])->assertRedirect(route('attendance.index'))
        ->assertSessionHas('success');

    // Duplicate START_SHIFT is rejected
    $this->actingAs($employee)->post(route('attendance.store'), [
        'event_type' => 'START_SHIFT',
        'latitude' => -6.200000,
        'longitude' => 106.816666,
        'selfie' => UploadedFile::fake()->image('selfie.jpg'),
    ])->assertSessionHas('error');

    $attendance = Attendance::where('user_id', $employee->id)->first();
    expect($attendance->events()->count())->toBe(1);
});

it('rejects concurrent web attendance submission when atomic lock is held', function () {
    $location = Location::factory()->create([
        'latitude' => -6.200000,
        'longitude' => 106.816666,
        'radius' => 500,
    ]);
    Shift::factory()->create();

    $employee = User::factory()->create([
        'location_id' => $location->id,
        'password_changed_at' => now(),
    ]);
    $employee->assignRole('Employee');

    $lock = Cache::lock("attendance_lock:{$employee->id}:START_SHIFT", 10);
    expect($lock->get())->toBeTrue();

    try {
        $this->actingAs($employee)->post(route('attendance.store'), [
            'event_type' => 'START_SHIFT',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
        ])->assertSessionHas('error', 'Permintaan presensi sedang diproses. Mohon tunggu sejenak.');

        expect(Attendance::where('user_id', $employee->id)->count())->toBe(0);
    } finally {
        $lock->release();
    }
});
