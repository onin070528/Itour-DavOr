<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Event listener — writes a "logout" entry to the security log.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Listeners;

use App\Models\User;
use App\Support\SecurityLogger;
use Illuminate\Auth\Events\Logout;

class LogLogout
{
    public function handle(Logout $objEvent): void
    {
        /** @var User|null $objUser */
        $objUser = $objEvent->user;

        if ($objUser) {
            SecurityLogger::logout($objUser);
        }
    }
}
