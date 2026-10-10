<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Applies the confirmed classification for Dahican Surf Guides & Tours (Recreation & Activities / Diving / Water Activity).
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'dahican-surf-guides';

    /**
     * Confirmed after Phase 1: surf lessons and board rentals are a
     * water-based activity, not a tour guide service — so the listing
     * keeps its map pin and QR eligibility. Only touches the row while it
     * still has the original Travel & Tours classification with no type,
     * so a later manual correction is never overwritten. The legacy
     * `category` slug is kept in sync with cat_id (still read by the
     * public pages, see Category::legacySlug()).
     */
    public function up(): void
    {
        $intRecreationCategoryId = DB::table('tbl_categories')->where('cat_name', 'Recreation & Activities')->value('cat_id');

        if ($intRecreationCategoryId === null) {
            return;
        }

        DB::table('tbl_listings')
            ->where('lst_slug', self::SLUG)
            ->where('lst_category', 'tour-guides')
            ->whereNull('lst_type')
            ->update([
                'lst_category' => 'recreation-activities',
                'cat_id' => $intRecreationCategoryId,
                'lst_type' => 'Diving / Water Activity',
            ]);
    }

    /**
     * Restores the original Travel & Tours classification, only while the
     * row still carries exactly what up() wrote.
     */
    public function down(): void
    {
        $intTravelCategoryId = DB::table('tbl_categories')->where('cat_name', 'Travel & Tours')->value('cat_id');

        DB::table('tbl_listings')
            ->where('lst_slug', self::SLUG)
            ->where('lst_category', 'recreation-activities')
            ->where('lst_type', 'Diving / Water Activity')
            ->update([
                'lst_category' => 'tour-guides',
                'cat_id' => $intTravelCategoryId,
                'lst_type' => null,
            ]);
    }
};
