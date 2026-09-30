<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Fired when a PTO admin changes an existing account's role
 * (Pto\UsersController::update).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Events;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRoleChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $actor,
        public readonly User $account,
        public readonly UserRole $fromRole,
        public readonly UserRole $toRole,
    ) {}
}
