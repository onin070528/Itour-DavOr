<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: RBAC policy for MunicipalReport — the consolidated provincial
 * report PTO reviews. Every route that reaches this model today is already
 * PTO-only via route middleware; this policy is the second-layer safety net
 * so a future route (or a regrouping mistake) can't silently expose another
 * municipality's report.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MunicipalReport;
use App\Models\User;

class MunicipalReportPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->usr_role, [UserRole::PtoAdministrator, UserRole::Lgu], true);
    }

    public function view(User $user, MunicipalReport $municipalReport): bool
    {
        return match ($user->usr_role) {
            UserRole::PtoAdministrator => true,
            UserRole::Lgu => $municipalReport->mun_id !== null && $municipalReport->mun_id === $user->mun_id,
            default => false,
        };
    }

    public function approve(User $user, MunicipalReport $municipalReport): bool
    {
        return $user->usr_role === UserRole::PtoAdministrator;
    }

    public function return(User $user, MunicipalReport $municipalReport): bool
    {
        return $user->usr_role === UserRole::PtoAdministrator;
    }

    /**
     * Official Report preview/PDF/Excel. LGU may export its own
     * municipality's report at any status — the template already renders a
     * DRAFT watermark (MunicipalReport::isFrozen()) whenever it isn't
     * APPROVED, so an unapproved export can never be mistaken for the
     * PTO-signed-off version.
     */
    public function export(User $user, MunicipalReport $municipalReport): bool
    {
        return $this->view($user, $municipalReport);
    }
}
