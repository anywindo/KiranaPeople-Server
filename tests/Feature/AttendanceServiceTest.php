<?php

use App\Models\User;
use App\Models\Shift;
use App\Enums\EventType;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;



function setupAttendanceServiceTest()
{
    $user = User::factory()->create();
    $shift = Shift::create([
        'shift_name' => 'Test Shift',
        'default_start_time' => '08:00:00',
        'default_end_time' => '17:00:00',
    ]);
    $service = app(AttendanceService::class);

    return [$user, $shift, $service];
}

it('can record a valid START_SHIFT event', function () {
    [$user, $shift, $service] = setupAttendanceServiceTest();

    $attendance = $service->recordEvent($user, $shift, EventType::START_SHIFT, [
        'latitude' => -6.200000,
        'longitude' => 106.816666,
        'selfie_path' => 'selfies/dummy.jpg',
    ]);

    expect($attendance->events)->toHaveCount(1);
    expect($attendance->events->first()->event_type)->toBe(EventType::START_SHIFT);
});

it('throws exception if gps or selfie is missing', function () {
    [$user, $shift, $service] = setupAttendanceServiceTest();

    $service->recordEvent($user, $shift, EventType::START_SHIFT, [
        'latitude' => -6.200000,
        // missing longitude
        'selfie_path' => 'selfies/dummy.jpg',
    ]);
})->throws(ValidationException::class);

it('throws exception if starting break before starting shift', function () {
    [$user, $shift, $service] = setupAttendanceServiceTest();

    $service->recordEvent($user, $shift, EventType::START_BREAK, [
        'latitude' => -6.2,
        'longitude' => 106.8,
        'selfie_path' => 'path.jpg'
    ]);
})->throws(ValidationException::class);

it('throws exception if starting shift twice', function () {
    [$user, $shift, $service] = setupAttendanceServiceTest();

    $service->recordEvent($user, $shift, EventType::START_SHIFT, [
        'latitude' => -6.2,
        'longitude' => 106.8,
        'selfie_path' => 'path.jpg'
    ]);

    $service->recordEvent($user, $shift, EventType::START_SHIFT, [
        'latitude' => -6.2,
        'longitude' => 106.8,
        'selfie_path' => 'path2.jpg'
    ]);
})->throws(ValidationException::class);

it('sets on-time status when clocking in at 07:36 with 08:00 shift and 15m rule', function () {
    [$user, $shift, $service] = setupAttendanceServiceTest();
    \App\Models\Setting::set('attendance_rule_enabled', 'true', 'boolean');
    \App\Models\Setting::set('attendance_rule_minutes', 15, 'integer');

    \Carbon\Carbon::setTestNow(\Carbon\Carbon::today()->setHour(7)->setMinute(36));

    $attendance = $service->recordEvent($user, $shift, EventType::START_SHIFT, [
        'latitude' => -6.200000,
        'longitude' => 106.816666,
        'selfie_path' => 'selfies/dummy.jpg',
    ]);

    expect($attendance->status)->not->toBe(\App\Enums\AttendanceStatus::LATE);
    expect($attendance->shift_status['color'])->toBe('success');
    \Carbon\Carbon::setTestNow();
});

it('sets late status when clocking in at 07:46 with 08:00 shift and 15m rule', function () {
    [$user, $shift, $service] = setupAttendanceServiceTest();
    \App\Models\Setting::set('attendance_rule_enabled', 'true', 'boolean');
    \App\Models\Setting::set('attendance_rule_minutes', 15, 'integer');

    \Carbon\Carbon::setTestNow(\Carbon\Carbon::today()->setHour(7)->setMinute(46));

    $attendance = $service->recordEvent($user, $shift, EventType::START_SHIFT, [
        'latitude' => -6.200000,
        'longitude' => 106.816666,
        'selfie_path' => 'selfies/dummy.jpg',
    ]);

    expect($attendance->status)->toBe(\App\Enums\AttendanceStatus::LATE);
    expect($attendance->shift_status['color'])->toBe('danger');
    \Carbon\Carbon::setTestNow();
});
