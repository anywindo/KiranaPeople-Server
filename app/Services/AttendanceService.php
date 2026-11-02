<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EventType;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use Illuminate\Validation\ValidationException;

class AttendanceService extends BaseService
{

    public function __construct(AttendanceRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Record an attendance event for an employee.
     */
    public function recordEvent(User $employee, Shift $shift, EventType $eventType, array $data): Attendance
    {
        // 1. Validate incoming data (GPS & Selfie)
        if (empty($data['latitude']) || empty($data['longitude'])) {
            throw ValidationException::withMessages([
                'location' => 'GPS coordinates (latitude and longitude) are required.'
            ]);
        }
        if (empty($data['selfie_path'])) {
            throw ValidationException::withMessages([
                'selfie_path' => 'Selfie photo is required.'
            ]);
        }

        // 2. Fetch or create today's Attendance aggregate
        /** @var Attendance|null $attendance */
        $attendance = $this->repository->findTodayAttendanceForUser($employee->id);

        if (!$attendance) {
            // First event must be START_SHIFT
            if ($eventType !== EventType::START_SHIFT) {
                throw ValidationException::withMessages([
                    'event_type' => 'Cannot record this event. You must start your shift first.'
                ]);
            }

            $initialStatus = AttendanceStatus::EXACT;
            if ($shift->default_start_time) {
                $scheduledStart = \Carbon\Carbon::parse(today()->format('Y-m-d') . ' ' . $shift->default_start_time);
                $ruleEnabled = (bool) \App\Models\Setting::get('attendance_rule_enabled', false);
                $now = now();
                if ($ruleEnabled) {
                    $ruleMinutes = (int) \App\Models\Setting::get('attendance_rule_minutes', 15);
                    $cutoffTime = $scheduledStart->copy()->subMinutes($ruleMinutes);
                    if ($now->greaterThanOrEqualTo($cutoffTime)) {
                        $initialStatus = AttendanceStatus::LATE;
                    } elseif ($now->lessThan($cutoffTime)) {
                        $initialStatus = AttendanceStatus::EARLY;
                    }
                } else {
                    if ($now->greaterThan($scheduledStart)) {
                        $initialStatus = AttendanceStatus::LATE;
                    } elseif ($now->lessThan($scheduledStart)) {
                        $initialStatus = AttendanceStatus::EARLY;
                    }
                }
            }

            $attendance = $this->repository->create([
                'user_id' => $employee->id,
                'shift_id' => $shift->id,
                'date' => today(),
                'status' => $initialStatus,
            ]);
        }

        // 3. Enforce sequence rules
        $events = $attendance->events()->orderBy('timestamp')->get();
        
        $hasStartedShift = $events->contains('event_type', EventType::START_SHIFT);
        $hasStartedBreak = $events->contains('event_type', EventType::START_BREAK);
        $hasEndedBreak = $events->contains('event_type', EventType::END_BREAK);
        $hasEndedShift = $events->contains('event_type', EventType::END_SHIFT);

        if ($hasEndedShift) {
            throw ValidationException::withMessages([
                'event_type' => 'Shift has already ended for today.'
            ]);
        }

        switch ($eventType) {
            case EventType::START_SHIFT:
                if ($hasStartedShift) {
                    throw ValidationException::withMessages([
                        'event_type' => 'Presensi masuk sudah dilakukan hari ini.'
                    ]);
                }

                $status = AttendanceStatus::EXACT;
                $latenessMinutes = 0;
                if ($shift->default_start_time) {
                    $scheduledStart = \Carbon\Carbon::parse(today()->format('Y-m-d') . ' ' . $shift->default_start_time);
                    $ruleEnabled = (bool) \App\Models\Setting::get('attendance_rule_enabled', false);
                    $now = now();
                    if ($ruleEnabled) {
                        $ruleMinutes = (int) \App\Models\Setting::get('attendance_rule_minutes', 15);
                        $cutoffTime = $scheduledStart->copy()->subMinutes($ruleMinutes);
                        if ($now->greaterThanOrEqualTo($cutoffTime)) {
                            $status = AttendanceStatus::LATE;
                            $latenessMinutes = (int) abs($now->diffInMinutes($cutoffTime));
                        } elseif ($now->lessThan($cutoffTime)) {
                            $status = AttendanceStatus::EARLY;
                        }
                    } else {
                        if ($now->greaterThan($scheduledStart)) {
                            $status = AttendanceStatus::LATE;
                            $latenessMinutes = (int) abs($now->diffInMinutes($scheduledStart));
                        } elseif ($now->lessThan($scheduledStart)) {
                            $status = AttendanceStatus::EARLY;
                        }
                    }
                }

                $attendance->update(['status' => $status]);

                if ($status === AttendanceStatus::LATE) {
                    $admins = rescue(fn() => \App\Models\User::role(['super-admin', 'HR'], 'web')->get(), collect());
                    if ($admins && $admins->isNotEmpty()) {
                        \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\EmployeeLateNotification($employee, $latenessMinutes));
                    }
                }
                break;
            case EventType::START_BREAK:
                if (!$hasStartedShift) {
                    throw ValidationException::withMessages([
                        'event_type' => 'Tidak dapat memulai istirahat. Anda harus melakukan presensi masuk kerja terlebih dahulu.'
                    ]);
                }
                if ($hasStartedBreak) {
                    throw ValidationException::withMessages([
                        'event_type' => 'Istirahat sudah dimulai sebelumnya.'
                    ]);
                }
                if ($shift->break_start) {
                    $breakStartTime = \Carbon\Carbon::parse($shift->break_start)->format('H:i:s');
                    $isFriday = now()->isFriday();
                    if ($isFriday && \App\Models\Setting::get('friday_break_enabled', true)) {
                        $fridayStart = \App\Models\Setting::get('friday_break_start', '11:30');
                        $breakStartTime = \Carbon\Carbon::parse($fridayStart)->format('H:i:s');
                    }

                    $currentTime = now()->format('H:i:s');
                    if ($currentTime < $breakStartTime) {
                        throw ValidationException::withMessages([
                            'event_type' => 'Belum waktu istirahat (jadwal istirahat mulai: '.$breakStartTime.').'
                        ]);
                    }
                }
                break;
            case EventType::END_BREAK:
                if (!$hasStartedShift) {
                    throw ValidationException::withMessages([
                        'event_type' => 'Tidak dapat menyelesaikan istirahat. Anda belum melakukan presensi masuk kerja.'
                    ]);
                }
                if (!$hasStartedBreak) {
                    throw ValidationException::withMessages([
                        'event_type' => 'Tidak dapat menyelesaikan istirahat sebelum memulainya.'
                    ]);
                }
                if ($hasEndedBreak) {
                    throw ValidationException::withMessages([
                        'event_type' => 'Istirahat sudah diselesaikan sebelumnya.'
                    ]);
                }
                break;
            case EventType::END_SHIFT:
                if (!$hasStartedShift) {
                    throw ValidationException::withMessages([
                        'event_type' => 'Tidak dapat melakukan presensi pulang. Anda belum melakukan presensi masuk kerja.'
                    ]);
                }
                if ($hasStartedBreak && !$hasEndedBreak) {
                    throw ValidationException::withMessages([
                        'event_type' => 'Tidak dapat pulang kerja saat masih istirahat. Selesaikan istirahat terlebih dahulu.'
                    ]);
                }
                break;
        }

        // 4. Record the event
        $attendance->events()->create([
            'event_type' => $eventType,
            'timestamp' => now(),
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'selfie_path' => $data['selfie_path'],
            'selfie_base64' => $data['selfie_base64'] ?? null,
        ]);

        return $attendance->load('events');
    }
}
