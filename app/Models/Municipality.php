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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'province'])]
class Municipality extends Model
{
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * Establishment-category listings in this municipality. Establishments
     * live in the same `listings` table as destinations — see Listing's
     * `category` column — there is no separate establishments table.
     */
    public function establishments(): HasMany
    {
        return $this->listings()->where('category', '!=', 'destinations');
    }

    public function destinations(): HasMany
    {
        return $this->listings()->where('category', 'destinations');
    }

    /**
     * PTO sees every municipality; LGU and Establishment users see only
     * their own assigned municipality.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::PtoAdministrator) {
            return $query;
        }

        return $query->where('id', $user->municipality_id);
    }
}
