<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Invalidates other active sessions for an account after a password
 * change/reset, by deleting their rows from the database session store.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class SessionSecurity
{
    /**
     * Deletes every session row for $user other than $exceptId, so a stolen
     * or forgotten session elsewhere stops working the moment the password
     * changes. Requires the database session driver (config('session.driver'))
     * — a no-op on file/cookie/array drivers, which have no per-user table
     * to query.
     *
     * $exceptId is omitted during a password reset, where the request isn't
     * authenticated as $user yet, so there is no "current" session of theirs
     * to preserve — every existing session for the account is invalidated.
     */
    public static function invalidateOtherSessionsFor(User $user, ?string $exceptId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->delete();
    }
}
