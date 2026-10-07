<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for a Davao Oriental municipality — the RBAC
 * scoping anchor for LGU and Establishment users.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('tbl_municipalities', key: 'mun_id')]
#[Fillable(['mun_name', 'mun_code', 'mun_province'])]
class Municipality extends Model
{
    public const CREATED_AT = 'mun_created_at';

    public const UPDATED_AT = 'mun_updated_at';

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'mun_id', 'mun_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'mun_id', 'mun_id');
    }

    /**
     * Establishment-category listings in this municipality. Establishments
     * live in the same `tbl_listings` table as destinations — see Listing's
     * `lst_category` column — there is no separate establishments table.
     */
    public function establishments(): HasMany
    {
        return $this->listings()->where('lst_category', '!=', 'destinations');
    }

    public function destinations(): HasMany
    {
        return $this->listings()->where('lst_category', 'destinations');
    }

    /**
     * PTO sees every municipality; LGU and Establishment users see only
     * their own assigned municipality.
     */
    public function scopeVisibleTo(Builder $objQuery, User $objUser): Builder
    {
        if ($objUser->usr_role === UserRole::PtoAdministrator) {
            return $objQuery;
        }

        return $objQuery->where('mun_id', $objUser->mun_id);
    }
}
