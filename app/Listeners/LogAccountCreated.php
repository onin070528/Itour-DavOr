<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Event listener — writes a "account created" entry to the security log.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Listeners;

use App\Events\UserAccountCreated;
use App\Support\SecurityLogger;

class LogAccountCreated
{
    public function handle(UserAccountCreated $objEvent): void
    {
        SecurityLogger::accountCreated($objEvent->actor, $objEvent->account);
    }
}
