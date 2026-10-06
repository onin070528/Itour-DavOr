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
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('tbl_audit_logs', key: 'aud_id')]
#[Fillable(['usr_id', 'aud_action', 'aud_target_type', 'aud_target_id', 'aud_ip_address', 'aud_user_agent', 'aud_metadata'])]
class AuditLog extends Model
{
    public const CREATED_AT = 'aud_created_at';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'aud_metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usr_id', 'usr_id');
    }
}
