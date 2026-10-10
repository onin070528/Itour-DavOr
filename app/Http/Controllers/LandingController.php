<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Renders the public landing page — featured destinations,
 * establishments, and municipalities — and the Nearby page.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Category;
use App\Support\DirectionsLink;
use App\Support\TourismCatalog;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LandingController extends Controller
{
    /**
     * The catalog fields the "View Details" modal shows
     * (resources/js/app.js initListingDetailsModal) — the only ones sent to
     * the browser for it. `id` is the public slug.
     *
     * @var array<int, string>
     */
    private const MODAL_FIELDS = [
        'id', 'name', 'category', 'municipality', 'barangay', 'lat', 'lng', 'description', 'rating', 'tags',
        'displayImageUrl', 'categoryIcon', 'contactOffice', 'contactPhone', 'hours', 'href', 'email', 'website',
    ];

    /**
     * Display the public iTOUR landing page.
     *
     * The Signature Experiences showcase (a curated mix of destinations and
     * establishments) comes from TourismCatalog, the single source of truth
     * shared with the /explore hub. Reviews are static frontend mock data —
     * shaped to match what an API resource will eventually return.
     *
     * `moreExperiences` holds every other Active listing, rendered hidden
     * and revealed in place by the showcase's "Explore all" toggle.
     * `listingDetails` feeds the shared "View Details" modal, keyed by
     * listing id so any card on the page can open it.
     */
    public function index(): View
    {
        $arrSignatureExperiences = TourismCatalog::signatureExperiences();
        $objActiveListings = collect(TourismCatalog::listings())->where('isPubliclyVisible', true);
        $arrSignatureIds = array_column($arrSignatureExperiences, 'id');

        return view('landing', [
            'signatureExperiences' => $arrSignatureExperiences,
            'moreExperiences' => $objActiveListings->whereNotIn('id', $arrSignatureIds)->values()->all(),
            'listingDetails' => $this->_listingDetails($objActiveListings),
            'featuredEstablishments' => TourismCatalog::featuredEstablishments(4),
            'municipalities' => TourismCatalog::municipalities(),
            'announcements' => Announcement::query()->currentlyVisible()->limit(5)->get(),
        ]);
    }

    /**
     * Display the public Nearby page: a map and list of the province's public
     * places, narrowed by the server's Find Near Me search once the visitor
     * picks a location. The page only carries public fields — the same ones
     * the landing page's "View Details" modal already used.
     */
    public function nearby(): View
    {
        $objActiveListings = collect(TourismCatalog::listings())->where('isPubliclyVisible', true);

        return view('nearby', [
            'nearbyPlaces' => $this->nearbyPlaces($objActiveListings),
            'listingDetails' => $this->_listingDetails($objActiveListings),
            'categories' => Category::query()->active()->get()
                ->map(fn (Category $objCategory) => [
                    'slug' => $objCategory->legacySlug(),
                    'label' => $objCategory->isDestinationCategory() ? 'Destinations' : $objCategory->cat_name,
                    'icon' => TourismCatalog::categoryIcon($objCategory->legacySlug()),
                ])
                ->all(),
        ]);
    }

    /**
     * The public modal fields of each listing, keyed by listing id.
     * "Get directions" links are built here (destination coordinates only,
     * App\Support\DirectionsLink). Public modal fields only (Objective 3,
     * Phase 7): no workflow status, visibility flag, or stored file name
     * reaches the page's JSON.
     *
     * @param  Collection<int, array<string, mixed>>  $objActiveListings
     * @return array<string, array<string, mixed>>
     */
    private function _listingDetails(Collection $objActiveListings): array
    {
        return $objActiveListings
            ->map(fn (array $arrListing) => [
                ...array_intersect_key($arrListing, array_flip(self::MODAL_FIELDS)),
                'categoryLabel' => TourismCatalog::categoryLabel($arrListing['category']),
                'directionsUrl' => DirectionsLink::toDestination($arrListing['lat'], $arrListing['lng']),
            ])
            ->keyBy('id')
            ->all();
    }

    /**
     * Active listings that have coordinates, in the shape the Nearby page's
     * Mapbox map and list are built from. `directionsUrl` is the server-built
     * destination-only link; `hours` is the stored value or null.
     *
     * @param  Collection<int, array<string, mixed>>  $objActiveListings
     * @return array<int, array<string, mixed>>
     */
    private function nearbyPlaces(Collection $objActiveListings): array
    {
        return $objActiveListings
            ->filter(fn ($arrListing) => $arrListing['lat'] !== null && $arrListing['lng'] !== null)
            ->map(fn ($arrListing) => [
                'slug' => $arrListing['id'],
                'name' => $arrListing['name'],
                'kind' => $arrListing['category'] === 'destinations' ? 'destination' : 'establishment',
                'category' => $arrListing['category'],
                'categoryLabel' => $arrListing['destinationType'] ?: TourismCatalog::categoryLabel($arrListing['category']),
                'municipality' => $arrListing['municipality'],
                'barangay' => $arrListing['barangay'],
                'lat' => (float) $arrListing['lat'],
                'lng' => (float) $arrListing['lng'],
                'hours' => $arrListing['hours'],
                'href' => $arrListing['href'],
                'directionsUrl' => DirectionsLink::toDestination($arrListing['lat'], $arrListing['lng']),
            ])
            ->values()
            ->all();
    }
}
