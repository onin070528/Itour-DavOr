<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Event listener — writes a "password changed" entry to the security log.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Listeners;

use App\Events\UserPasswordChanged;
use App\Support\SecurityLogger;

class LogPasswordChanged
{
    public function handle(UserPasswordChanged $objEvent): void
    {
        SecurityLogger::passwordChanged($objEvent->user);
    }
}
