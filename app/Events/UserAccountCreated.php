<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Fired when a PTO or LGU admin creates a new account, so the
 * security_logs write lives in a listener rather than inline in the
 * controllers that create accounts.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserAccountCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $actor,
        public readonly User $account,
    ) {}
}
