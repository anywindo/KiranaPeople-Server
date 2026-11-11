<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    public function index(Request $request)
    {
        // Default to today if no date selected
        $date = $request->input('date', Carbon::today()->toDateString());
        $excludedRoles = json_decode(\App\Models\Setting::get('exclude_roles_from_attendance', '["super-admin"]'), true);
        if (! is_array($excludedRoles)) {
            $excludedRoles = ['super-admin'];
        }

        $query = User::active()
            ->whereDoesntHave('roles', function ($q) use ($excludedRoles) {
                $q->whereIn('name', $excludedRoles);
            })
            // Find users who DO NOT have attendance records today
            ->whereDoesntHave('attendances', function ($q) use ($date) {
                $q->whereDate('date', $date);
            });

        // Filter by search (name or NIK)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        // Filter by location
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        $perPage = $request->input('per_page', 10);
        $absentees = $query->with('location')->paginate($perPage)->appends($request->all());

        $locations = Location::getAllCached();

        return view('admin.absences.index', compact('absentees', 'date', 'locations'));
    }
}
