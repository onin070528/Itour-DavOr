<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for tbl_hotlines, the province-wide hotline directory.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Province-wide emergency hotlines (public Hotlines page). PTO-managed
 * only — there is no municipality scoping column by design, LGUs have no
 * access.
 */
#[Table('tbl_hotlines', key: 'hot_id')]
#[Fillable(['hot_agency_name', 'hot_agency_type', 'hot_contact_number', 'hot_scope', 'hot_is_24_7', 'hot_is_active', 'hot_sort_order', 'hot_created_by', 'hot_updated_by'])]
class Hotline extends Model
{
    public const CREATED_AT = 'hot_created_at';

    public const UPDATED_AT = 'hot_updated_at';

    protected function casts(): array
    {
        return [
            'hot_is_24_7' => 'boolean',
            'hot_is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hot_created_by', 'usr_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hot_updated_by', 'usr_id');
    }

    /**
     * Active hotlines, in display order, for the public Hotlines page.
     */
    public function scopeActive(Builder $objQuery): Builder
    {
        return $objQuery->where('hot_is_active', true)->orderBy('hot_sort_order');
    }
}
