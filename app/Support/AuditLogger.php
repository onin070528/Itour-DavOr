<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Single write path for App\Models\AuditLog rows — logins/logouts
 * and changes to RBAC-sensitive user fields. Controllers call
 * AuditLogger::record() instead of creating AuditLog rows directly, so the
 * IP/user-agent capture and "never log passwords" rule live in one place.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata  Never include a password or
     *                                          token value here.
     */
    public static function record(?User $actor, string $action, ?Model $target = null, array $metadata = []): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'metadata' => $metadata ?: null,
        ]);
    }

    /**
     * Logs only the RBAC-sensitive fields (role, municipality_id,
     * establishment_id, status) that actually changed between $before and
     * the target's current values — call after ->update() so $target
     * reflects the new state. No-ops (returns null) if none changed.
     */
    public static function recordUserScopeChange(User $actor, User $target, array $before): ?AuditLog
    {
        $watched = ['role', 'municipality_id', 'establishment_id', 'status'];
        $changes = [];

        foreach ($watched as $field) {
            $previous = $before[$field] ?? null;
            $current = $target->getAttribute($field);
            $previous = $previous instanceof \BackedEnum ? $previous->value : $previous;
            $current = $current instanceof \BackedEnum ? $current->value : $current;

            if ($previous !== $current) {
                $changes[$field] = ['from' => $previous, 'to' => $current];
            }
        }

        if (! $changes) {
            return null;
        }

        return self::record($actor, 'user.scope_changed', $target, $changes);
    }
}
