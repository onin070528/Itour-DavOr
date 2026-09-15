<?php

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
    'slug', 'name', 'category', 'municipality', 'barangay', 'description',
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
