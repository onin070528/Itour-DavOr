<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Eloquent model for tbl_categories, the fixed establishment category lookup.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fixed category lookup backing the Tourism Directory's Categories panel.
 * "Others" is the only category without cat_is_qr_enabled by default (A8) —
 * callers must read the flag, never hard-code the category name.
 */
#[Table('tbl_categories', key: 'cat_id')]
#[Fillable(['cat_name', 'cat_sort_order', 'cat_is_active', 'cat_is_qr_enabled'])]
class Category extends Model
{
    public const CREATED_AT = 'cat_created_at';

    public const UPDATED_AT = 'cat_updated_at';

    /** cat_name of the category used by destination-only records. */
    public const DESTINATION_CATEGORY_NAME = 'Tourist Destinations';

    protected function casts(): array
    {
        return [
            'cat_is_active' => 'boolean',
            'cat_is_qr_enabled' => 'boolean',
        ];
    }

    /**
     * Describes whether this category is named "Others" — the only
     * category that requires a lst_category_note on the establishment form.
     */
    public function isOthers(): bool
    {
        return $this->cat_name === 'Others';
    }

    /**
     * Describes whether this is the "Tourist Destinations" category — the
     * one row used by destination-only records, never offered as an
     * establishment category and never given establishment types.
     */
    public function isDestinationCategory(): bool
    {
        return $this->cat_name === self::DESTINATION_CATEGORY_NAME;
    }

    /**
     * The legacy free-text `listings.category` slug for this category —
     * kept in sync with cat_id on every write because TourismCatalog, the
     * public Explore page, and ListingPolicy still read the slug. Moved
     * here from Pto\DirectoryController so the PTO and LGU directory forms
     * share one mapping.
     */
    public function legacySlug(): string
    {
        return match ($this->cat_name) {
            self::DESTINATION_CATEGORY_NAME => 'destinations',
            'Accommodation' => 'accommodation',
            'Food & Dining' => 'restaurants',
            'Tourist Transport' => 'transportation',
            'Travel & Tours' => 'tour-guides',
            'Farm & Agri-Tourism' => 'farm-agri-tourism',
            'Wellness & Spa' => 'wellness-spa',
            'Recreation & Activities' => 'recreation-activities',
            'MICE & Events' => 'mice-events',
            default => 'others',
        };
    }

    /**
     * The establishment types allowed under this category, in display
     * order, from the single source config/establishment_categories.php.
     * Empty for Tourist Destinations (and for any category the config
     * does not list).
     *
     * @return array<int, string>
     */
    public function establishmentTypes(): array
    {
        return config('establishment_categories.types.'.$this->cat_name, []);
    }

    /**
     * Every category's types keyed by cat_id — what the dependent
     * Category -> Type dropdown needs on the client side.
     *
     * @param  iterable<int, Category>  $arrCategories
     * @return array<int, array<int, string>>
     */
    public static function establishmentTypesById(iterable $arrCategories): array
    {
        $arrTypesById = [];

        foreach ($arrCategories as $objCategory) {
            $arrTypesById[$objCategory->cat_id] = $objCategory->establishmentTypes();
        } // end foreach category

        return $arrTypesById;
    }

    public function establishments(): HasMany
    {
        return $this->hasMany(Listing::class, 'cat_id', 'cat_id');
    }

    /**
     * Active categories, in display order, for the Tourism Directory's
     * Categories panel and the establishment registration form's dropdown.
     */
    public function scopeActive(Builder $objQuery): Builder
    {
        return $objQuery->where('cat_is_active', true)->orderBy('cat_sort_order');
    }

    /**
     * Establishment categories only — every category except Tourist
     * Destinations (R13: it is never an establishment category).
     */
    public function scopeForEstablishments(Builder $query): Builder
    {
        return $query->where('cat_name', '!=', self::DESTINATION_CATEGORY_NAME);
    }
}
