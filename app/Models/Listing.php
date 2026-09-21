<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for a tourism destination or establishment listing.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tourism destination or establishment — the real, DB-backed replacement
 * for App\Support\TourismCatalog::listings(). `category` is 'destinations'
 * for destinations, or one of the non-destination category slugs
 * (TourismCatalog::categories()) for establishments.
 */
#[Fillable([
    'slug', 'name', 'category', 'municipality', 'barangay', 'lat', 'lng', 'description',
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
}
