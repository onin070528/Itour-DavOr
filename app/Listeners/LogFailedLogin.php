<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Event listener — writes a "failed login" entry to the security log.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Listeners;

use App\Models\User;
use App\Support\SecurityLogger;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $objEvent): void
    {
        /** @var User|null $objUser */
        $objUser = $objEvent->user;

        // Only the email is ever read from $objEvent->credentials — never the
        // password, which lives in the same array.
        SecurityLogger::loginFailed($objUser, $objEvent->credentials['email'] ?? null, 'invalid_credentials');
    }
}
