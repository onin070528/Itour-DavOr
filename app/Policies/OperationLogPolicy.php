<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: RBAC policy for OperationLog's two page-level abilities — row
 * filtering already lives in OperationLog::scopeVisibleTo(), which every
 * query here goes through (see App\Support\AuditLogQuery); this policy gates
 * the entry points themselves so a future route can't reach that query
 * without going through an authorization check at all.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class OperationLogPolicy
{
    /**
     * The Audit Logs / Activity Log page — every role gets some slice of
     * its own operation history (see scopeVisibleTo()); default deny for
     * any other role is handled there too.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->usr_role, [UserRole::PtoAdministrator, UserRole::Lgu, UserRole::Establishment], true);
    }

    /**
     * CSV export — PTO and LGU only. Establishment's Activity Log page has
     * no export route at all (see Establishment\ActivityLogController).
     */
    public function export(User $user): bool
    {
        return in_array($user->usr_role, [UserRole::PtoAdministrator, UserRole::Lgu], true);
    }
}
