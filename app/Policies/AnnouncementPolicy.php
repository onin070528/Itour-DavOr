<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: RBAC policy for Announcement — PTO-managed promotions, advisories,
 * and events on the public landing page. Already PTO-only via route
 * middleware; this policy is the second-layer safety net for whenever a
 * route gets added or regrouped. Public visibility of a published
 * announcement (Announcement::scopeCurrentlyVisible()) is unrelated and
 * stays ungated.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->usr_role === UserRole::PtoAdministrator;
    }

    public function create(User $user): bool
    {
        return $user->usr_role === UserRole::PtoAdministrator;
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->usr_role === UserRole::PtoAdministrator;
    }

    public function togglePublish(User $user, Announcement $announcement): bool
    {
        return $user->usr_role === UserRole::PtoAdministrator;
    }
}
