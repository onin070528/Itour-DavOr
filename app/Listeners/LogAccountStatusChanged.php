<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Event listener — writes a "account status changed" entry to the security log.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Listeners;

use App\Events\UserAccountStatusChanged;
use App\Support\SecurityLogger;

class LogAccountStatusChanged
{
    public function handle(UserAccountStatusChanged $objEvent): void
    {
        SecurityLogger::accountStatusChanged($objEvent->actor, $objEvent->account, $objEvent->newStatus);
    }
}
