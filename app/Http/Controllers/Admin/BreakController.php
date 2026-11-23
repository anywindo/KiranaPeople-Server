<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Exports\Admin\BreakExport;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;

class BreakController extends Controller
{
    public function index(Request $request)
    {
        // Get attendances that have any break events
        $query = Attendance::with(['user.location', 'shift', 'events'])
            ->whereHas('events', function ($q) {
                $q->whereIn('event_type', ['START_BREAK', 'END_BREAK']);
            })
            ->orderBy('date', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        // Default date filter to today if 'date' query parameter is not explicitly provided
        $selectedDate = $request->has('date') ? $request->input('date') : now()->toDateString();

        if (! empty($selectedDate)) {
            $query->whereDate('date', $selectedDate);
        }

        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
        }

        if ($request->filled('location_id')) {
            $location_id = $request->location_id;
            $query->whereHas('user', function ($q) use ($location_id) {
                $q->where('location_id', $location_id);
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        $includeAll = $request->boolean('include_all_employees');

        if ($includeAll) {
            $baseAttendanceQuery = Attendance::with(['user.location', 'shift', 'events'])
                ->orderBy('date', 'desc');

            if ($request->filled('search')) {
                $search = $request->search;
                $baseAttendanceQuery->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%");
                });
            }
            if (! empty($selectedDate)) {
                $baseAttendanceQuery->whereDate('date', $selectedDate);
            }
            if ($request->filled('shift_id')) {
                $baseAttendanceQuery->where('shift_id', $request->shift_id);
            }
            if ($request->filled('location_id')) {
                $locId = $request->location_id;
                $baseAttendanceQuery->whereHas('user', function ($q) use ($locId) {
                    $q->where('location_id', $locId);
                });
            }

            $attendancesCollection = $baseAttendanceQuery->get();

            $excludedRoles = json_decode(Setting::get('exclude_roles_from_attendance', '["super-admin"]'), true);
            if (! is_array($excludedRoles)) {
                $excludedRoles = ['super-admin'];
            }

            $userQuery = User::active()
                ->with(['location'])
                ->whereDoesntHave('roles', function ($q) use ($excludedRoles) {
                    $q->whereIn('name', $excludedRoles);
                });

            if ($request->filled('search')) {
                $search = $request->search;
                $userQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%");
                });
            }

            if ($request->filled('location_id')) {
                $userQuery->where('location_id', $request->location_id);
            }

            $eligibleUsers = $userQuery->orderBy('name', 'asc')->get();

            $dates = collect();
            if (! empty($selectedDate)) {
                $dates->push($selectedDate);
            } else {
                $distinctDates = Attendance::distinct()
                    ->orderBy('date', 'desc')
                    ->pluck('date')
                    ->map(fn ($d) => Carbon::parse($d)->toDateString())
                    ->unique()
                    ->values();

                if ($distinctDates->isNotEmpty()) {
                    $dates = $distinctDates;
                } else {
                    $dates->push(now()->toDateString());
                }
            }

            $attendancesByDate = $attendancesCollection->groupBy(function ($att) {
                return Carbon::parse($att->date)->toDateString();
            });

            $defaultShift = Shift::first();
            $targetShift = $request->filled('shift_id') ? Shift::find($request->shift_id) : null;
            $allRecords = collect();

            foreach ($dates as $dateStr) {
                $dayAttendances = $attendancesByDate->get($dateStr, collect());
                $attendedUserIds = $dayAttendances->pluck('user_id')->all();

                foreach ($dayAttendances as $att) {
                    $allRecords->push($att);
                }

                foreach ($eligibleUsers as $user) {
                    if (in_array($user->id, $attendedUserIds)) {
                        continue;
                    }

                    $userShift = $user->shift ?? $defaultShift;
                    if ($request->filled('shift_id') && ($userShift?->id != $request->shift_id)) {
                        continue;
                    }

                    $absentAtt = new Attendance([
                        'user_id' => $user->id,
                        'date' => Carbon::parse($dateStr),
                        'shift_id' => $targetShift?->id ?? $userShift?->id,
                        'status' => AttendanceStatus::ABSENT,
                    ]);
                    $absentAtt->is_absent = true;
                    $absentAtt->setRelation('user', $user);
                    $absentAtt->setRelation('shift', $targetShift ?? $userShift);
                    $absentAtt->setRelation('events', collect());

                    $allRecords->push($absentAtt);
                }
            }

            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $currentItems = $allRecords->slice(($currentPage - 1) * $perPage, $perPage)->values();

            $attendances = new LengthAwarePaginator(
                $currentItems,
                $allRecords->count(),
                $perPage,
                $currentPage,
                [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                    'query' => array_merge($request->all(), ['date' => $selectedDate]),
                ]
            );
        } else {
            $attendances = $query->paginate($perPage)->appends(array_merge($request->all(), ['date' => $selectedDate]));
        }

        $shifts = Shift::getAllCached();
        $locations = Location::getAllCached();

        return view('admin.breaks.index', compact('attendances', 'shifts', 'locations', 'selectedDate'));
    }

    public function export(Request $request)
    {
        $startDate = null;
        $endDate = null;
        $search = null;
        $shift_id = null;
        $location_id = null;

        if ($request->export_type === 'today') {
            $startDate = now()->toDateString();
            $endDate = now()->toDateString();
        } elseif ($request->export_type === 'custom') {
            $startDate = $request->start_date;
            $endDate = $request->end_date;
        } elseif ($request->export_type === 'filter') {
            if ($request->filled('date')) {
                $startDate = $request->date;
                $endDate = $request->date;
            }
            $search = $request->search;
            $shift_id = $request->shift_id;
            $location_id = $request->location_id;
        }

        $includeAll = $request->has('include_all_employees')
            ? $request->boolean('include_all_employees')
            : true;

        $showStatus = true;
        if ($request->has('hide_status') && $request->boolean('hide_status')) {
            $showStatus = false;
        } elseif ($request->has('show_status')) {
            $showStatus = $request->boolean('show_status');
        }

        return Excel::download(new BreakExport($startDate, $endDate, $search, $shift_id, $location_id, $includeAll, $showStatus), 'break_history_'.date('Y-m-d_H-i-s').'.xlsx');
    }
}
