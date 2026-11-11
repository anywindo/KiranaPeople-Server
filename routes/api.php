<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['name' => config('app.name'), 'version' => config('app.version', '1.0.1'), 'status' => 'healthy']));
Route::get('health', fn () => response()->json(['status' => 'ok', 'timestamp' => now()]));

Route::prefix('v1')->group(function () {
    Route::get('/', fn () => response()->json(['name' => config('app.name'), 'api' => 'v1', 'status' => 'healthy']));

    // Auth Routes
    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('login', [AuthController::class, 'login']);
            Route::post('register', [AuthController::class, 'register']);
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

        // Employee Routes
        Route::prefix('employee')->group(function () {
            Route::get('attendance/today', [\App\Http\Controllers\Api\V1\Employee\AttendanceController::class, 'today']);
            Route::get('attendance/history', [\App\Http\Controllers\Api\V1\Employee\AttendanceController::class, 'history']);
            Route::post('attendance/events', [\App\Http\Controllers\Api\V1\Employee\AttendanceController::class, 'storeEvent']);

            Route::get('correction-requests', [\App\Http\Controllers\Api\V1\Employee\AttendanceCorrectionRequestController::class, 'index']);
            Route::post('correction-requests', [\App\Http\Controllers\Api\V1\Employee\AttendanceCorrectionRequestController::class, 'store']);
        });

        // Admin/HR Routes
        Route::prefix('admin')->middleware('role:admin|super-admin|HR')->group(function () {
            Route::apiResource('employees', \App\Http\Controllers\Api\V1\Admin\EmployeeController::class);
            Route::get('attendances', [\App\Http\Controllers\Api\V1\Admin\AttendanceController::class, 'index']);
            Route::get('correction-requests', [\App\Http\Controllers\Api\V1\Admin\AttendanceCorrectionRequestController::class, 'index']);
            Route::put('correction-requests/{id}/process', [\App\Http\Controllers\Api\V1\Admin\AttendanceCorrectionRequestController::class, 'process']);
        });

        // Teams
        Route::post('teams/{team}/members', [TeamController::class, 'addMember']);
        Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember']);
        Route::apiResource('teams', TeamController::class);
    });
});
