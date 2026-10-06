<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Seeds the 10 fixed categories (tbl_categories) and backfills every existing listing's new
 * `cat_id` from its legacy free-text `lst_category` slug — the slug column itself is left
 * untouched until lat...
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Listing;
use Illuminate\Database\Seeder;

/**
 * Seeds the 10 fixed categories (tbl_categories) and backfills every
 * existing listing's new `cat_id` from its legacy free-text `lst_category`
 * slug — the slug column itself is left untouched until later stages move
 * every reader onto the relation.
 */
class CategorySeeder extends Seeder
{
    /**
     * cat_is_qr_enabled is true for every category except Others (A8,
     * provisional — subject to the PTO's final confirmation).
     *
     * @var array<int, array{name: string, qr: bool}>
     */
    private const CATEGORIES = [
        ['name' => 'Tourist Destinations', 'qr' => true],
        ['name' => 'Accommodation', 'qr' => true],
        ['name' => 'Food & Dining', 'qr' => true],
        ['name' => 'Farm & Agri-Tourism', 'qr' => true],
        ['name' => 'Wellness & Spa', 'qr' => true],
        ['name' => 'Travel & Tours', 'qr' => true],
        ['name' => 'Tourist Transport', 'qr' => true],
        ['name' => 'Recreation & Activities', 'qr' => true],
        ['name' => 'MICE & Events', 'qr' => true],
        ['name' => 'Others', 'qr' => false],
    ];

    /**
     * Maps every legacy `tbl_listings.lst_category` slug onto a new category name
     * (A1: pasalubong/delicacy shops are businesses, filed under Food &
     * Dining — delicacies themselves are not tracked by the PTO).
     *
     * @var array<string, string>
     */
    private const LEGACY_SLUG_MAP = [
        'destinations' => 'Tourist Destinations',
        'accommodation' => 'Accommodation',
        'restaurants' => 'Food & Dining',
        'local-delicacies' => 'Food & Dining',
        'transportation' => 'Tourist Transport',
        'tour-guides' => 'Travel & Tours',
        'farm-agri-tourism' => 'Farm & Agri-Tourism',
        'wellness-spa' => 'Wellness & Spa',
        'recreation-activities' => 'Recreation & Activities',
        'mice-events' => 'MICE & Events',
    ];

    public function run(): void
    {
        $objCategoriesByName = collect(self::CATEGORIES)
            ->values()
            ->map(function (array $arrCategory, int $intIndex): Category {
                return Category::query()->updateOrCreate(
                    ['cat_name' => $arrCategory['name']],
                    [
                        'cat_sort_order' => $intIndex,
                        'cat_is_active' => true,
                        'cat_is_qr_enabled' => $arrCategory['qr'],
                    ]
                );
            })
            ->keyBy('cat_name');

        foreach (self::LEGACY_SLUG_MAP as $strSlug => $strCategoryName) {
            $objCategory = $objCategoriesByName->get($strCategoryName);

            if ($objCategory === null) {
                continue;
            }

            Listing::query()
                ->where('lst_category', $strSlug)
                ->whereNull('cat_id')
                ->update(['cat_id' => $objCategory->cat_id]);
        }
    }
}
