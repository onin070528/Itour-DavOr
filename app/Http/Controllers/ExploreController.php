<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Renders the public Explore hub — the unified Tourism Directory of
 * destinations and tourism establishments, with server-side keyword search,
 * filters, and pagination, shown as a Grid, Table, or Map.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers;

use App\Http\Requests\FindNearMeRequest;
use App\Models\Listing;
use App\Services\NearbySearchService;
use App\Support\TourismCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExploreController extends Controller
{
    /** The longest keyword the directory search accepts. */
    private const MAX_KEYWORD_LENGTH = 100;

    /** The Explore views: server-rendered cards, a table, or a map of the current page. */
    private const VIEWS = ['grid', 'table', 'map'];

    /**
     * Display the unified Tourism Directory (Objective 3, R1/R11).
     *
     * Everything is filtered on the server — only publicly visible listings
     * (Listing::scopePubliclyVisible()) ever leave the database, one page
     * at a time (config 'tourism_directory.results_per_page'). The `q`,
     * `municipality`, and `category` query parameters keep working for the
     * existing hero search, quick pills, and municipality chips; `type`
     * filters destinations by their configured destination type. Unknown
     * values are ignored rather than rejected, since this is a public page.
     */
    public function index(Request $objRequest): View
    {
        $arrFilters = $this->_publicFilters($objRequest);

        $objListings = Listing::query()
            ->publiclyVisible()
            ->with(['categoryRecord', 'establishmentImages' => fn ($objQuery) => $objQuery->where('img_status', 'PUBLISHED')])
            ->when($arrFilters['q'] !== '', fn ($objQuery) => $objQuery->matchingPublicSearch($arrFilters['q']))
            ->when($arrFilters['municipality'] !== '', fn ($objQuery) => $objQuery->where('lst_municipality', $arrFilters['municipality']))
            ->when($arrFilters['category'] !== '', fn ($objQuery) => $objQuery->where('lst_category', $arrFilters['category']))
            ->when($arrFilters['type'] !== '', fn ($objQuery) => $objQuery->where('lst_category', 'destinations')->where('lst_type', $arrFilters['type']))
            ->orderBy('lst_name')
            ->orderBy('lst_id')
            ->paginate((int) config('tourism_directory.results_per_page'))
            ->withQueryString();

        $arrEntries = $objListings->getCollection()
            ->map(fn (Listing $objListing) => TourismCatalog::catalogEntry($objListing))
            ->all();

        return view('explore', [
            'listings' => $objListings,
            'entries' => $arrEntries,
            'mapPlaces' => $this->_mapPlaces($objListings->getCollection()),
            'filters' => $arrFilters,
            'categories' => TourismCatalog::exploreCategories(),
            'municipalities' => TourismCatalog::municipalities(),
            'destinationTypes' => config('tourism_directory.destination_types'),
        ]);
    }

    /**
     * Find Near Me (Objective 3, R10): POST JSON with the visitor's current
     * location, answered with the nearest public listings as JSON.
     *
     * FindNearMeRequest has already validated the location (including the
     * Davao Oriental guard) before this runs, so no search happens for an
     * invalid point. Only the rounded coordinates (about 4 decimal places)
     * are used, as bound query values in NearbySearchService — the single,
     * authoritative distance calculation. The location is never stored,
     * logged, cached, put in the session, or returned in the response, and
     * the response is marked no-store.
     */
    public function nearMe(FindNearMeRequest $objRequest, NearbySearchService $objNearby): JsonResponse
    {
        $mixRadius = $objRequest->validated('radius');
        $fltRadiusKm = $objNearby->resolveRadiusKm($mixRadius !== null ? (float) $mixRadius : null);
        $objCategory = $objRequest->selectedCategory();

        try {
            $objNearbyQuery = $objNearby->findNearbyListings(
                $objRequest->roundedLatitude(),
                $objRequest->roundedLongitude(),
                $fltRadiusKm,
                null,
                $objCategory !== null ? [$objCategory->cat_id] : [],
            );
            $objResults = $objNearby->paginate($objNearbyQuery, (int) ($objRequest->validated('page') ?? 1));
        } catch (\Throwable $objException) {
            // Summary comment: only the exception class is logged — a database
            // error message would contain the SQL with the bound coordinates.
            Log::warning('Find Near Me search failed.', ['exception' => $objException::class]);

            return response()
                ->json(['message' => "Nearby places can't be shown right now. Please try again later."], 503)
                ->header('Cache-Control', 'no-store, private');
        }

        return response()
            ->json([
                'radiusKm' => (int) $fltRadiusKm,
                'category' => $objCategory?->cat_name,
                'total' => $objResults->total(),
                'page' => $objResults->currentPage(),
                'lastPage' => $objResults->lastPage(),
                'results' => $objResults->getCollection()->map(fn (Listing $objListing) => $objNearby->toPublicResult($objListing))->values(),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * The request's filters, each limited to known values: a trimmed,
     * length-capped keyword; a municipality, category slug, and destination
     * type from the configured lists; and a view name. Anything else
     * becomes "no filter".
     *
     * @return array{q: string, municipality: string, category: string, type: string, view: string}
     */
    private function _publicFilters(Request $objRequest): array
    {
        $fnPick = fn (string $strKey, array $arrAllowed) => in_array($objRequest->query($strKey), $arrAllowed, true) ? (string) $objRequest->query($strKey) : '';
        $mixKeyword = $objRequest->query('q');
        $strKeyword = is_string($mixKeyword) ? Str::limit(trim($mixKeyword), self::MAX_KEYWORD_LENGTH, '') : '';
        $strView = $fnPick('view', self::VIEWS);

        return [
            'q' => $strKeyword,
            'municipality' => $fnPick('municipality', array_column(TourismCatalog::municipalities(), 'name')),
            'category' => $fnPick('category', array_column(TourismCatalog::exploreCategories(), 'slug')),
            'type' => $fnPick('type', config('tourism_directory.destination_types')),
            'view' => $strView !== '' ? $strView : 'grid',
        ];
    }

    /**
     * The current page's listings that have a valid map location
     * (Listing::hasValidCoordinates()), in the small public pin shape the
     * Explore map script plots. They come from the same public, paginated
     * query as the Grid and Table — no extra query, no other listing, and
     * no id, owner, contact, QR, or status field. Links use the slug.
     *
     * @param  Collection<int, Listing>  $colListings
     * @return array<int, array<string, mixed>>
     */
    private function _mapPlaces(Collection $colListings): array
    {
        return $colListings
            ->filter(fn (Listing $objListing) => $objListing->hasValidCoordinates())
            ->map(fn (Listing $objListing) => [
                'slug' => $objListing->lst_slug,
                'name' => $objListing->lst_name,
                'kind' => $objListing->isDestinationOnly() ? 'destination' : 'establishment',
                'categoryLabel' => $objListing->isDestinationOnly() && $objListing->lst_type ? $objListing->lst_type : TourismCatalog::categoryLabel($objListing->lst_category),
                'municipality' => $objListing->lst_municipality,
                'barangay' => $objListing->lst_barangay,
                'lat' => (float) $objListing->lst_lat,
                'lng' => (float) $objListing->lst_lng,
                'href' => route('listings.show', $objListing->lst_slug),
                'displayImageUrl' => $objListing->publicCoverImageUrl(),
                'categoryIcon' => TourismCatalog::categoryIcon($objListing->lst_category),
            ])
            ->values()
            ->all();
    }
}
