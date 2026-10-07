<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: First-login password change — an account created with a temporary
 * password replaces it before reaching any other page (see
 * App\Http\Middleware\ForcePasswordChange).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Auth;

use App\Events\UserPasswordChanged;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SessionSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class FirstLoginPasswordController extends Controller
{
    /**
     * Show the change-password form — only while the account still has to
     * change its password; afterwards the page sends it to its dashboard.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $objUser = $request->user();

        if (! $objUser->mustChangePassword()) {
            return redirect()->to($this->_dashboardUrlFor($objUser));
        }

        return view('auth.change-password', ['email' => $objUser->usr_email]);
    }

    /**
     * Validates the new password with the app-wide Password::default()
     * policy (AppServiceProvider), rejects reusing the temporary password,
     * then saves it (hashed by the User model's cast), clears the flag,
     * records when it changed, and logs the change to security_logs via
     * UserPasswordChanged — never the password itself.
     */
    public function store(Request $request): RedirectResponse
    {
        $objUser = $request->user();

        if (! $objUser->mustChangePassword()) {
            return redirect()->to($this->_dashboardUrlFor($objUser));
        }

        $arrData = $request->validate([
            'password' => ['required', 'confirmed', Password::default()],
        ]);

        if (Hash::check($arrData['password'], $objUser->usr_password)) {
            throw ValidationException::withMessages([
                'password' => 'Choose a new password. It cannot be the same as your temporary password.',
            ]);
        }

        try {
            $objUser->forceFill([
                'usr_password' => $arrData['password'],
                'usr_must_change_password' => false,
                'usr_password_changed_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            Log::error('Failed to save the first-login password change.', ['exception' => $e, 'user_id' => $objUser->usr_id]);

            return back()->withErrors(['password' => 'Something went wrong while saving your password. Please try again.']);
        }

        $request->session()->regenerate();
        SessionSecurity::invalidateOtherSessionsFor($objUser, $request->session()->getId());

        event(new UserPasswordChanged($objUser));

        return redirect()->intended($this->_dashboardUrlFor($objUser))
            ->with('toast', 'Password updated. Welcome to iTOUR.');
    }

    private function _dashboardUrlFor(User $objUser): string
    {
        return $objUser->usr_role ? route($objUser->usr_role->dashboardRouteName()) : route('home');
    }
}
