<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class SettingController extends Controller
{
    public function index()
    {
        $attendanceRuleEnabled = Setting::get('attendance_rule_enabled', false);
        $attendanceRuleMinutes = Setting::get('attendance_rule_minutes', 15);
        $excludedRoles = json_decode(Setting::get('exclude_roles_from_attendance', '[]'), true);
        $roles = Role::all();

        $fridayBreakEnabled = Setting::get('friday_break_enabled', true);
        $fridayBreakStart = Setting::get('friday_break_start', '11:30');
        $fridayBreakEnd = Setting::get('friday_break_end', '13:00');

        return view('admin.settings.index', compact(
            'attendanceRuleEnabled',
            'attendanceRuleMinutes',
            'excludedRoles',
            'roles',
            'fridayBreakEnabled',
            'fridayBreakStart',
            'fridayBreakEnd'
        ));
    }

    public function update(Request $request)
    {
        $group = $request->input('setting_group');

        if ($group === 'attendance_rule') {
            $request->validate([
                'attendance_rule_enabled' => 'nullable|boolean',
                'attendance_rule_minutes' => 'required|integer|min:0',
            ]);

            Setting::set('attendance_rule_enabled', $request->has('attendance_rule_enabled') ? 'true' : 'false', 'boolean');
            Setting::set('attendance_rule_minutes', $request->input('attendance_rule_minutes'), 'integer');

            return back()->with('success', 'Pengaturan rule presensi berhasil diperbarui.');
        }

        if ($group === 'friday_break') {
            $request->validate([
                'friday_break_enabled' => 'nullable|boolean',
                'friday_break_start' => 'nullable|date_format:H:i',
                'friday_break_end' => 'nullable|date_format:H:i',
            ]);

            Setting::set('friday_break_enabled', $request->has('friday_break_enabled') ? 'true' : 'false', 'boolean');
            Setting::set('friday_break_start', $request->input('friday_break_start', '11:30'), 'string');
            Setting::set('friday_break_end', $request->input('friday_break_end', '13:00'), 'string');

            return back()->with('success', 'Pengaturan istirahat hari Jumat berhasil diperbarui.');
        }

        if ($group === 'exclude_roles') {
            $request->validate([
                'exclude_roles' => 'nullable|array',
                'exclude_roles.*' => 'string',
            ]);

            Setting::set('exclude_roles_from_attendance', json_encode($request->input('exclude_roles', [])), 'string');

            return back()->with('success', 'Pengaturan pengecualian presensi berhasil diperbarui.');
        }

        // Fallback for full or legacy updates without setting_group
        if ($request->has('attendance_rule_minutes')) {
            $request->validate([
                'attendance_rule_enabled' => 'nullable|boolean',
                'attendance_rule_minutes' => 'required|integer|min:0',
            ]);
            Setting::set('attendance_rule_enabled', $request->has('attendance_rule_enabled') ? 'true' : 'false', 'boolean');
            Setting::set('attendance_rule_minutes', $request->input('attendance_rule_minutes'), 'integer');
        }

        if ($request->hasAny(['friday_break_enabled', 'friday_break_start', 'friday_break_end'])) {
            $request->validate([
                'friday_break_enabled' => 'nullable|boolean',
                'friday_break_start' => 'nullable|date_format:H:i',
                'friday_break_end' => 'nullable|date_format:H:i',
            ]);
            Setting::set('friday_break_enabled', $request->has('friday_break_enabled') ? 'true' : 'false', 'boolean');
            Setting::set('friday_break_start', $request->input('friday_break_start', '11:30'), 'string');
            Setting::set('friday_break_end', $request->input('friday_break_end', '13:00'), 'string');
        }

        if ($request->has('exclude_roles')) {
            $request->validate([
                'exclude_roles' => 'nullable|array',
                'exclude_roles.*' => 'string',
            ]);
            Setting::set('exclude_roles_from_attendance', json_encode($request->input('exclude_roles', [])), 'string');
        }

        return back()->with('success', 'Pengaturan berhasil diperbarui.');
    }
}
