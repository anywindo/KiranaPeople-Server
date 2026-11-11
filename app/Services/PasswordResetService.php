<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordResetService
{
    public const EXPIRATION_MINUTES = 15;

    /**
     * Send password reset notification containing token link and numeric OTP.
     */
    public function sendResetNotification(string $email): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $user = User::where('email', $normalizedEmail)->first();

        if (! $user) {
            return false;
        }

        $token = Str::random(60);
        $otp = sprintf('%06d', random_int(0, 999999));

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $normalizedEmail],
            [
                'token' => Hash::make($token),
                'otp' => Hash::make($otp),
                'created_at' => now(),
            ]
        );

        $user->notify(new ResetPasswordNotification($token, $otp));

        return true;
    }

    /**
     * Reset password using web token.
     *
     * @return array{success: bool, message: string}
     */
    public function resetWithToken(string $email, string $token, string $password): array
    {
        $normalizedEmail = strtolower(trim($email));
        $record = DB::table('password_reset_tokens')->where('email', $normalizedEmail)->first();

        if (! $record || empty($record->token)) {
            return [
                'success' => false,
                'message' => 'Token reset kata sandi tidak valid atau tidak ditemukan.',
            ];
        }

        if (Carbon::parse($record->created_at)->addMinutes(self::EXPIRATION_MINUTES)->isPast()) {
            return [
                'success' => false,
                'message' => 'Token reset kata sandi telah kedaluwarsa. Silakan ajukan permohonan baru.',
            ];
        }

        if (! Hash::check($token, $record->token)) {
            return [
                'success' => false,
                'message' => 'Token reset kata sandi tidak valid.',
            ];
        }

        $user = User::where('email', $normalizedEmail)->first();
        if (! $user) {
            return [
                'success' => false,
                'message' => 'Pengguna tidak ditemukan.',
            ];
        }

        if (self::isProhibitedPassword($user, $password)) {
            return [
                'success' => false,
                'message' => 'Kata sandi tidak boleh menggunakan tanggal lahir atau kata sandi default.',
            ];
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'password_changed_at' => now(),
        ])->save();

        // Revoke Sanctum tokens
        $user->tokens()->delete();

        // Delete used token record
        DB::table('password_reset_tokens')->where('email', $normalizedEmail)->delete();

        return [
            'success' => true,
            'message' => 'Kata sandi Anda berhasil diperbarui. Silakan masuk dengan kata sandi baru.',
        ];
    }

    /**
     * Verify numeric OTP without resetting password (Mobile API).
     *
     * @return array{success: bool, message: string}
     */
    public function verifyOtp(string $email, string $otp): array
    {
        $normalizedEmail = strtolower(trim($email));
        $record = DB::table('password_reset_tokens')->where('email', $normalizedEmail)->first();

        if (! $record || empty($record->otp)) {
            return [
                'success' => false,
                'message' => 'Permintaan reset kata sandi tidak ditemukan atau telah kedaluwarsa.',
            ];
        }

        if (Carbon::parse($record->created_at)->addMinutes(self::EXPIRATION_MINUTES)->isPast()) {
            return [
                'success' => false,
                'message' => 'Kode OTP telah kedaluwarsa. Silakan ajukan kode baru.',
            ];
        }

        if (! Hash::check($otp, $record->otp)) {
            return [
                'success' => false,
                'message' => 'Kode OTP yang Anda masukkan salah.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Kode OTP valid.',
        ];
    }

    /**
     * Reset password using numeric OTP (Mobile API).
     *
     * @return array{success: bool, message: string}
     */
    public function resetWithOtp(string $email, string $otp, string $password): array
    {
        $normalizedEmail = strtolower(trim($email));
        $record = DB::table('password_reset_tokens')->where('email', $normalizedEmail)->first();

        if (! $record || empty($record->otp)) {
            return [
                'success' => false,
                'message' => 'Permintaan reset kata sandi tidak ditemukan atau telah kedaluwarsa.',
            ];
        }

        if (Carbon::parse($record->created_at)->addMinutes(self::EXPIRATION_MINUTES)->isPast()) {
            return [
                'success' => false,
                'message' => 'Kode OTP telah kedaluwarsa. Silakan ajukan kode baru.',
            ];
        }

        if (! Hash::check($otp, $record->otp)) {
            return [
                'success' => false,
                'message' => 'Kode OTP yang Anda masukkan salah.',
            ];
        }

        $user = User::where('email', $normalizedEmail)->first();
        if (! $user) {
            return [
                'success' => false,
                'message' => 'Pengguna tidak ditemukan.',
            ];
        }

        if (self::isProhibitedPassword($user, $password)) {
            return [
                'success' => false,
                'message' => 'Kata sandi tidak boleh menggunakan tanggal lahir atau kata sandi default.',
            ];
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'password_changed_at' => now(),
        ])->save();

        // Revoke Sanctum tokens
        $user->tokens()->delete();

        // Delete used token record
        DB::table('password_reset_tokens')->where('email', $normalizedEmail)->delete();

        return [
            'success' => true,
            'message' => 'Kata sandi Anda berhasil diperbarui. Silakan masuk kembali.',
        ];
    }

    /**
     * Password validation rules.
     */
    public static function passwordRules(): array
    {
        return [
            'required',
            'string',
            'confirmed',
            Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers(),
        ];
    }

    /**
     * Validation messages in Indonesian.
     */
    public static function validationMessages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'token.required' => 'Token reset kata sandi wajib disertakan.',
            'otp.required' => 'Kode OTP wajib diisi.',
            'otp.size' => 'Kode OTP harus 6 digit angka.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.letters' => 'Kata sandi harus mengandung huruf.',
            'password.mixed_case' => 'Kata sandi harus mengandung huruf besar dan kecil.',
            'password.numbers' => 'Kata sandi harus mengandung angka.',
        ];
    }

    /**
     * Check if chosen password is a forbidden default (DOB or generic).
     */
    public static function isProhibitedPassword(User $user, string $password): bool
    {
        if (strtolower($password) === 'password') {
            return true;
        }

        $defaultPwd = $user->getDefaultPassword();
        if ($defaultPwd && $password === $defaultPwd) {
            return true;
        }

        return false;
    }
}
