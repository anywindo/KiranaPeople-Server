<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\EventType;
use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        // Limit to 5 employees to keep seeding fast on remote cloud databases
        $employees = User::role('Employee')->take(5)->get();
        $shifts = Shift::all();

        if ($employees->isEmpty() || $shifts->isEmpty()) {
            return;
        }

        // Generate data for the past 3 days up to today
        $startDate = Carbon::today()->subDays(3);
        $endDate = Carbon::today();

        for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
            // Skip weekends
            if ($date->isWeekend()) {
                continue;
            }

            foreach ($employees as $employee) {
                $shift = $shifts->random();
                $isAbsent = rand(1, 100) <= 5; // 5% chance of absence
                $isLeave = rand(1, 100) <= 2; // 2% chance of leave

                if ($isLeave) {
                    Attendance::factory()->create([
                        'user_id' => $employee->id,
                        'shift_id' => $shift->id,
                        'date' => $date->toDateString(),
                        'status' => AttendanceStatus::ON_LEAVE,
                    ]);

                    continue;
                }

                if ($isAbsent) {
                    Attendance::factory()->create([
                        'user_id' => $employee->id,
                        'shift_id' => $shift->id,
                        'date' => $date->toDateString(),
                        'status' => AttendanceStatus::ABSENT,
                    ]);

                    continue;
                }

                // Calculate random check-in/out times
                $scheduledStart = Carbon::parse($shift->default_start_time);
                $scheduledEnd = Carbon::parse($shift->default_end_time);

                $isLate = rand(1, 100) <= 15; // 15% chance of being late
                $isEarlyOut = rand(1, 100) <= 10; // 10% chance of leaving early

                $actualStart = $isLate
                    ? $scheduledStart->copy()->addMinutes(rand(5, 60))
                    : $scheduledStart->copy()->subMinutes(rand(5, 30));

                $actualEnd = $isEarlyOut
                    ? $scheduledEnd->copy()->subMinutes(rand(5, 30))
                    : $scheduledEnd->copy()->addMinutes(rand(5, 60));

                $status = $isLate ? AttendanceStatus::LATE : AttendanceStatus::EXACT;

                $attendance = Attendance::factory()->create([
                    'user_id' => $employee->id,
                    'shift_id' => $shift->id,
                    'date' => $date->toDateString(),
                    'status' => $status,
                ]);

                // Start Shift Event
                AttendanceEvent::factory()->create([
                    'attendance_id' => $attendance->id,
                    'event_type' => EventType::START_SHIFT,
                    'timestamp' => $date->copy()->setTimeFromTimeString($actualStart->toTimeString()),
                ]);

                // Break Events
                if ($shift->break_start && $shift->break_end) {
                    $scheduledBreakStart = Carbon::parse($shift->break_start);
                    $scheduledBreakEnd = Carbon::parse($shift->break_end);

                    // Actual break start: slightly after scheduled
                    $actualBreakStart = $scheduledBreakStart->copy()->addMinutes(rand(1, 10));
                    // Actual break end: slightly before or after scheduled end
                    $actualBreakEnd = $scheduledBreakEnd->copy()->addMinutes(rand(-5, 5));

                    AttendanceEvent::factory()->create([
                        'attendance_id' => $attendance->id,
                        'event_type' => EventType::START_BREAK,
                        'timestamp' => $date->copy()->setTimeFromTimeString($actualBreakStart->toTimeString()),
                    ]);

                    AttendanceEvent::factory()->create([
                        'attendance_id' => $attendance->id,
                        'event_type' => EventType::END_BREAK,
                        'timestamp' => $date->copy()->setTimeFromTimeString($actualBreakEnd->toTimeString()),
                    ]);
                }

                // End Shift Event
                AttendanceEvent::factory()->create([
                    'attendance_id' => $attendance->id,
                    'event_type' => EventType::END_SHIFT,
                    'timestamp' => $date->copy()->setTimeFromTimeString($actualEnd->toTimeString()),
                ]);
            }
        }
    }
}
