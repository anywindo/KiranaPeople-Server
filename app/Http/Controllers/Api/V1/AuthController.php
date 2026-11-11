<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\UserResource;
use App\Services\AuthService;
use App\Services\FileUploadService;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class AuthController extends BaseController
{
    public function __construct(private readonly AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->created([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
            'expires_at' => $result['expires_at'],
        ], 'Registration successful');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return $this->success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
            'expires_at' => $result['expires_at'],
        ], 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->noContent('Logged out successfully');
    }

    public function refresh(Request $request): JsonResponse
    {
        $result = $this->authService->refresh($request->user());

        return $this->success($result, 'Token refreshed');
    }

    public function profile(Request $request): JsonResponse
    {
        return $this->success(
            new UserResource($request->user()->load('roles', 'location')),
            'Profile retrieved'
        );
    }

    public function updateProfile(Request $request, FileUploadService $fileUploadService): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'phone' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'phone_number' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ], [
            'avatar.max' => 'Ukuran foto profil tidak boleh melebihi 5MB.',
            'avatar.image' => 'File yang diunggah harus berupa gambar.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'phone_number.regex' => 'Format nomor telepon tidak valid.',
        ]);

        $updates = [];

        if ($request->has('phone')) {
            $updates['phone'] = $request->input('phone');
        } elseif ($request->has('phone_number')) {
            $updates['phone'] = $request->input('phone_number');
        }

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
            $updates['avatar'] = $fileUploadService->uploadImage($request->file('avatar'), 'avatars');
        }

        if (! empty($updates)) {
            $user->update($updates);
        }

        return $this->success(
            new UserResource($user->fresh()->load('roles', 'location')),
            'Profil berhasil diperbarui'
        );
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'nullable|string',
            'old_password' => 'nullable|string',
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers(),
            ],
        ], [
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.letters' => 'Kata sandi harus mengandung huruf.',
            'password.mixed_case' => 'Kata sandi harus mengandung huruf besar dan kecil.',
            'password.numbers' => 'Kata sandi harus mengandung angka.',
        ]);

        $currentPasswordProvided = $request->input('current_password') ?? $request->input('old_password');

        if (! $user->mustChangePassword()) {
            if (empty($currentPasswordProvided)) {
                return response()->json([
                    'meta' => [
                        'success' => false,
                        'message' => 'Kata sandi saat ini wajib diisi.',
                    ],
                    'errors' => [
                        'current_password' => ['Kata sandi saat ini wajib diisi.'],
                    ],
                ], 422);
            }

            if (! Hash::check($currentPasswordProvided, $user->password)) {
                return response()->json([
                    'meta' => [
                        'success' => false,
                        'message' => 'Kata sandi saat ini salah.',
                    ],
                    'errors' => [
                        'current_password' => ['Kata sandi saat ini tidak cocok.'],
                    ],
                ], 422);
            }
        }

        $defaultPwd = $user->getDefaultPassword();

        if ($request->password === $defaultPwd || strtolower($request->password) === 'password') {
            return response()->json([
                'meta' => [
                    'success' => false,
                    'message' => 'Kata sandi baru tidak boleh sama dengan kata sandi default atau tanggal lahir Anda.',
                ],
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->password_changed_at = now();
        $user->save();

        return $this->success(
            new UserResource($user->fresh()->load('roles', 'location')),
            'Kata sandi berhasil diperbarui'
        );
    }

    public function updateOnboardingPassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers(),
            ],
        ], [
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.letters' => 'Kata sandi harus mengandung huruf.',
            'password.mixed_case' => 'Kata sandi harus mengandung huruf besar dan kecil.',
            'password.numbers' => 'Kata sandi harus mengandung angka.',
        ]);

        $defaultPwd = $user->getDefaultPassword();

        if ($request->password === $defaultPwd || strtolower($request->password) === 'password') {
            return response()->json([
                'meta' => [
                    'success' => false,
                    'message' => 'Kata sandi baru tidak boleh sama dengan kata sandi default atau tanggal lahir Anda.',
                ],
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->password_changed_at = now();
        $user->save();

        return $this->success(
            new UserResource($user->fresh()->load('roles', 'location')),
            'Password updated successfully'
        );
    }

    /**
     * Send password reset OTP for mobile users.
     */
    public function forgotPassword(Request $request, PasswordResetService $service): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ], PasswordResetService::validationMessages());

        $sent = $service->sendResetNotification($request->email);

        if (! $sent) {
            return $this->error('Email tidak terdaftar dalam sistem.', 404, [
                'email' => ['Email tidak terdaftar dalam sistem.'],
            ]);
        }

        return $this->success(
            null,
            'Kode OTP verifikasi telah dikirimkan ke email Anda.'
        );
    }

    /**
     * Verify numeric OTP before setting a new password.
     */
    public function verifyOtp(Request $request, PasswordResetService $service): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ], PasswordResetService::validationMessages());

        $result = $service->verifyOtp(
            $request->email,
            $request->otp
        );

        if (! $result['success']) {
            return $this->error($result['message'], 422, [
                'otp' => [$result['message']],
            ]);
        }

        return $this->success(
            null,
            $result['message']
        );
    }

    /**
     * Reset password using numeric OTP.
     */
    public function resetPassword(Request $request, PasswordResetService $service): JsonResponse
    {
        $request->validate(array_merge([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'password' => PasswordResetService::passwordRules(),
        ]), PasswordResetService::validationMessages());

        $result = $service->resetWithOtp(
            $request->email,
            $request->otp,
            $request->password
        );

        if (! $result['success']) {
            return $this->error($result['message'], 422);
        }

        return $this->success(
            null,
            $result['message']
        );
    }
}
