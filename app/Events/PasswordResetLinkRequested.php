<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Fired when a password reset link is actually emailed to an
 * existing account (Auth\PasswordResetLinkController::store). Not fired for
 * unknown emails — the controller shows the same generic confirmation
 * either way, but there is no account to log a request against.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PasswordResetLinkRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {}
}
