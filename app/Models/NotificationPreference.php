<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for a user's per-key notification toggle preference.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('tbl_notification_preferences', key: 'npf_id')]
#[Fillable(['usr_id', 'npf_key', 'npf_enabled'])]
class NotificationPreference extends Model
{
    public const CREATED_AT = 'npf_created_at';

    public const UPDATED_AT = 'npf_updated_at';

    protected function casts(): array
    {
        return [
            'npf_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usr_id', 'usr_id');
    }
}
