<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        $excludedRoles = json_decode(Setting::get('exclude_roles_from_attendance', '[]'), true) ?? [];

        // 1. Total employees count via database
        $totalEmployeesQuery = User::query();
        if (! empty($excludedRoles)) {
            $totalEmployeesQuery->whereDoesntHave('roles', function ($q) use ($excludedRoles) {
                $q->whereIn('name', $excludedRoles);
            });
        }
        $totalEmployees = $totalEmployeesQuery->count();

        // 2. Present & Late counts today via direct database index
        $presentToday = Attendance::whereDate('date', '=', $today)->count();
        $lateCountToday = Attendance::whereDate('date', '=', $today)
            ->where('status', \App\Enums\AttendanceStatus::LATE)
            ->count();
        $onTimeToday = max(0, $presentToday - $lateCountToday);
        $onTimeRate = $presentToday > 0 ? round(($onTimeToday / $presentToday) * 100) : 0;

        // 3. Absent users: count and take 10 without hydrating entire user table
        $presentUserIds = Attendance::whereDate('date', '=', $today)->pluck('user_id');

        $absentQuery = User::with(['location'])->whereNotIn('id', $presentUserIds);
        if (! empty($excludedRoles)) {
            $absentQuery->whereDoesntHave('roles', function ($q) use ($excludedRoles) {
                $q->whereIn('name', $excludedRoles);
            });
        }
        $absentToday = (clone $absentQuery)->count();
        $absentEmployees = (clone $absentQuery)->take(50)->get();

        // 4. Past 7 days attendance trend
        $trendLabels = [];
        $trendOnTime = [];
        $trendLate = [];
        $trendAbsent = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dateStr = $date->toDateString();
            $trendLabels[] = $date->format('d M');

            $dayPresent = Attendance::whereDate('date', $dateStr)->count();
            $dayLate = Attendance::whereDate('date', $dateStr)->where('status', \App\Enums\AttendanceStatus::LATE)->count();
            $dayOnTime = max(0, $dayPresent - $dayLate);
            $dayAbsent = max(0, $totalEmployees - $dayPresent);

            $trendOnTime[] = $dayOnTime;
            $trendLate[] = $dayLate;
            $trendAbsent[] = $dayAbsent;
        }

        $trendData = [
            'labels' => $trendLabels,
            'on_time' => $trendOnTime,
            'late' => $trendLate,
            'absent' => $trendAbsent,
        ];

        // 5. Late employees: only load attendances that actually have a START_SHIFT event today
        $lateEmployees = Attendance::with(['user.location', 'shift', 'events' => function ($q) {
            $q->where('event_type', EventType::START_SHIFT);
        }])
            ->whereDate('date', '=', $today)
            ->whereHas('events', function ($q) {
                $q->where('event_type', EventType::START_SHIFT);
            })
            ->get()
            ->filter(function ($attendance) {
                $checkIn = $attendance->events->first(fn ($e) => ($e->event_type?->value ?? $e->event_type) === EventType::START_SHIFT->value || $e->event_type === EventType::START_SHIFT);
                $scheduledStart = $attendance->shift?->default_start_time ? Carbon::parse($attendance->shift->default_start_time) : null;

                if ($checkIn && $scheduledStart) {
                    return $checkIn->timestamp->format('H:i') > $scheduledStart->format('H:i');
                }

                return false;
            })
            ->map(function ($attendance) {
                $checkIn = $attendance->events->first(fn ($e) => ($e->event_type?->value ?? $e->event_type) === EventType::START_SHIFT->value || $e->event_type === EventType::START_SHIFT);
                $scheduledStart = Carbon::parse($attendance->shift->default_start_time);
                $actualStart = $checkIn->timestamp;

                $scheduledToday = $actualStart->copy()->setTimeFrom($scheduledStart);
                $lateMinutes = $scheduledToday->diffInMinutes($actualStart, false);

                $attendance->late_minutes = max(0, $lateMinutes);
                $attendance->actual_time = $actualStart->format('H:i');
                $attendance->scheduled_time = $scheduledStart->format('H:i');

                return $attendance;
            })
            ->sortByDesc('late_minutes')
            ->take(10);

        // 5. Early leavers: only load attendances that actually have an END_SHIFT event today
        $earlyEmployees = Attendance::with(['user.location', 'shift', 'events' => function ($q) {
            $q->where('event_type', EventType::END_SHIFT);
        }])
            ->whereDate('date', '=', $today)
            ->whereHas('events', function ($q) {
                $q->where('event_type', EventType::END_SHIFT);
            })
            ->get()
            ->filter(function ($attendance) {
                $checkOut = $attendance->events->first(fn ($e) => ($e->event_type?->value ?? $e->event_type) === EventType::END_SHIFT->value || $e->event_type === EventType::END_SHIFT);
                $scheduledEnd = $attendance->shift?->default_end_time ? Carbon::parse($attendance->shift->default_end_time) : null;

                if ($checkOut && $scheduledEnd) {
                    return $checkOut->timestamp->format('H:i') < $scheduledEnd->format('H:i');
                }

                return false;
            })
            ->map(function ($attendance) {
                $checkOut = $attendance->events->first(fn ($e) => ($e->event_type?->value ?? $e->event_type) === EventType::END_SHIFT->value || $e->event_type === EventType::END_SHIFT);
                $scheduledEnd = Carbon::parse($attendance->shift->default_end_time);
                $actualEnd = $checkOut->timestamp;

                $scheduledToday = $actualEnd->copy()->setTimeFrom($scheduledEnd);
                $earlyMinutes = $actualEnd->diffInMinutes($scheduledToday, false);

                $attendance->early_minutes = max(0, $earlyMinutes);
                $attendance->actual_end_time = $actualEnd->format('H:i');
                $attendance->scheduled_end_time = $scheduledEnd->format('H:i');

                return $attendance;
            })
            ->sortByDesc('early_minutes')
            ->take(10);

        // 7. Live Activity Radar (Latest 8 events today)
        $liveActivity = \App\Models\AttendanceEvent::with(['attendance.user.location', 'attendance.shift'])
            ->whereDate('timestamp', $today)
            ->latest('timestamp')
            ->take(8)
            ->get();

        return view('admin.dashboard', compact(
            'totalEmployees',
            'presentToday',
            'absentToday',
            'lateCountToday',
            'onTimeToday',
            'onTimeRate',
            'trendData',
            'liveActivity',
            'lateEmployees',
            'earlyEmployees',
            'absentEmployees'
        ));
    }
}
