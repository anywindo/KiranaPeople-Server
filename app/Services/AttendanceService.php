<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EventType;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\EmployeeLateNotification;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class AttendanceService extends BaseService
{
    private const MAX_CLOCK_DRIFT_SECONDS = 600;

    public function __construct(AttendanceRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Record an attendance event for an employee.
     */
    public function recordEvent(User $employee, Shift $shift, EventType $eventType, array $data): Attendance
    {
        $this->validateRequiredPayload($data);
        $this->validateDeviceSecurity($employee, $eventType, $data);
        $this->validateClockDrift($data);

        return DB::transaction(function () use ($employee, $shift, $eventType, $data) {
            $attendance = $this->getOrCreateTodayAttendance($employee, $shift, $eventType);
            $this->validateEventSequence($attendance, $eventType, $shift);

            if ($eventType === EventType::START_SHIFT) {
                [$status, $latenessMinutes] = $this->determineShiftStartStatus($shift, now());
                $attendance->update(['status' => $status]);

                if ($status === AttendanceStatus::LATE) {
                    $this->notifyAdminsOfLateness($employee, $latenessMinutes);
                }
            }

            $this->createAttendanceEvent($attendance, $eventType, $data);

            return $attendance->load('events');
        });
    }

    /**
     * Validate presence of essential coordinates and photo.
     */
    protected function validateRequiredPayload(array $data): void
    {
        if (empty($data['latitude']) || empty($data['longitude'])) {
            throw ValidationException::withMessages([
                'location' => 'GPS coordinates (latitude and longitude) are required.',
            ]);
        }

        if (empty($data['selfie_path'])) {
            throw ValidationException::withMessages([
                'selfie_path' => 'Selfie photo is required.',
            ]);
        }
    }

    /**
     * Verify device anti-spoofing, mock location, and integrity heuristics.
     */
    protected function validateDeviceSecurity(User $employee, EventType $eventType, array $data): void
    {
        $isMocked = ! empty($data['is_mocked']) && filter_var($data['is_mocked'], FILTER_VALIDATE_BOOLEAN);
        $fakeGps = ! empty($data['fake_gps_app_installed']) && filter_var($data['fake_gps_app_installed'], FILTER_VALIDATE_BOOLEAN);

        if ($isMocked || $fakeGps) {
            $this->logSecurityViolation($employee, $eventType, 'Mock location / Fake GPS detected', $data);

            throw ValidationException::withMessages([
                'location' => 'Lokasi palsu (Mock Location / Fake GPS) terdeteksi. Harap nonaktifkan aplikasi lokasi palsu dan gunakan GPS asli perangkat.',
            ]);
        }

        if (isset($data['accuracy']) && is_numeric($data['accuracy']) && (float) $data['accuracy'] < 0) {
            throw ValidationException::withMessages([
                'accuracy' => 'Nilai akurasi GPS tidak valid.',
            ]);
        }

        $isRooted = ! empty($data['is_rooted']) && filter_var($data['is_rooted'], FILTER_VALIDATE_BOOLEAN);
        $isHooked = ! empty($data['is_hooked']) && filter_var($data['is_hooked'], FILTER_VALIDATE_BOOLEAN);
        $compromised = isset($data['integrity_status']) && strtolower((string) $data['integrity_status']) === 'compromised';

        if ($isRooted || $isHooked || $compromised) {
            $this->logSecurityViolation($employee, $eventType, 'Compromised / rooted device detected during attendance', $data);

            throw ValidationException::withMessages([
                'device_security' => 'Perangkat tidak lolos pemeriksaan integritas keamanan (terdeteksi root/hooking/modifikasi). Presensi ditolak.',
            ]);
        }
    }

    /**
     * Validate device timestamp drift against server time.
     */
    protected function validateClockDrift(array $data): void
    {
        if (empty($data['device_timestamp'])) {
            return;
        }

        try {
            $deviceTime = Carbon::parse($data['device_timestamp']);
            $driftSeconds = abs(now()->diffInSeconds($deviceTime));

            if ($driftSeconds > self::MAX_CLOCK_DRIFT_SECONDS) {
                throw ValidationException::withMessages([
                    'device_timestamp' => 'Waktu pada perangkat tidak sinkron dengan waktu server (selisih '.round($driftSeconds / 60).' menit). Harap gunakan pengaturan waktu otomatis.',
                ]);
            }
        } catch (Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
        }
    }

    /**
     * Audit security violation incident into audit_logs.
     */
    protected function logSecurityViolation(User $employee, EventType $eventType, string $violation, array $data): void
    {
        $afterPayload = [
            'violation' => $violation,
            'event_type' => $eventType->value,
            'telemetry' => $data['device_telemetry'] ?? null,
        ];

        if (isset($data['latitude'], $data['longitude'])) {
            $afterPayload['latitude'] = $data['latitude'];
            $afterPayload['longitude'] = $data['longitude'];
        }

        AuditLog::create([
            'user_id' => $employee->id,
            'event' => 'security_violation',
            'auditable_type' => User::class,
            'auditable_id' => $employee->id,
            'before' => null,
            'after' => $afterPayload,
            'ip_address' => request()?->ip() ?? '127.0.0.1',
            'user_agent' => request()?->userAgent() ?? 'System',
            'created_at' => now(),
        ]);
    }

    /**
     * Retrieve today's attendance aggregate or initialize a new record.
     */
    protected function getOrCreateTodayAttendance(User $employee, Shift $shift, EventType $eventType): Attendance
    {
        /** @var Attendance|null $attendance */
        $attendance = $this->repository->findTodayAttendanceForUser($employee->id, true);

        if ($attendance) {
            return $attendance;
        }

        if ($eventType !== EventType::START_SHIFT) {
            throw ValidationException::withMessages([
                'event_type' => 'Cannot record this event. You must start your shift first.',
            ]);
        }

        [$initialStatus] = $this->determineShiftStartStatus($shift, now());

        return $this->repository->create([
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'date' => today(),
            'status' => $initialStatus,
        ]);
    }

    /**
     * Determine attendance status (EXACT, EARLY, or LATE) and lateness minutes.
     *
     * @return array{0: AttendanceStatus, 1: int}
     */
    protected function determineShiftStartStatus(Shift $shift, Carbon $now): array
    {
        if (! $shift->default_start_time) {
            return [AttendanceStatus::EXACT, 0];
        }

        $scheduledStart = Carbon::parse(today()->format('Y-m-d').' '.$shift->default_start_time);
        $ruleEnabled = (bool) Setting::get('attendance_rule_enabled', false);

        if ($ruleEnabled) {
            $ruleMinutes = (int) Setting::get('attendance_rule_minutes', 15);
            $cutoffTime = $scheduledStart->copy()->subMinutes($ruleMinutes);

            if ($now->greaterThanOrEqualTo($cutoffTime)) {
                return [AttendanceStatus::LATE, (int) abs($now->diffInMinutes($cutoffTime))];
            }

            return [AttendanceStatus::EARLY, 0];
        }

        if ($now->greaterThan($scheduledStart)) {
            return [AttendanceStatus::LATE, (int) abs($now->diffInMinutes($scheduledStart))];
        }

        if ($now->lessThan($scheduledStart)) {
            return [AttendanceStatus::EARLY, 0];
        }

        return [AttendanceStatus::EXACT, 0];
    }

    /**
     * Enforce strict sequential state machine transitions for shift and breaks.
     */
    protected function validateEventSequence(Attendance $attendance, EventType $eventType, Shift $shift): void
    {
        /** @var Collection $events */
        $events = $attendance->events()->lockForUpdate()->orderBy('timestamp')->get();

        $hasStartedShift = $events->contains('event_type', EventType::START_SHIFT);
        $hasStartedBreak = $events->contains('event_type', EventType::START_BREAK);
        $hasEndedBreak = $events->contains('event_type', EventType::END_BREAK);
        $hasEndedShift = $events->contains('event_type', EventType::END_SHIFT);

        if ($hasEndedShift) {
            throw ValidationException::withMessages([
                'event_type' => 'Shift has already ended for today.',
            ]);
        }

        switch ($eventType) {
            case EventType::START_SHIFT:
                if ($hasStartedShift) {
                    throw ValidationException::withMessages([
                        'event_type' => 'Presensi masuk sudah dilakukan hari ini.',
                    ]);
                }
                break;

            case EventType::START_BREAK:
                $this->validateBreakStartSequence($hasStartedShift, $hasStartedBreak, $shift);
                break;

            case EventType::END_BREAK:
                $this->validateBreakEndSequence($hasStartedShift, $hasStartedBreak, $hasEndedBreak);
                break;

            case EventType::END_SHIFT:
                if (! $hasStartedShift) {
                    throw ValidationException::withMessages([
                        'event_type' => 'Tidak dapat melakukan presensi pulang. Anda belum melakukan presensi masuk kerja.',
                    ]);
                }
                if ($hasStartedBreak && ! $hasEndedBreak) {
                    throw ValidationException::withMessages([
                        'event_type' => 'Tidak dapat pulang kerja saat masih istirahat. Selesaikan istirahat terlebih dahulu.',
                    ]);
                }
                break;
        }
    }

    /**
     * Validate rules when employee starts a break.
     */
    protected function validateBreakStartSequence(bool $hasStartedShift, bool $hasStartedBreak, Shift $shift): void
    {
        if (! $hasStartedShift) {
            throw ValidationException::withMessages([
                'event_type' => 'Tidak dapat memulai istirahat. Anda harus melakukan presensi masuk kerja terlebih dahulu.',
            ]);
        }

        if ($hasStartedBreak) {
            throw ValidationException::withMessages([
                'event_type' => 'Istirahat sudah dimulai sebelumnya.',
            ]);
        }

        if (! $shift->break_start) {
            return;
        }

        $breakStartTime = Carbon::parse($shift->break_start)->format('H:i:s');
        $isFriday = now()->isFriday();

        if ($isFriday && Setting::get('friday_break_enabled', true)) {
            $fridayStart = Setting::get('friday_break_start', '11:30');
            $breakStartTime = Carbon::parse($fridayStart)->format('H:i:s');
        }

        $currentTime = now()->format('H:i:s');
        if ($currentTime < $breakStartTime) {
            throw ValidationException::withMessages([
                'event_type' => 'Belum waktu istirahat (jadwal istirahat mulai: '.$breakStartTime.').',
            ]);
        }
    }

    /**
     * Validate rules when employee ends a break.
     */
    protected function validateBreakEndSequence(bool $hasStartedShift, bool $hasStartedBreak, bool $hasEndedBreak): void
    {
        if (! $hasStartedShift) {
            throw ValidationException::withMessages([
                'event_type' => 'Tidak dapat menyelesaikan istirahat. Anda belum melakukan presensi masuk kerja.',
            ]);
        }

        if (! $hasStartedBreak) {
            throw ValidationException::withMessages([
                'event_type' => 'Tidak dapat menyelesaikan istirahat sebelum memulainya.',
            ]);
        }

        if ($hasEndedBreak) {
            throw ValidationException::withMessages([
                'event_type' => 'Istirahat sudah diselesaikan sebelumnya.',
            ]);
        }
    }

    /**
     * Notify supervisors/HR when an employee checks in late.
     */
    protected function notifyAdminsOfLateness(User $employee, int $latenessMinutes): void
    {
        $admins = rescue(fn () => User::role(['super-admin', 'HR'], 'web')->get(), collect());
        if ($admins && $admins->isNotEmpty()) {
            Notification::send($admins, new EmployeeLateNotification($employee, $latenessMinutes));
        }
    }

    /**
     * Persist attendance event record into database.
     */
    protected function createAttendanceEvent(Attendance $attendance, EventType $eventType, array $data): void
    {
        $attendance->events()->create([
            'event_type' => $eventType,
            'timestamp' => now(),
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'selfie_path' => $data['selfie_path'],
            'notes' => $data['notes'] ?? null,
            'accuracy' => isset($data['accuracy']) && is_numeric($data['accuracy']) ? (float) $data['accuracy'] : null,
            'altitude' => isset($data['altitude']) && is_numeric($data['altitude']) ? (float) $data['altitude'] : null,
            'is_mocked' => ! empty($data['is_mocked']) && filter_var($data['is_mocked'], FILTER_VALIDATE_BOOLEAN),
            'device_timestamp' => ! empty($data['device_timestamp']) ? Carbon::parse($data['device_timestamp']) : null,
            'device_telemetry' => $data['device_telemetry'] ?? null,
        ]);
    }
}
