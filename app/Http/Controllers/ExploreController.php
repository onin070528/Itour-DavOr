<?php

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
            'listings' => TourismCatalog::listings(),
            'categories' => TourismCatalog::categories(),
            'municipalities' => TourismCatalog::municipalities(),
        ]);
    }
}
