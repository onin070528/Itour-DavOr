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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A tourism destination or establishment — the real, DB-backed replacement
 * for App\Support\TourismCatalog::listings(). `category` is 'destinations'
 * for destinations, or one of the non-destination category slugs
 * (TourismCatalog::categories()) for establishments. There is no separate
 * Establishment/Destination model or table — RBAC (see ListingPolicy)
 * branches on `category` instead.
 */
#[Fillable([
    'slug', 'name', 'owner_name', 'category', 'municipality', 'municipality_id', 'barangay', 'lat', 'lng', 'description',
    'rating', 'tags', 'image', 'contact_office', 'contact_phone', 'hours',
    'email', 'website', 'status',
])]
class Listing extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'rating' => 'decimal:1',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    /**
     * Route-model-bind by slug (e.g. "dahican-beach") — every existing
     * link/route built from a listing's mock 'id' already uses this value.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort_order');
    }

    public function arrivals(): HasMany
    {
        return $this->hasMany(Arrival::class);
    }

    public function municipalityRecord(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'municipality_id');
    }

    /**
     * The single User account linked to this listing via establishment_id
     * (only ever set when this listing is an establishment, not a
     * destination — see users.establishment_id's unique constraint).
     */
    public function establishmentUser(): HasOne
    {
        return $this->hasOne(User::class, 'establishment_id');
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
        return match ($user->role) {
            UserRole::PtoAdministrator => $query,
            UserRole::Lgu => $query->where('municipality_id', $user->municipality_id),
            UserRole::Establishment => $query->where(function (Builder $q) use ($user) {
                $q->where('id', $user->establishment_id)
                    ->orWhere(function (Builder $q2) use ($user) {
                        $q2->where('category', 'destinations')->where('municipality_id', $user->municipality_id);
                    });
            }),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
