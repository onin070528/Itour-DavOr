<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Single configuration source for the public Tourism Directory and
 * its nearby search — destination types, the Davao Oriental coordinate
 * guard, radius options, page sizes, and GPS rounding.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Destination Types
    |--------------------------------------------------------------------------
    |
    | The only allowed values of tbl_listings.lst_type for destination-only
    | records (lst_category 'destinations'). Establishment types stay in
    | config/establishment_categories.php — the two lists never mix.
    |
    */

    'destination_types' => [
        'Beach',
        'Waterfall',
        'Mountain',
        'Cave',
        'Museum',
        'Heritage Site',
        'Nature Park',
        'Other',
    ],

    /*
    |--------------------------------------------------------------------------
    | Davao Oriental Coordinate Guard
    |--------------------------------------------------------------------------
    |
    | Coarse bounding box (decimal degrees) every submitted listing
    | coordinate must fall inside — checked server-side by
    | App\Rules\WithinDavaoOrientalBounds. Provisional values; adjust here
    | only, never in code.
    |
    */

    'coordinate_bounds' => [
        'min_latitude' => 6.20,
        'max_latitude' => 8.10,
        'min_longitude' => 125.80,
        'max_longitude' => 126.70,
    ],

    /*
    |--------------------------------------------------------------------------
    | Results Per Page
    |--------------------------------------------------------------------------
    |
    | One page size for every public result list: the Explore directory and
    | the nearby lists (App\Services\NearbySearchService::paginate()).
    |
    */

    'results_per_page' => 20,

    /*
    |--------------------------------------------------------------------------
    | Nearby Search
    |--------------------------------------------------------------------------
    |
    | Read by App\Services\NearbySearchService. Distances are straight-line
    | (Haversine) kilometres. `coordinate_precision` is the number of decimal
    | places a tourist's Find Near Me coordinates are rounded to (about 11 m
    | at 4 places) before they are used — they are never stored.
    | `find_near_me_per_minute` is the Find Near Me rate limit per visitor IP
    | (App\Providers\AppServiceProvider, limiter "find-near-me").
    |
    */

    'nearby' => [
        'radius_options_km' => [1, 5, 10, 25, 50],
        'default_radius_km' => 10,
        'max_radius_km' => 50,
        'results_per_group' => 5,
        'coordinate_precision' => 4,
        'find_near_me_per_minute' => 60,
    ],

];
