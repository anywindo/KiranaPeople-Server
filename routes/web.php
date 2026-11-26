<?php

use App\Http\Controllers\Admin\AbsenceController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\AuditLogArchiveController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BreakController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OnboardingController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

$showLoginOrRedirect = function () {
    if (auth()->check()) {
        if (auth()->user()->mustChangePassword()) {
            return redirect()->route('onboarding.password');
        }

        // HR & Admin directly routed to admin dashboard
        return redirect()->intended(auth()->user()->isAdmin() ? '/admin/dashboard' : '/attendance');
    }

    return view('auth.login');
};

Route::get('/', $showLoginOrRedirect);
Route::get('/login', $showLoginOrRedirect)->name('login');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Password Reset Routes
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email')->middleware('throttle:3,15');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update')->middleware('throttle:5,15');
});

Route::get('/csrf-token', function () {
    return response()->json([
        'token' => csrf_token(),
        'authenticated' => auth()->check(),
    ]);
})->name('csrf.token');

// Onboarding Routes
Route::middleware('auth')->group(function () {
    Route::get('/onboarding/password', [OnboardingController::class, 'showPasswordForm'])->name('onboarding.password');
    Route::post('/onboarding/password', [OnboardingController::class, 'updatePassword'])->name('onboarding.password.update');
});

// Maintenance Route (Khusus Employee; HR & Superadmin otomatis diarahkan ke admin dashboard)
Route::get('/maintenance', function () {
    if (auth()->check() && auth()->user()->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    return view('maintenance');
})->name('maintenance');

Route::middleware(['auth', 'password.changed'])->group(function () {
    // Role Selection (HR & Admin langsung direct ke admin dashboard)
    Route::get('/role-selection', function () {
        if (! auth()->user()->isAdmin()) {
            return redirect()->route('maintenance');
        }

        return redirect()->route('admin.dashboard');
    })->name('role.selection');

    // Attendance routes: configurable employee web maintenance (defaults to standard active routes)
    if (config('app.employee_web_maintenance', false) && ! app()->runningUnitTests()) {
        Route::get('/attendance', function () {
            if (auth()->user()->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            return response()->view('maintenance');
        })->name('attendance.index');

        Route::post('/attendance', function () {
            if (auth()->user()->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            return redirect()->route('maintenance');
        })->name('attendance.store');

        Route::any('/attendance/{any?}', function () {
            if (auth()->user()->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            return redirect()->route('maintenance');
        })->where('any', '.*');
    } else {
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    }

    Route::get('/attendance/history', [AttendanceController::class, 'history'])->name('attendance.history');
    Route::get('/attendance/profile', [AttendanceController::class, 'profile'])->name('attendance.profile');
    Route::put('/attendance/profile', [AttendanceController::class, 'updateProfile'])->name('attendance.profile.update');

    // HR / Admin Routes
    Route::middleware(['role:admin|HR|super-admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::middleware(['permission:manage-rbac'])->group(function () {
            Route::resource('roles', RoleController::class);
        });
        Route::get('employees/template', [EmployeeController::class, 'downloadTemplate'])->name('employees.template');
        Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
        Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
        Route::resource('employees', EmployeeController::class);
        Route::resource('shifts', ShiftController::class);
        Route::resource('locations', LocationController::class);

        Route::get('attendances/export', [AdminAttendanceController::class, 'export'])->name('attendances.export');
        Route::get('attendances', [AdminAttendanceController::class, 'index'])->name('attendances.index');

        Route::get('breaks/export', [BreakController::class, 'export'])->name('breaks.export');
        Route::get('breaks', [BreakController::class, 'index'])->name('breaks.index');

        // Absences
        Route::get('absences', [AbsenceController::class, 'index'])->name('absences.index');

        // Settings
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('settings', [SettingController::class, 'update'])->name('settings.update');

        // Notifications
        Route::match(['get', 'post'], 'notifications/clear', function () {
            auth()->user()->notifications()->delete();

            return back();
        })->name('notifications.clear');

        Route::get('notifications/{id}/read', function ($id) {
            $notification = auth()->user()->notifications()->find($id);
            if ($notification) {
                $notification->markAsRead();
            }

            if (request()->filled('redirect_to')) {
                $redirectTo = trim((string) request('redirect_to'));
                $parsed = parse_url($redirectTo);
                $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
                $requestHost = request()->getHost();

                $isSafeRelative = str_starts_with($redirectTo, '/')
                    && ! str_starts_with($redirectTo, '//')
                    && ! str_starts_with($redirectTo, '/\\');

                $isSameOrigin = isset($parsed['scheme'], $parsed['host'])
                    && in_array(strtolower($parsed['scheme']), ['http', 'https'], true)
                    && in_array(strtolower($parsed['host']), array_filter([
                        $appHost ? strtolower($appHost) : null,
                        $requestHost ? strtolower($requestHost) : null,
                    ]), true);

                if ($isSafeRelative || $isSameOrigin) {
                    return redirect($redirectTo);
                }
            }

            $employeeName = null;
            if ($notification && isset($notification->data['employee_id'])) {
                $employeeName = User::find($notification->data['employee_id'])?->name;
            }
            if (! $employeeName && $notification && isset($notification->data['message'])) {
                $parts = explode(' terlambat ', $notification->data['message']);
                $employeeName = $parts[0] ?? null;
            }

            if ($employeeName) {
                return redirect()->route('admin.attendances.index', ['search' => trim($employeeName)]);
            }

            return redirect()->route('admin.attendances.index');
        })->name('notifications.read');

        // Profile
        Route::get('profile', [ProfileController::class, 'index'])->name('profile.index');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

        // Audit Logs (Superadmin Only)
        Route::middleware(['role:super-admin'])->group(function () {
            // Archives & Safe Purge (Dual-Gate: Mandatory Download + OTP)
            Route::get('audit-logs/archives', [AuditLogArchiveController::class, 'index'])->name('audit-logs.archives.index');
            Route::post('audit-logs/archives', [AuditLogArchiveController::class, 'store'])->name('audit-logs.archives.store');
            Route::get('audit-logs/archives/{archive}/download', [AuditLogArchiveController::class, 'download'])->name('audit-logs.archives.download');
            Route::post('audit-logs/archives/{archive}/request-otp', [AuditLogArchiveController::class, 'requestOtp'])->name('audit-logs.archives.request-otp');
            Route::post('audit-logs/archives/{archive}/purge', [AuditLogArchiveController::class, 'purge'])->name('audit-logs.archives.purge');

            Route::get('audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
        });
    });
});

// Serve storage files (supports serverless /tmp, public storage disk, R2 object storage, and legacy fallbacks)
Route::get('/storage/{path}', function ($path) {
    // 1. Path traversal and sanitization guard
    if (str_contains($path, '..') || str_contains($path, "\0") || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
        abort(404);
    }

    $filename = basename($path);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // 2. Strict allowed extension whitelist (prevent serving PHP source, env files, etc. - TN-SEC-05)
    $allowedMimes = [
        'webp' => 'image/webp',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
    ];

    if (! array_key_exists($ext, $allowedMimes)) {
        abort(404);
    }

    $mime = $allowedMimes[$ext];

    $respondFile = function ($content, $mime) {
        return response($content, 200)
            ->header('Content-Type', $mime)
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'public, max-age=86400');
    };

    // 3. Check in /tmp/storage/app/public/ (local lambda container cache)
    $tmpPath = '/tmp/storage/app/public/'.$path;
    if (file_exists($tmpPath) && is_file($tmpPath)) {
        return $respondFile(file_get_contents($tmpPath), $mime);
    }

    // 4. Fetch directly from Cloudflare R2 bucket
    try {
        if (Storage::disk('r2')->exists($path)) {
            $content = Storage::disk('r2')->get($path);
            @mkdir(dirname($tmpPath), 0777, true);
            @file_put_contents($tmpPath, $content);

            return $respondFile($content, $mime);
        }
    } catch (Throwable $e) {
    }

    // 5. Check in local public storage disk (storage/app/public/)
    try {
        if (Storage::disk('public')->exists($path)) {
            return $respondFile(Storage::disk('public')->get($path), $mime);
        }
    } catch (Throwable $e) {
    }

    // 6. Check only whitelisted legacy upload subdirectories in public_path() (never root public_path)
    $legacyDirs = ['selfies', 'avatars', 'attendance-selfies'];
    foreach ($legacyDirs as $dir) {
        $legacyFile = public_path($dir.'/'.$filename);
        if (file_exists($legacyFile) && is_file($legacyFile)) {
            return $respondFile(file_get_contents($legacyFile), $mime);
        }
    }

    abort(404);
})->where('path', '.*');
