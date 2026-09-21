<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: RBAC policy for the User model — who may view/update/deactivate
 * which accounts. Account *creation* is not a boolean ability here; the
 * PTO→LGU→Establishment creation chain and municipality checks are
 * explicit, narrow validation in Pto\UsersController / Lgu\UsersController
 * rather than a single "can create a user" gate.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::PtoAdministrator, UserRole::Lgu], true);
    }

    public function view(User $user, User $target): bool
    {
        return match ($user->role) {
            UserRole::PtoAdministrator => true,
            UserRole::Lgu => $target->role === UserRole::Establishment && $target->municipality_id === $user->municipality_id,
            UserRole::Establishment => $target->id === $user->id,
            default => false,
        };
    }

    public function update(User $user, User $target): bool
    {
        return $this->view($user, $target);
    }

    /**
     * Toggling Active/Inactive status. Nobody may deactivate themselves —
     * that's the "nobody can change their own status" rule, enforced here
     * rather than only in the controller so it holds regardless of caller.
     */
    public function deactivate(User $user, User $target): bool
    {
        if ($target->id === $user->id) {
            return false;
        }

        return match ($user->role) {
            UserRole::PtoAdministrator => true,
            UserRole::Lgu => $target->role === UserRole::Establishment && $target->municipality_id === $user->municipality_id,
            default => false,
        };
    }
}
