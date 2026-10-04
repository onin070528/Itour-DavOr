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
use App\Rules\Turnstile;
use App\Support\SecurityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
     *
     * Deliberately doesn't use Auth::attempt(): that fires Illuminate\Auth\
     * Events\Login on any correct password, before the account-status check
     * below runs — which would log a false login_success for a suspended
     * account we're about to reject. Checking credentials manually and
     * calling Auth::login() only once both checks pass keeps Login/Failed
     * firing exactly when they mean "this session is now authenticated" /
     * "these credentials were rejected" (App\Listeners\LogSuccessfulLogin,
     * LogFailedLogin — see App\Support\SecurityLogger).
     */
    public function store(Request $request): RedirectResponse
    {
        // Verified before credentials are even looked up — the account
        // must never be authenticated unless Turnstile succeeds. Skipped
        // only in the automated test suite (APP_ENV=testing), which has no
        // real Turnstile widget to produce a token and isn't exercising
        // network-dependent third-party verification — never on localhost
        // (APP_ENV=local) or in production.
        if (! app()->environment('testing')) {
            $request->validate([
                'cf-turnstile-response' => ['required', 'string', new Turnstile($request->ip())],
            ], [
                'cf-turnstile-response.required' => 'Please complete the verification check.',
            ]);
        }

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            event(new Failed('web', $user, $credentials));

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        // PTO's "Disable Account" action (Pto\UsersController::toggleStatus)
        // is documented as immediate loss of access — enforce that here.
        // Deliberately the *same* message as a bad password above: telling
        // the requester their account is disabled would let anyone probing
        // an email address learn it belongs to a real, suspended account.
        // The real reason is still recorded in security_logs, just not shown.
        if ($user->status === 'Inactive') {
            SecurityLogger::loginFailed($user, null, 'account_suspended');

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(
            $user->role ? route($user->role->dashboardRouteName()) : route('home')
        );
    }

    /**
     * Log the user out. Auth::guard('web')->logout() fires Illuminate\Auth\
     * Events\Logout, which App\Listeners\LogLogout turns into a security_log row.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
