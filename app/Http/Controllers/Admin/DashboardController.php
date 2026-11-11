<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        $excludedRoles = json_decode(\App\Models\Setting::get('exclude_roles_from_attendance', '[]'), true) ?? [];

        $totalEmployeesQuery = User::query();
        if (! empty($excludedRoles)) {
            $totalEmployeesQuery->whereDoesntHave('roles', function ($q) use ($excludedRoles) {
                $q->whereIn('name', $excludedRoles);
            });
        }
        $totalEmployees = $totalEmployeesQuery->count();
        $attendancesToday = Attendance::with(['user.location', 'shift', 'events'])
            ->whereDate('date', '=', $today)
            ->get();

        $presentUserIds = $attendancesToday->pluck('user_id')->unique();

        $absentQuery = User::with(['location'])->whereNotIn('id', $presentUserIds);
        if (! empty($excludedRoles)) {
            $absentQuery->whereDoesntHave('roles', function ($q) use ($excludedRoles) {
                $q->whereIn('name', $excludedRoles);
            });
        }
        $absentEmployeesList = $absentQuery->get();

        $presentToday = $attendancesToday->count();
        $absentToday = $absentEmployeesList->count();
        $absentEmployees = $absentEmployeesList->take(10);

        $lateEmployees = $attendancesToday->filter(function ($attendance) {
            $checkIn = $attendance->events->where('event_type', \App\Enums\EventType::START_SHIFT)->first();
            $scheduledStart = $attendance->shift->default_start_time ? Carbon::parse($attendance->shift->default_start_time) : null;

            if ($checkIn && $scheduledStart) {
                // If actual start time (hours/minutes) > scheduled start time
                return $checkIn->timestamp->format('H:i') > $scheduledStart->format('H:i');
            }

            return false;
        })->map(function ($attendance) {
            $checkIn = $attendance->events->where('event_type', \App\Enums\EventType::START_SHIFT)->first();
            $scheduledStart = Carbon::parse($attendance->shift->default_start_time);
            $actualStart = $checkIn->timestamp;

            // Calculate lateness in minutes based on time difference for today
            $scheduledToday = $actualStart->copy()->setTimeFrom($scheduledStart);
            $lateMinutes = $scheduledToday->diffInMinutes($actualStart, false);

            $attendance->late_minutes = max(0, $lateMinutes);
            $attendance->actual_time = $actualStart->format('H:i');
            $attendance->scheduled_time = $scheduledStart->format('H:i');

            return $attendance;
        })->sortByDesc('late_minutes')->take(10); // Top 10 latest

        $earlyEmployees = $attendancesToday->filter(function ($attendance) {
            $checkOut = $attendance->events->where('event_type', \App\Enums\EventType::END_SHIFT)->first();
            $scheduledEnd = $attendance->shift->default_end_time ? Carbon::parse($attendance->shift->default_end_time) : null;

            if ($checkOut && $scheduledEnd) {
                // If actual end time (hours/minutes) < scheduled end time
                return $checkOut->timestamp->format('H:i') < $scheduledEnd->format('H:i');
            }

            return false;
        })->map(function ($attendance) {
            $checkOut = $attendance->events->where('event_type', \App\Enums\EventType::END_SHIFT)->first();
            $scheduledEnd = Carbon::parse($attendance->shift->default_end_time);
            $actualEnd = $checkOut->timestamp;

            // Calculate early leaving in minutes
            $scheduledToday = $actualEnd->copy()->setTimeFrom($scheduledEnd);
            $earlyMinutes = $actualEnd->diffInMinutes($scheduledToday, false);

            $attendance->early_minutes = max(0, $earlyMinutes);
            $attendance->actual_end_time = $actualEnd->format('H:i');
            $attendance->scheduled_end_time = $scheduledEnd->format('H:i');

            return $attendance;
        })->sortByDesc('early_minutes')->take(10); // Top 10 earliest

        return view('admin.dashboard', compact(
            'totalEmployees',
            'presentToday',
            'absentToday',
            'lateEmployees',
            'earlyEmployees',
            'absentEmployees'
        ));
    }
}
