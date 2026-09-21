<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for an immutable audit trail row (logins/logouts,
 * changes to RBAC-sensitive user fields). See App\Support\AuditLogger for
 * the write helper — rows should not be created directly from controllers.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'action', 'target_type', 'target_id', 'ip_address', 'user_agent', 'metadata'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
