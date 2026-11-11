<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    protected PasswordResetService $resetService;

    public function __construct(PasswordResetService $resetService)
    {
        $this->resetService = $resetService;
    }

    /**
     * Display the form to request a password reset link.
     */
    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a reset link & OTP to the given user.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ], PasswordResetService::validationMessages());

        $sent = $this->resetService->sendResetNotification($request->email);

        if (! $sent) {
            return back()->withErrors(['email' => 'Email tidak terdaftar dalam sistem.'])->withInput();
        }

        return back()->with('status', 'Tautan reset kata sandi beserta kode OTP telah dikirimkan ke email Anda.');
    }
}
