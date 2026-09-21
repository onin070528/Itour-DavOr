<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Handles user sign-in and sign-out, including the disabled-account
 * check enforced at login time.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SessionController extends Controller
{
    /**
     * Show the sign-in form.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Authenticate the user and redirect to their role's dashboard.
     * Rate-limited via the 'login' limiter (routes/web.php, 'throttle:login')
     * — see AppServiceProvider::boot().
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            AuditLogger::record(
                User::query()->where('email', $credentials['email'])->first(),
                'login.failed'
            );

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // PTO's "Disable Account" action (Pto\UsersController::toggleStatus)
        // is documented as immediate loss of access — enforce that here.
        if ($user->status === 'Inactive') {
            Auth::guard('web')->logout();

            AuditLogger::record($user, 'login.blocked_suspended');

            throw ValidationException::withMessages([
                'email' => __('This account has been disabled. Contact your Provincial Tourism Office administrator.'),
            ]);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        AuditLogger::record($user, 'login.success');

        return redirect()->intended(
            $user->role ? route($user->role->dashboardRouteName()) : route('home')
        );
    }

    /**
     * Log the user out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        AuditLogger::record($user, 'logout');

        return redirect()->route('home');
    }
}
