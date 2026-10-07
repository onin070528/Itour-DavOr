<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: RBAC policy for Category — the fixed establishment category
 * lookup. Already PTO-only via route middleware; this policy is the
 * second-layer safety net for whenever a route gets added or regrouped.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function update(User $user, Category $category): bool
    {
        return $user->role === UserRole::PtoAdministrator;
    }
}
