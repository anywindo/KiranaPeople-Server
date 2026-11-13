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

        // 4. Past 7 days attendance trend (optimized to 1 single aggregated query instead of 14 queries)
        $sevenDaysAgo = Carbon::today()->subDays(6)->toDateString();
        $todayStr = $today->toDateString();

        $trendRecords = Attendance::whereBetween('date', [$sevenDaysAgo, $todayStr])
            ->selectRaw('date, count(*) as total_present, sum(case when status = ? then 1 else 0 end) as total_late', [\App\Enums\AttendanceStatus::LATE->value])
            ->groupBy('date')
            ->get()
            ->keyBy(function ($record) {
                return Carbon::parse($record->date)->toDateString();
            });

        $trendLabels = [];
        $trendOnTime = [];
        $trendLate = [];
        $trendAbsent = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dateStr = $date->toDateString();
            $trendLabels[] = $date->format('d M');

            $dayRecord = $trendRecords->get($dateStr);
            $dayPresent = $dayRecord ? (int) $dayRecord->total_present : 0;
            $dayLate = $dayRecord ? (int) $dayRecord->total_late : 0;
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

        // 5. Late employees: only load attendances marked LATE with events today (bounded query)
        $lateEmployees = Attendance::with(['user.location', 'shift', 'events' => function ($q) {
            $q->where('event_type', EventType::START_SHIFT);
        }])
            ->whereDate('date', '=', $today)
            ->where('status', \App\Enums\AttendanceStatus::LATE)
            ->whereHas('events', function ($q) {
                $q->where('event_type', EventType::START_SHIFT);
            })
            ->take(30)
            ->get()
            ->map(function ($attendance) {
                $checkIn = $attendance->events->first(fn ($e) => ($e->event_type?->value ?? $e->event_type) === EventType::START_SHIFT->value || $e->event_type === EventType::START_SHIFT);
                $scheduledStart = $attendance->shift?->default_start_time ? Carbon::parse($attendance->shift->default_start_time) : null;
                $actualStart = $checkIn?->timestamp;

                $lateMinutes = 0;
                if ($checkIn && $scheduledStart) {
                    $scheduledToday = $actualStart->copy()->setTimeFrom($scheduledStart);
                    $lateMinutes = max(0, $scheduledToday->diffInMinutes($actualStart, false));
                }

                $attendance->late_minutes = $lateMinutes;
                $attendance->actual_time = $actualStart ? $actualStart->format('H:i') : '-';
                $attendance->scheduled_time = $scheduledStart ? $scheduledStart->format('H:i') : '-';

                return $attendance;
            })
            ->sortByDesc('late_minutes')
            ->take(10);

        // 6. Early leavers: only load attendances that have an END_SHIFT event today (bounded query)
        $earlyEmployees = Attendance::with(['user.location', 'shift', 'events' => function ($q) {
            $q->where('event_type', EventType::END_SHIFT);
        }])
            ->whereDate('date', '=', $today)
            ->whereHas('events', function ($q) {
                $q->where('event_type', EventType::END_SHIFT);
            })
            ->take(50)
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
