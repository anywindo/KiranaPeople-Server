<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class OnboardingController extends Controller
{
    /**
     * Show onboarding password change form.
     */
    public function showPasswordForm()
    {
        $user = auth()->user();

        if (! $user->mustChangePassword()) {
            return redirect()->intended($user->isAdmin() ? '/admin/dashboard' : '/attendance');
        }

        return view('auth.onboarding-password', [
            'user' => $user,
        ]);
    }

    /**
     * Handle onboarding password change submission.
     */
    public function updatePassword(Request $request)
    {
        $user = auth()->user();

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
            return back()->withErrors([
                'password' => 'Kata sandi baru tidak boleh sama dengan kata sandi default atau tanggal lahir Anda.',
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->password_changed_at = now();
        $user->save();

        $target = $user->isAdmin() ? '/admin/dashboard' : '/attendance';

        return redirect()->intended($target)->with('success', 'Selamat datang! Kata sandi Anda berhasil diperbarui.');
    }
}
