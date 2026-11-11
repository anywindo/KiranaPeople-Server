<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        if (Auth::user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return $this->renderAttendanceView($request, 'home');
    }

    public function history(Request $request)
    {
        return $this->renderAttendanceView($request, 'history');
    }

    public function profile(Request $request)
    {
        return $this->renderAttendanceView($request, 'profile');
    }

    private function renderAttendanceView(Request $request, string $activeNav = 'home')
    {
        $user = Auth::user();
        $today = Carbon::today();

        // Find today's attendance record with eager loaded relations
        $attendance = Attendance::with(['events' => fn ($q) => $q->orderBy('timestamp', 'asc'), 'shift'])
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        $events = $attendance ? $attendance->events : collect();

        // Determine the next allowed action based on existing events
        $nextAction = 'START_SHIFT';
        $activeBreak = false;

        if ($events->isNotEmpty()) {
            $lastEvent = $events->last()->event_type;

            if ($lastEvent === 'START_SHIFT') {
                $nextAction = 'START_BREAK_OR_END_SHIFT';
            } elseif ($lastEvent === 'START_BREAK') {
                $nextAction = 'END_BREAK';
                $activeBreak = true;
            } elseif ($lastEvent === 'END_BREAK') {
                $nextAction = 'START_BREAK_OR_END_SHIFT';
            } elseif ($lastEvent === 'END_SHIFT') {
                $nextAction = 'COMPLETED';
            }
        }
        $historyEvents = AttendanceEvent::whereHas('attendance', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->orderBy('timestamp', 'desc')->take(20)->get();

        $historyQuery = Attendance::with(['events' => fn ($q) => $q->orderBy('timestamp', 'asc'), 'shift'])
            ->where('user_id', $user->id);

        if ($request->filled('start_date')) {
            $historyQuery->whereDate('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $historyQuery->whereDate('date', '<=', $request->end_date);
        }

        $attendancesHistory = $historyQuery->orderBy('date', 'desc')
            ->take(30)
            ->get();

        $availableShifts = \App\Models\Shift::all();
        $shift = $attendance ? $attendance->shift : ($user->shift ?? $availableShifts->first());

        return view('attendance.index', compact('user', 'events', 'historyEvents', 'attendancesHistory', 'nextAction', 'activeBreak', 'shift', 'availableShifts', 'activeNav'));
    }

    public function store(Request $request, \App\Services\FileUploadService $fileUploadService)
    {
        $request->validate([
            'event_type' => 'required|in:START_SHIFT,START_BREAK,END_BREAK,END_SHIFT',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'selfie' => 'required|image|max:10240',
            'shift_id' => 'nullable|exists:shifts,id',
        ]);

        $user = Auth::user();

        // --- GEOFENCING VALIDATION ---
        if (! $user->location_id || ! $user->location) {
            return back()->with('error', 'Anda belum memiliki lokasi kerja yang diatur oleh HR. Silakan hubungi admin.');
        }

        $location = $user->location;
        $earthRadius = 6371000; // Radius of the earth in meters
        $latFrom = deg2rad($request->latitude);
        $lonFrom = deg2rad($request->longitude);
        $latTo = deg2rad($location->latitude);
        $lonTo = deg2rad($location->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
          cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        $distance = $angle * $earthRadius;

        if ($distance > $location->radius) {
            return back()->with('error', 'Jarak Anda '.round($distance).' meter. Melebihi radius maksimal '.$location->radius.' meter dari lokasi kerja.');
        }
        // --- END GEOFENCING VALIDATION ---

        $today = Carbon::today();

        // Check for lateness if START_SHIFT
        $status = 'EXACT';
        $latenessMinutes = 0;

        $shift = ($request->filled('shift_id') ? \App\Models\Shift::find($request->shift_id) : null)
            ?? $user->shift
            ?? \App\Models\Shift::first();
        $shiftId = $shift ? $shift->id : 1;

        if ($request->event_type === 'START_SHIFT') {
            if ($shift && $shift->default_start_time) {
                $scheduledStart = \Carbon\Carbon::parse($today->format('Y-m-d').' '.$shift->default_start_time);
                $ruleEnabled = (bool) \App\Models\Setting::get('attendance_rule_enabled', false);
                $cutoffMinutes = $ruleEnabled ? (int) \App\Models\Setting::get('attendance_rule_minutes', 15) : 0;

                $now = now();
                if ($ruleEnabled && $cutoffMinutes > 0) {
                    $lateThreshold = $scheduledStart->copy()->subMinutes($cutoffMinutes);
                    if ($now->greaterThanOrEqualTo($lateThreshold)) {
                        $latenessMinutes = (int) abs($now->diffInMinutes($lateThreshold));
                        $status = 'LATE';
                    } elseif ($now->lessThan($lateThreshold)) {
                        $status = 'EARLY';
                    }
                } else {
                    if ($now->greaterThan($scheduledStart)) {
                        $latenessMinutes = (int) abs($now->diffInMinutes($scheduledStart));
                        $status = 'LATE';
                    } elseif ($now->lessThan($scheduledStart)) {
                        $status = 'EARLY';
                    }
                }
            }
        }

        // Find or create today's attendance record
        $attendance = Attendance::firstOrCreate(
            ['user_id' => $user->id, 'date' => $today],
            ['status' => $status, 'shift_id' => $shiftId] // Using dynamic valid status
        );

        // If it's START_SHIFT and attendance was just created or we need to update status
        if ($request->event_type === 'START_SHIFT') {
            $attendance->update(['status' => $status]);
        }

        $storeBase64 = (bool) env('STORE_SELFIE_BASE64', false);
        if ($storeBase64) {
            $uploaded = $fileUploadService->uploadImageWithBase64($request->file('selfie'), 'attendance-selfies');
            $selfiePath = $uploaded['path'];
            $selfieBase64 = $uploaded['base64'];
        } else {
            $selfiePath = $fileUploadService->uploadImage($request->file('selfie'), 'attendance-selfies');
            $selfieBase64 = null;
        }

        AttendanceEvent::create([
            'attendance_id' => $attendance->id,
            'event_type' => $request->event_type,
            'timestamp' => now(),
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'selfie_path' => $selfiePath,
            'selfie_base64' => $selfieBase64,
        ]);

        $message = 'Kehadiran berhasil dicatat!';

        // Notification for late check-in
        if ($request->event_type === 'START_SHIFT' && $status === 'LATE') {
            try {
                $admins = \App\Models\User::role(['super-admin', 'HR'], 'web')->get();
                if ($admins->isNotEmpty()) {
                    \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\EmployeeLateNotification($user, $latenessMinutes));
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Late notification warning: '.$e->getMessage());
            }

            $message .= ' Anda terlambat '.$latenessMinutes.' menit.';
        }

        return redirect()->route('attendance.index')->with('success', $message);
    }

    public function updateProfile(Request $request, FileUploadService $fileUploadService)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'phone' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'password' => 'nullable|string|min:8|confirmed',
        ], [
            'password.min' => 'New password must be at least 8 characters.',
            'password.confirmed' => 'New password confirmation does not match.',
            'avatar.max' => 'Profile picture size must not exceed 5MB.',
            'avatar.image' => 'The file must be an image.',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                try {
                    Storage::disk('r2')->delete($user->avatar);
                } catch (\Throwable $e) {
                }
                try {
                    Storage::disk('public')->delete($user->avatar);
                } catch (\Throwable $e) {
                }
            }
            $uploaded = $fileUploadService->uploadImageWithBase64($request->file('avatar'), 'avatars');
            $validated['avatar'] = $uploaded['path'];
            $validated['avatar_base64'] = $uploaded['base64'];
        }

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }
        unset($validated['current_password']);

        $user->update($validated);

        return redirect()->route('attendance.profile')->with('success', 'Profile updated successfully!');
    }
}
