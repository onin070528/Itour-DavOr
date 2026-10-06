<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: The page a password reset email links to — lets the account
 * holder choose a new password using the emailed token.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SessionSecurity;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Show the "Set a new password" form for the emailed token.
     */
    public function create(Request $objRequest, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $objRequest->query('email', ''),
        ]);
    }

    public function store(Request $objRequest): RedirectResponse
    {
        $objRequest->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::default()],
        ]);

        try {
            $strStatus = Password::reset(
                [
                    'usr_email' => $objRequest->input('email'),
                    ...$objRequest->only('password', 'password_confirmation', 'token'),
                ],
                function (User $objUser, string $strPassword): void {
                    $objUser->forceFill([
                        'usr_password' => $strPassword,
                        'usr_remember_token' => Str::random(60),
                    ])->save();

                    // Fires Illuminate\Auth\Events\PasswordReset, which
                    // App\Listeners\LogPasswordResetCompleted turns into a
                    // security_log row.
                    event(new PasswordReset($objUser));

                    SessionSecurity::invalidateOtherSessionsFor($objUser);
                }
            );
        } catch (\Throwable $objException) {
            Log::error('Failed to reset a password.', ['exception' => $objException]);

            throw ValidationException::withMessages([
                'email' => __('Something went wrong while saving your new password. Please try again.'),
            ]);
        }

        if ($strStatus !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __('This password reset link is invalid or has expired. Request a new one.'),
            ]);
        }

        return redirect()->route('login')->with('status', __('Your password has been set. You can now sign in.'));
    }
}
