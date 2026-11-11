<?php

use App\Enums\AttendanceStatus;
use App\Enums\RequestStatus;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceCorrectionRequestService;
use Illuminate\Validation\ValidationException;

function setupCorrectionServiceTest()
{
    $user = User::factory()->create();
    $manager = User::factory()->create();
    $shift = Shift::create([
        'shift_name' => 'Test Shift',
        'default_start_time' => '08:00:00',
        'default_end_time' => '17:00:00',
    ]);
    $attendance = Attendance::create([
        'user_id' => $user->id,
        'shift_id' => $shift->id,
        'date' => today(),
        'status' => AttendanceStatus::EXACT,
    ]);

    $service = app(AttendanceCorrectionRequestService::class);

    return [$user, $manager, $shift, $attendance, $service];
}

it('can submit a correction request', function () {
    [$user, $manager, $shift, $attendance, $service] = setupCorrectionServiceTest();

    $request = $service->submitRequest(
        $user,
        $attendance->id,
        'Forgot to check in',
        ['START_SHIFT' => '08:00:00']
    );

    expect($request->status)->toBe(RequestStatus::PENDING);
    expect($request->requested_changes)->toBeArray();
});

it('can approve a pending request', function () {
    [$user, $manager, $shift, $attendance, $service] = setupCorrectionServiceTest();

    $request = $service->submitRequest(
        $user,
        $attendance->id,
        'Forgot to check in',
        ['START_SHIFT' => '08:00:00']
    );

    $processed = $service->processRequest($request, $manager, RequestStatus::APPROVED, 'Approved');

    expect($processed->status)->toBe(RequestStatus::APPROVED);
    expect($processed->approved_by)->toBe($manager->id);
});

it('throws exception when processing already processed request', function () {
    [$user, $manager, $shift, $attendance, $service] = setupCorrectionServiceTest();

    $request = $service->submitRequest(
        $user,
        $attendance->id,
        'Forgot to check in',
        ['START_SHIFT' => '08:00:00']
    );

    $processed = $service->processRequest($request, $manager, RequestStatus::APPROVED);

    // Try processing again using the updated state
    $service->processRequest($processed, $manager, RequestStatus::REJECTED);
})->throws(ValidationException::class);
