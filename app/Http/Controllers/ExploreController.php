<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Renders the public Explore hub listing every active destination
 * and tourism establishment, with client-side grid/table/map filtering.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers;

use App\Support\TourismCatalog;
use Illuminate\View\View;

class ExploreController extends Controller
{
    /**
     * Display the consolidated Explore hub — every destination and tourism
     * establishment in one place, with Grid, Table, and Map views.
     *
     * Filtering (search, municipality, category) is handled client-side
     * against the full listings payload embedded in the page, so switching
     * views or filters never triggers a full page reload. The `q`,
     * `municipality`, and `category` query parameters (from the hero
     * search, quick pills, and municipality chips) are read by the client
     * script to set the initial filter state.
     */
    public function index(): View
    {
        return view('explore', [
            // Only Active listings are publicly visible — Archived
            // destinations (Lgu/Pto\DirectoryController@archiveDestination)
            // and un-verified establishments (Pending Review/Inactive,
            // Lgu\DirectoryController@verifyEstablishment) are meant to
            // disappear from the public site, not the LGU/PTO management
            // tables — so the filter lives here, not in
            // TourismCatalog::listings() itself.
            'listings' => collect(TourismCatalog::listings())->where('status', 'Active')->values()->all(),
            'categories' => TourismCatalog::categories(),
            'municipalities' => TourismCatalog::municipalities(),
        ]);
    }
}
