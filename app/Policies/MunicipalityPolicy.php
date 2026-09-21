<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: RBAC policy for the Municipality model.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Municipality;
use App\Models\User;

class MunicipalityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Municipality $municipality): bool
    {
        return match ($user->role) {
            UserRole::PtoAdministrator => true,
            UserRole::Lgu, UserRole::Establishment => $municipality->id === $user->municipality_id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::PtoAdministrator;
    }

    public function update(User $user, Municipality $municipality): bool
    {
        return $user->role === UserRole::PtoAdministrator;
    }

    public function delete(User $user, Municipality $municipality): bool
    {
        return $user->role === UserRole::PtoAdministrator;
    }
}
