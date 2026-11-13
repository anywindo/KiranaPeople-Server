<?php

use App\Enums\EventType;
use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('caches settings in cache store and invalidates properly on set or delete', function () {
    Setting::clearCache();

    Setting::set('test_cache_key', 'initial_value', 'string');

    // First access primes the cache
    $val1 = Setting::get('test_cache_key');
    expect($val1)->toBe('initial_value');
    expect(Cache::has(Setting::CACHE_KEY))->toBeTrue();

    // Verify subsequent call uses cache without query
    DB::enableQueryLog();
    $val2 = Setting::get('test_cache_key');
    expect($val2)->toBe('initial_value');
    expect(DB::getQueryLog())->toBeEmpty();
    DB::disableQueryLog();

    // Updating setting clears cache
    Setting::set('test_cache_key', 'updated_value', 'string');
    expect(Setting::get('test_cache_key'))->toBe('updated_value');
});

it('evaluates check in and check out attributes cleanly on attendance model', function () {
    $shift = Shift::create([
        'shift_name' => 'Regular Morning',
        'default_start_time' => '08:00',
        'default_end_time' => '17:00',
    ]);

    $user = User::factory()->create();

    $attendance = Attendance::create([
        'user_id' => $user->id,
        'shift_id' => $shift->id,
        'date' => '2026-11-13',
        'status' => \App\Enums\AttendanceStatus::EXACT,
    ]);

    // Check-in on time
    AttendanceEvent::create([
        'attendance_id' => $attendance->id,
        'event_type' => EventType::START_SHIFT,
        'timestamp' => '2026-11-13 07:45:00',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'selfie_path' => 'selfies/in.jpg',
    ]);

    // Check-out on time
    AttendanceEvent::create([
        'attendance_id' => $attendance->id,
        'event_type' => EventType::END_SHIFT,
        'timestamp' => '2026-11-13 17:05:00',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'selfie_path' => 'selfies/out.jpg',
    ]);

    $attendance->load('events', 'shift', 'user');

    $inEval = $attendance->check_in_evaluation;
    expect($inEval['is_late'])->toBeFalse();
    expect($inEval['color'])->toBe('success');
    expect($inEval['time'])->toBe('07:45');

    $outEval = $attendance->check_out_evaluation;
    expect($outEval['is_early'])->toBeFalse();
    expect($outEval['color'])->toBe('success');
    expect($outEval['time'])->toBe('17:05');

    $modalData = $attendance->detail_modal_data;
    expect($modalData['employee'])->toBe($user->name);
    expect($modalData['in']['time'])->toBe('07:45');
    expect($modalData['out']['time'])->toBe('17:05');
});

it('evaluates break status and modal data cleanly on attendance model', function () {
    $shift = Shift::create([
        'shift_name' => 'Day Shift',
        'default_start_time' => '08:00',
        'default_end_time' => '17:00',
        'break_start' => '12:00',
        'break_end' => '13:00',
    ]);

    $user = User::factory()->create();

    $attendance = Attendance::create([
        'user_id' => $user->id,
        'shift_id' => $shift->id,
        'date' => '2026-11-13',
        'status' => \App\Enums\AttendanceStatus::EXACT,
    ]);

    AttendanceEvent::create([
        'attendance_id' => $attendance->id,
        'event_type' => EventType::START_BREAK,
        'timestamp' => '2026-11-13 12:02:00',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'selfie_path' => 'selfies/break_in.jpg',
    ]);

    AttendanceEvent::create([
        'attendance_id' => $attendance->id,
        'event_type' => EventType::END_BREAK,
        'timestamp' => '2026-11-13 12:55:00',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'selfie_path' => 'selfies/break_out.jpg',
    ]);

    $attendance->load('events', 'shift', 'user');

    $breakEval = $attendance->break_evaluation;
    expect($breakEval['start_break'])->not->toBeNull();
    expect($breakEval['end_break'])->not->toBeNull();
    expect($breakEval['detail_data'])->toBeArray();
    expect($breakEval['detail_data']['in']['time'])->toBe('12:02');
    expect($breakEval['detail_data']['out']['time'])->toBe('12:55');
});
