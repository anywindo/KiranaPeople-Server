<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\V1\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Api\V1\Admin\AttendanceCorrectionRequestController as AdminAttendanceCorrectionRequestController;
use App\Http\Controllers\Api\V1\Admin\EmployeeController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Employee\AttendanceController;
use App\Http\Controllers\Api\V1\Employee\AttendanceCorrectionRequestController;
use App\Http\Controllers\Api\V1\SecurityIncidentController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['name' => config('app.name'), 'version' => config('app.version', '1.0.7'), 'status' => 'healthy']));
Route::get('health', HealthController::class);

Route::prefix('v1')->group(function () {
    Route::get('/', fn () => response()->json(['name' => config('app.name'), 'api' => 'v1', 'status' => 'healthy']));

    // Auth Routes
    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('login', [AuthController::class, 'login']);
            // Public self-registration is disabled for enterprise workforce security (TN-SEC-04).
            // Accounts are provisioned exclusively by Admin/HR.
        });

        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,15');
        Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:5,15');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,15');

        Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
            Route::get('profile', [AuthController::class, 'profile']);
            Route::match(['put', 'post'], 'profile', [AuthController::class, 'updateProfile']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
        });
    });

    // Protected API Routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        // Onboarding Password Route
        Route::post('onboarding/password', [AuthController::class, 'updateOnboardingPassword']);

        // Client-side Security Incident Telemetry (MASVS-AUTH / SEC-08 / SEC-20)
        Route::post('security/incident', [SecurityIncidentController::class, 'store'])
            ->middleware('throttle:attendance-events');

        // Employee Routes
        Route::prefix('employee')->group(function () {
            Route::get('attendance/today', [AttendanceController::class, 'today']);
            Route::get('attendance/history', [AttendanceController::class, 'history']);
            Route::post('attendance/events', [AttendanceController::class, 'storeEvent'])
                ->middleware('throttle:attendance-events');

            Route::get('correction-requests', [AttendanceCorrectionRequestController::class, 'index']);
            Route::post('correction-requests', [AttendanceCorrectionRequestController::class, 'store']);
        });

        // Admin/HR Routes
        Route::prefix('admin')->middleware('role:admin|super-admin|HR')->group(function () {
            Route::apiResource('employees', EmployeeController::class);
            Route::get('attendances', [AdminAttendanceController::class, 'index']);
            Route::get('correction-requests', [AdminAttendanceCorrectionRequestController::class, 'index']);
            Route::put('correction-requests/{id}/process', [AdminAttendanceCorrectionRequestController::class, 'process']);
        });
    });
});
