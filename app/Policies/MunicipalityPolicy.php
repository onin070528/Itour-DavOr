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
    public function viewAny(User $objUser): bool
    {
        return true;
    }

    public function view(User $objUser, Municipality $objMunicipality): bool
    {
        return match ($objUser->usr_role) {
            UserRole::PtoAdministrator => true,
            UserRole::Lgu, UserRole::Establishment => $objMunicipality->mun_id === $objUser->mun_id,
            default => false,
        };
    }

    public function create(User $objUser): bool
    {
        return $objUser->usr_role === UserRole::PtoAdministrator;
    }

    public function update(User $objUser, Municipality $objMunicipality): bool
    {
        return $objUser->usr_role === UserRole::PtoAdministrator;
    }

    public function delete(User $objUser, Municipality $objMunicipality): bool
    {
        return $objUser->usr_role === UserRole::PtoAdministrator;
    }
}
