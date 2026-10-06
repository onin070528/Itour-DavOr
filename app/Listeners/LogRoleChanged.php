<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Event listener — writes a "role changed" entry to the security log.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Listeners;

use App\Events\UserRoleChanged;
use App\Support\SecurityLogger;

class LogRoleChanged
{
    public function handle(UserRoleChanged $objEvent): void
    {
        SecurityLogger::roleChanged($objEvent->actor, $objEvent->account, $objEvent->fromRole, $objEvent->toRole);
    }
}
