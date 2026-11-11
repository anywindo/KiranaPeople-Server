<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->mustChangePassword()) {
            if ($request->routeIs('onboarding.*') || $request->is('onboarding*') || $request->routeIs('logout') || $request->is('logout')) {
                return $next($request);
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'meta' => [
                        'success' => false,
                        'message' => 'Password change required before proceeding.',
                        'requires_onboarding' => true,
                    ],
                ], 403);
            }

            return redirect()->route('onboarding.password');
        }

        // If user already changed password and visits onboarding, redirect to app
        if ($user && ! $user->mustChangePassword() && ($request->routeIs('onboarding.*') || $request->is('onboarding*'))) {
            return redirect()->intended($user->isAdmin() ? '/admin/dashboard' : '/attendance');
        }

        return $next($request);
    }
}
