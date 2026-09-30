<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Fired when a user changes their own password from Settings
 * (Concerns\UpdatesAccountSettings::updatePassword). Laravel's own
 * PasswordReset event covers the forgot-password-link flow instead.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPasswordChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {}
}
