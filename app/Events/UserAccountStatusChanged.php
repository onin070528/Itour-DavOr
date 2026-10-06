<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Fired when a PTO or LGU admin suspends or reactivates an account
 * (Pto\UsersController::toggleStatus, Lgu\UsersController::toggleStatus).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserAccountStatusChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string  $newStatus  The status the account was just set to ('Active'/'Inactive').
     */
    public function __construct(
        public readonly User $actor,
        public readonly User $account,
        public readonly string $newStatus,
    ) {}
}
