<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Eloquent model for tblcategories, the fixed establishment category lookup.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fixed category lookup backing the Tourism Directory's Categories panel.
 * "Others" is the only category without cat_is_qr_enabled by default (A8) —
 * callers must read the flag, never hard-code the category name.
 */
#[Fillable(['cat_name', 'cat_sort_order', 'cat_is_active', 'cat_is_qr_enabled'])]
class Category extends Model
{
    public const CREATED_AT = 'cat_created_at';

    public const UPDATED_AT = 'cat_updated_at';

    protected $table = 'tblcategories';

    protected $primaryKey = 'cat_id';

    protected function casts(): array
    {
        return [
            'cat_is_active' => 'boolean',
            'cat_is_qr_enabled' => 'boolean',
        ];
    }

    /**
     * Describes whether this category is named "Others" — the only
     * category that requires a category_note on the establishment form.
     */
    public function isOthers(): bool
    {
        return $this->cat_name === 'Others';
    }

    public function establishments(): HasMany
    {
        return $this->hasMany(Listing::class, 'cat_id', 'cat_id');
    }

    /**
     * Active categories, in display order, for the Tourism Directory's
     * Categories panel and the establishment registration form's dropdown.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('cat_is_active', true)->orderBy('cat_sort_order');
    }
}
