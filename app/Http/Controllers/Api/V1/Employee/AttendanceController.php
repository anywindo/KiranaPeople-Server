<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceEventRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\Shift;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Services\AttendanceService;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService,
        protected AttendanceRepositoryInterface $attendanceRepository,
        protected FileUploadService $fileUploadService
    ) {}

    /**
     * Get today's attendance status and next allowed action for the employee.
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();

        $attendance = $this->attendanceRepository->findTodayAttendanceForUser($user->id);
        if ($attendance) {
            $attendance->load(['events' => fn ($q) => $q->orderBy('timestamp', 'asc'), 'shift']);
        }

        $events = $attendance ? $attendance->events : collect();

        $hasStartedShift = $events->contains(fn ($e) => ($e->event_type?->value ?? $e->event_type) === 'START_SHIFT');
        $hasStartedBreak = $events->contains(fn ($e) => ($e->event_type?->value ?? $e->event_type) === 'START_BREAK');
        $hasEndedBreak = $events->contains(fn ($e) => ($e->event_type?->value ?? $e->event_type) === 'END_BREAK');
        $hasEndedShift = $events->contains(fn ($e) => ($e->event_type?->value ?? $e->event_type) === 'END_SHIFT');

        $nextAction = 'START_SHIFT';
        $activeBreak = false;

        if ($hasEndedShift) {
            $nextAction = null;
        } elseif ($hasStartedBreak && ! $hasEndedBreak) {
            $nextAction = 'END_BREAK';
            $activeBreak = true;
        } elseif ($hasStartedShift) {
            if ($hasEndedBreak) {
                $nextAction = 'END_SHIFT';
            } else {
                $nextAction = 'START_BREAK';
            }
        }

        $shift = $attendance?->shift ?? Shift::getFirstCached();
        $workLocation = $user->getLocationCached() ?? $user->location;

        $formattedEvents = $events->map(function ($event) {
            return [
                'id' => $event->id,
                'event_type' => $event->event_type instanceof EventType ? $event->event_type->value : (string) $event->event_type,
                'timestamp' => $event->timestamp ? Carbon::parse($event->timestamp)->format('Y-m-d H:i:s') : '',
                'latitude' => $event->latitude !== null ? (float) $event->latitude : null,
                'longitude' => $event->longitude !== null ? (float) $event->longitude : null,
                'accuracy' => $event->accuracy !== null ? (float) $event->accuracy : null,
                'is_mocked' => (bool) $event->is_mocked,
                'notes' => $event->notes,
                'selfie_url' => $event->selfie_url,
            ];
        });

        // Build available shifts list for the shift picker
        $availableShifts = Shift::getAllCached()->map(fn ($s) => $this->formatShift($s));

        $ruleEnabled = (bool) \App\Models\Setting::get('attendance_rule_enabled', false);
        $ruleMinutes = (int) \App\Models\Setting::get('attendance_rule_minutes', 15);

        return response()->json([
            'meta' => [
                'success' => true,
                'message' => 'Status presensi hari ini berhasil dimuat.',
            ],
            'data' => [
                'date' => $today->toDateString(),
                'status' => $attendance?->status?->value ?? ($attendance?->status ?? 'EXACT'),
                'shift_status' => $attendance ? ($attendance->shift_status['label'] ?? null) : null,
                'break_status' => $attendance ? ($attendance->break_status['label'] ?? '-') : '-',
                'next_allowed_action' => $nextAction,
                'active_break' => $activeBreak,
                'attendance_rule_enabled' => $ruleEnabled,
                'attendance_rule_minutes' => $ruleMinutes,
                'shift' => $this->formatShift($shift),
                'available_shifts' => $availableShifts,
                'work_location' => $workLocation ? [
                    'id' => $workLocation->id,
                    'name' => $workLocation->name,
                    'latitude' => (float) $workLocation->latitude,
                    'longitude' => (float) $workLocation->longitude,
                    'radius' => (float) $workLocation->radius,
                ] : null,
                'events' => $formattedEvents,
            ],
        ]);
    }

    /**
     * Format a shift object for API response including break times configured in admin panel.
     */
    private function formatShift(?Shift $shift): ?array
    {
        if (! $shift) {
            return null;
        }

        $breakStart = $shift->break_start ? Carbon::parse($shift->break_start)->format('H:i:s') : null;
        $breakEnd = $shift->break_end ? Carbon::parse($shift->break_end)->format('H:i:s') : null;

        $fridayBreakEnabled = \App\Models\Setting::get('friday_break_enabled', true);
        if (now()->isFriday() && $fridayBreakEnabled && $shift->break_start) {
            $fridayStart = \App\Models\Setting::get('friday_break_start', '11:30');
            $fridayEnd = \App\Models\Setting::get('friday_break_end', '13:00');
            $breakStart = Carbon::parse($fridayStart)->format('H:i:s');
            $breakEnd = Carbon::parse($fridayEnd)->format('H:i:s');
        }

        $ruleEnabled = (bool) \App\Models\Setting::get('attendance_rule_enabled', false);
        $ruleMinutes = (int) \App\Models\Setting::get('attendance_rule_minutes', 15);

        return [
            'id' => $shift->id,
            'name' => $shift->shift_name ?? $shift->name,
            'start_time' => $shift->default_start_time ?? ($shift->start_time ?? '08:00:00'),
            'end_time' => $shift->default_end_time ?? ($shift->end_time ?? '17:00:00'),
            'break_start' => $breakStart,
            'break_end' => $breakEnd,
            'break_start_time' => $breakStart,
            'break_end_time' => $breakEnd,
            'attendance_rule_enabled' => $ruleEnabled,
            'attendance_rule_minutes' => $ruleMinutes,
        ];
    }

    /**
     * View own attendance records.
     */
    public function history(Request $request)
    {
        $query = $request->user()->attendances()->with(['events', 'shift'])->latest('date');

        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->input('end_date'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('month')) {
            $query->whereMonth('date', $request->input('month'));
        }

        if ($request->filled('year')) {
            $query->whereYear('date', $request->input('year'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $attendances = $query->paginate($perPage);

        return AttendanceResource::collection($attendances);
    }

    /**
     * Submit an attendance event (e.g. check-in, check-out)
     */
    public function storeEvent(StoreAttendanceEventRequest $request): JsonResponse
    {
        $employee = $request->user();
        $eventTypeRaw = $request->validated('event_type');
        $eventType = EventType::from($eventTypeRaw);

        // 1. Check Idempotency-Key if provided by client
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->header('Idempotency-Key');
        $idempotencyCacheKey = null;
        if ($idempotencyKey) {
            $idempotencyCacheKey = "idempotency:attendance:{$employee->id}:{$idempotencyKey}";
            $cached = Cache::get($idempotencyCacheKey);
            if ($cached) {
                return response()->json($cached['payload'], $cached['status'], [
                    'X-Cache-Lookup' => 'HIT',
                ]);
            }
        }

        // 2. Concurrency Protection via Atomic Lock (per user & event type)
        $lockKey = "attendance_lock:{$employee->id}:{$eventTypeRaw}";
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            return response()->json([
                'meta' => [
                    'success' => false,
                    'message' => 'Permintaan presensi sedang diproses. Mohon tunggu sejenak.',
                ],
            ], 429);
        }

        try {
            // Rapid double-tap defense: check if this event was already created in the last 5 seconds
            $existingAttendance = $this->attendanceRepository->findTodayAttendanceForUser($employee->id);
            if ($existingAttendance) {
                $recentEvent = $existingAttendance->events()
                    ->where('event_type', $eventTypeRaw)
                    ->where('timestamp', '>=', now()->subSeconds(5))
                    ->first();

                if ($recentEvent) {
                    $responsePayload = [
                        'meta' => [
                            'success' => true,
                            'message' => 'Kehadiran berhasil dicatat!',
                        ],
                        'data' => [
                            'event_type' => $eventTypeRaw,
                            'timestamp' => Carbon::parse($recentEvent->timestamp)->format('Y-m-d H:i:s'),
                            'status' => $existingAttendance->status?->value ?? ($existingAttendance->status ?? 'EXACT'),
                        ],
                    ];

                    return response()->json($responsePayload, 200);
                }
            }

            $shiftId = $request->validated('shift_id');
            if ($shiftId) {
                $shift = Shift::findCached($shiftId) ?? Shift::findOrFail($shiftId);
            } elseif ($existingAttendance && $existingAttendance->shift) {
                $shift = $existingAttendance->shift;
            } else {
                $shift = Shift::getFirstCached();
            }

            // Geofence validation if employee has assigned location
            $location = $employee->getLocationCached() ?? $employee->location;
            if ($location) {
                $earthRadius = 6371000;
                $latFrom = deg2rad((float) $request->validated('latitude'));
                $lonFrom = deg2rad((float) $request->validated('longitude'));
                $latTo = deg2rad((float) $location->latitude);
                $lonTo = deg2rad((float) $location->longitude);

                $latDelta = $latTo - $latFrom;
                $lonDelta = $lonTo - $lonFrom;

                $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                    cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

                $distance = $angle * $earthRadius;
                if ($distance > $location->radius) {
                    return response()->json([
                        'meta' => [
                            'success' => false,
                            'message' => 'Jarak Anda '.round($distance).' meter. Melebihi radius maksimal '.round($location->radius).' meter dari lokasi kerja.',
                        ],
                    ], 422);
                }
            }

            $selfiePath = null;
            if ($request->hasFile('selfie')) {
                $selfiePath = $this->fileUploadService->uploadImage($request->file('selfie'), 'attendance-selfies');
            }

            $payload = $request->validated();
            $payload['selfie_path'] = $selfiePath;

            $telemetryKeys = [
                'integrity_status', 'is_rooted', 'is_hooked', 'is_emulator',
                'is_debugger', 'is_developer_mode', 'fake_gps_app_installed',
                'integrity_signals', 'app_signature',
            ];
            $telemetry = [];
            foreach ($telemetryKeys as $key) {
                if ($request->has($key)) {
                    $telemetry[$key] = $request->input($key);
                }
            }
            if (! empty($telemetry)) {
                $payload['device_telemetry'] = $telemetry;
            }

            $attendance = $this->attendanceService->recordEvent($employee, $shift, $eventType, $payload);

            $responsePayload = [
                'meta' => [
                    'success' => true,
                    'message' => 'Kehadiran berhasil dicatat!',
                ],
                'data' => [
                    'event_type' => $eventType->value,
                    'timestamp' => now()->format('Y-m-d H:i:s'),
                    'status' => $attendance->status?->value ?? ($attendance->status ?? 'EXACT'),
                ],
            ];

            if ($idempotencyKey && $idempotencyCacheKey) {
                Cache::put($idempotencyCacheKey, [
                    'payload' => $responsePayload,
                    'status' => 201,
                ], 300);
            }

            return response()->json($responsePayload, 201);
        } finally {
            $lock->release();
        }
    }
}
