<?php

namespace App\Http\Controllers;

use App\Enums\EventType;
use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\Shift;
use App\Services\AttendanceService;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

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

        $availableShifts = Shift::getAllCached();
        $shift = $attendance ? $attendance->shift : ($user->shift ?? Shift::getFirstCached());

        return view('attendance.index', compact('user', 'events', 'historyEvents', 'attendancesHistory', 'nextAction', 'activeBreak', 'shift', 'availableShifts', 'activeNav'));
    }

    public function store(Request $request, FileUploadService $fileUploadService)
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

        $eventTypeRaw = $request->input('event_type');
        $lock = Cache::lock("attendance_lock:{$user->id}:{$eventTypeRaw}", 10);

        if (! $lock->get()) {
            return back()->with('error', 'Permintaan presensi sedang diproses. Mohon tunggu sejenak.');
        }

        try {
            $shift = ($request->filled('shift_id') ? Shift::findCached($request->shift_id) : null)
                ?? $user->shift
                ?? Shift::getFirstCached();

            if (! $shift) {
                return back()->with('error', 'Shift kerja tidak ditemukan. Silakan hubungi admin.');
            }

            $selfiePath = $fileUploadService->uploadImage($request->file('selfie'), 'attendance-selfies');
            $eventType = EventType::from($eventTypeRaw);

            $this->attendanceService->recordEvent($user, $shift, $eventType, [
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'selfie_path' => $selfiePath,
            ]);

            return redirect()->route('attendance.index')->with('success', 'Kehadiran berhasil dicatat!');
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first() ?? 'Presensi tidak dapat diproses.';

            return back()->with('error', $firstError);
        } finally {
            $lock->release();
        }
    }

    public function updateProfile(Request $request, FileUploadService $fileUploadService)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'phone' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'current_password' => ['required_with:password', 'current_password'],
            'password' => 'nullable|string|min:8|confirmed',
        ], [
            'current_password.required_with' => 'Current password is required to set a new password.',
            'current_password.current_password' => 'The provided current password does not match our records.',
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
            $validated['avatar'] = $fileUploadService->uploadImage($request->file('avatar'), 'avatars');
        }

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
            $validated['password_changed_at'] = now();
        } else {
            unset($validated['password']);
        }
        unset($validated['current_password']);

        $user->update($validated);

        return redirect()->route('attendance.profile')->with('success', 'Profile updated successfully!');
    }
}
