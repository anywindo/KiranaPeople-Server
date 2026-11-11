<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;

class ResetPasswordController extends Controller
{
    protected PasswordResetService $resetService;

    public function __construct(PasswordResetService $resetService)
    {
        $this->resetService = $resetService;
    }

    /**
     * Display the password reset view for the given token.
     */
    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Reset the given user's password.
     */
    public function reset(Request $request)
    {
        $request->validate(array_merge([
            'token' => 'required',
            'email' => 'required|email',
            'password' => PasswordResetService::passwordRules(),
        ]), PasswordResetService::validationMessages());

        $result = $this->resetService->resetWithToken(
            $request->email,
            $request->token,
            $request->password
        );

        if (! $result['success']) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $result['message']]);
        }

        return redirect('/')->with('success', $result['message']);
    }
}
