<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for a tourism destination or establishment listing.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A tourism destination or establishment — the real, DB-backed replacement
 * for App\Support\TourismCatalog::listings(). `lst_category` is
 * 'destinations' for destinations, or one of the non-destination category
 * slugs (TourismCatalog::categories()) for establishments. There is no
 * separate Establishment/Destination model or table — RBAC (see
 * ListingPolicy) branches on `lst_category` instead.
 */
#[Table('tbl_listings', key: 'lst_id')]
#[Fillable([
    'lst_slug', 'lst_name', 'lst_owner_name', 'lst_category', 'lst_municipality', 'mun_id', 'lst_barangay', 'lst_lat', 'lst_lng', 'lst_description',
    'lst_rating', 'lst_tags', 'lst_image', 'lst_contact_office', 'lst_contact_phone', 'lst_hours',
    'lst_email', 'lst_website', 'lst_status',
])]
class Listing extends Model
{
    use HasFactory;

    public const CREATED_AT = 'lst_created_at';

    public const UPDATED_AT = 'lst_updated_at';

    protected function casts(): array
    {
        return [
            'lst_tags' => 'array',
            'lst_rating' => 'decimal:1',
            'lst_lat' => 'float',
            'lst_lng' => 'float',
        ];
    }

    /**
     * Route-model-bind by slug (e.g. "dahican-beach") — every existing
     * link/route built from a listing's mock 'id' already uses this value.
     */
    public function getRouteKeyName(): string
    {
        return 'lst_slug';
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class, 'lst_id', 'lst_id')->orderBy('lsi_sort_order');
    }

    public function arrivals(): HasMany
    {
        return $this->hasMany(Arrival::class, 'lst_id', 'lst_id');
    }

    public function municipalityRecord(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'mun_id', 'mun_id');
    }

    /**
     * The single User account linked to this listing via tbl_users.lst_id
     * (only ever set when this listing is an establishment, not a
     * destination — see that column's unique constraint).
     */
    public function establishmentUser(): HasOne
    {
        return $this->hasOne(User::class, 'lst_id', 'lst_id');
    }

    /**
     * PTO: unrestricted. LGU: only listings (establishments or
     * destinations) in its own municipality. Establishment: only its own
     * linked listing, plus read-only visibility of destinations in its own
     * municipality (per the permission matrix — establishments never see
     * other establishments).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->usr_role) {
            UserRole::PtoAdministrator => $query,
            UserRole::Lgu => $query->where('mun_id', $user->mun_id),
            UserRole::Establishment => $query->where(function (Builder $q) use ($user) {
                $q->where('lst_id', $user->lst_id)
                    ->orWhere(function (Builder $q2) use ($user) {
                        $q2->where('lst_category', 'destinations')->where('mun_id', $user->mun_id);
                    });
            }),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
