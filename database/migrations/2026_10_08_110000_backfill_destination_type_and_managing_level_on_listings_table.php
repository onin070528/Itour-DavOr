<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Migration — approved Objective 3 backfill of the destination
 * type (lst_type) for the existing destination records and the managing
 * level of Subangan Museum.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The mapping approved before Phase 2, keyed by slug (stable across
     * environments, unlike ids). Only destination-only rows
     * (lst_category 'destinations') are touched, and only where the value
     * is still empty — a value someone already set is never overwritten.
     * Types come from config/tourism_directory.php 'destination_types'.
     *
     * @var array<string, string>
     */
    private const DESTINATION_TYPES = [
        'dahican-beach' => 'Beach',
        'aliwagwag-falls' => 'Waterfall',
        'hamiguitan' => 'Mountain',
        'pujada-bay' => 'Other',
        'subangan-museum' => 'Museum',
        'cape-san-agustin' => 'Other',
        'pusan-point' => 'Other',
        'sleeping-dinosaur-island' => 'Other',
    ];

    /**
     * Destinations whose existing data shows PTO management (contact office
     * "Provincial Tourism Office"). Every other destination stays NULL,
     * which reads as LGU-managed.
     *
     * @var array<int, string>
     */
    private const PTO_MANAGED_SLUGS = ['subangan-museum'];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::DESTINATION_TYPES as $strSlug => $strType) {
                DB::table('tbl_listings')
                    ->where('lst_slug', $strSlug)
                    ->where('lst_category', 'destinations')
                    ->whereNull('lst_type')
                    ->update(['lst_type' => $strType]);
            } // end foreach destination type

            DB::table('tbl_listings')
                ->whereIn('lst_slug', self::PTO_MANAGED_SLUGS)
                ->where('lst_category', 'destinations')
                ->whereNull('lst_managing_level')
                ->update(['lst_managing_level' => 'pto']);
        });
    }

    /**
     * Reverts only values that still equal what up() wrote, so later
     * manual corrections are kept.
     */
    public function down(): void
    {
        DB::transaction(function () {
            foreach (self::DESTINATION_TYPES as $strSlug => $strType) {
                DB::table('tbl_listings')
                    ->where('lst_slug', $strSlug)
                    ->where('lst_category', 'destinations')
                    ->where('lst_type', $strType)
                    ->update(['lst_type' => null]);
            } // end foreach destination type

            DB::table('tbl_listings')
                ->whereIn('lst_slug', self::PTO_MANAGED_SLUGS)
                ->where('lst_category', 'destinations')
                ->where('lst_managing_level', 'pto')
                ->update(['lst_managing_level' => null]);
        });
    }
};
