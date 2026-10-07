<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Event listener — writes a "password reset link requested" entry to the security log.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Listeners;

use App\Events\PasswordResetLinkRequested;
use App\Support\SecurityLogger;

class LogPasswordResetLinkRequested
{
    public function handle(PasswordResetLinkRequested $objEvent): void
    {
        SecurityLogger::passwordResetRequested($objEvent->user);
    }
}
