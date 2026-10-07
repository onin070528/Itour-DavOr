<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Event listener — writes a "successful login" entry to the security log.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Listeners;

use App\Models\User;
use App\Support\SecurityLogger;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function handle(Login $objEvent): void
    {
        /** @var User $objUser */
        $objUser = $objEvent->user;

        SecurityLogger::loginSuccess($objUser);
    }
}
