<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for an authenticated account (PTO/LGU/Establishment roles).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * `municipality_id`/`establishment_id` stay in #[Fillable] for legitimate
 * admin-initiated create/update calls (Pto\UsersController,
 * Lgu\UsersController), but no controller ever mass-assigns them from raw
 * request input — every write path is an explicit, role-checked field
 * assignment. Self-service settings (Concerns\UpdatesAccountSettings)
 * validate by an allow-list that excludes role/status/municipality_id/
 * establishment_id entirely, so a user can never change their own scope.
 */
#[Fillable(['name', 'email', 'password', 'role', 'organization_name', 'organization_subtitle', 'status', 'municipality_id', 'establishment_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * The single establishment (Listing) this account is linked to — only
     * ever set for role === Establishment. Establishments and destinations
     * share the `listings` table (see Listing's `category` column).
     */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'establishment_id');
    }

    /**
     * PTO sees every user; LGU sees only Establishment-role users in its
     * own municipality; Establishment sees only itself.
     */
    public function scopeVisibleTo(Builder $query, self $user): Builder
    {
        return match ($user->role) {
            UserRole::PtoAdministrator => $query,
            UserRole::Lgu => $query->where('role', UserRole::Establishment)
                ->where('municipality_id', $user->municipality_id),
            UserRole::Establishment => $query->where('id', $user->id),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
