<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
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

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
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

        $perPage = $request->input('per_page', 10);
        $attendances = $query->paginate($perPage)->appends($request->all());

        $shifts = \App\Models\Shift::getAllCached();
        $locations = \App\Models\Location::getAllCached();

        return view('admin.breaks.index', compact('attendances', 'shifts', 'locations'));
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

        return Excel::download(new \App\Exports\Admin\BreakExport($startDate, $endDate, $search, $shift_id, $location_id), 'break_history_'.date('Y-m-d_H-i-s').'.xlsx');
    }
}
