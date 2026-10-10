<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: RBAC policy for Hotline — the province-wide emergency hotline
 * directory, PTO-managed only (no municipality scoping by design, see
 * Hotline's own doc comment). Already PTO-only via route middleware; this
 * policy is the second-layer safety net for whenever a route gets added or
 * regrouped.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Hotline;
use App\Models\User;

class HotlinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->usr_role === UserRole::PtoAdministrator;
    }

    public function create(User $user): bool
    {
        return $user->usr_role === UserRole::PtoAdministrator;
    }

    public function update(User $user, Hotline $hotline): bool
    {
        return $user->usr_role === UserRole::PtoAdministrator;
    }

    public function deactivate(User $user, Hotline $hotline): bool
    {
        return $user->usr_role === UserRole::PtoAdministrator;
    }

    public function reorder(User $user, Hotline $hotline): bool
    {
        return $user->usr_role === UserRole::PtoAdministrator;
    }
}
