<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: "Forgot password?" — emails a password reset link. This is also
 * how newly created accounts (which are given a random password nobody
 * knows) set their password before signing in for the first time.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the "Forgot password" form.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Email a reset link. The same confirmation is shown whether or not the
     * email belongs to an account, so this form can't be used to discover
     * which emails are registered.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (TransportExceptionInterface $e) {
            // Mail server unreachable or rejected the login (e.g. wrong
            // MAIL_* settings in .env) — show a friendly error, not a 500.
            Log::error('Failed to send password reset email.', ['exception' => $e]);

            throw ValidationException::withMessages([
                'email' => __('We couldn\'t send the reset email right now. Please try again later or contact your administrator.'),
            ]);
        }

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => __('Please wait a minute before requesting another reset link.'),
            ]);
        }

        if ($status === Password::RESET_LINK_SENT) {
            AuditLogger::record(User::query()->where('email', $request->input('email'))->first(), 'password.reset_link_sent');
        }

        return back()->withInput()->with('status', __('If an account exists for that email, a password reset link has been sent. Check your inbox.'));
    }
}
