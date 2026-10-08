<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Public detail page for one destination or establishment — cover
 * image, gallery of PUBLISHED photos, public information — plus a
 * destination's Nearby Tourism Services and its full "Find Nearby" list.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Listing;
use App\Services\NearbySearchService;
use App\Support\DirectionsLink;
use App\Support\TourismCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ListingDetailController extends Controller
{
    /** Heading of the nearby group that lists other destinations. */
    private const OTHER_DESTINATIONS_LABEL = 'Other Nearby Destinations';

    /**
     * A publicly visible listing is reachable by anyone — same rule Explore
     * applies to its own list (see ExploreController). A signed-in LGU/PTO
     * user allowed to view this listing (App\Policies\ListingPolicy::view())
     * may still open it even before it's published — PTO's "Preview as
     * public" link on a FOR_PTO_REVIEW listing relies on this. A destination
     * also shows its Nearby Tourism Services (Objective 3, R9): at most
     * 5 per category, nearest first, from App\Services\NearbySearchService.
     */
    public function show(Request $objRequest, Listing $listing, NearbySearchService $objNearby): View
    {
        $blnCanPreview = $objRequest->user()?->can('view', $listing) ?? false;

        abort_unless($listing->isPubliclyVisible() || $blnCanPreview, 404);

        $listing->loadMissing('categoryRecord');
        $blnIsDestination = $listing->isDestinationOnly();
        $blnHasLocation = $listing->hasValidCoordinates();
        [$colNearbyGroups, $blnIsNearbyUnavailable] = $blnIsDestination && $blnHasLocation
            ? $this->_nearbyGroups($listing, $objNearby)
            : [collect(), false];

        return view('listing-detail', [
            'listing' => $listing,
            'categoryLabel' => TourismCatalog::categoryLabel($listing->lst_category),
            'categoryIcon' => TourismCatalog::categoryIcon($listing->lst_category),
            'coverImageUrl' => $listing->publicCoverImageUrl(),
            'galleryImages' => $listing->publishedGalleryImages(),
            'isDestination' => $blnIsDestination,
            'hasLocation' => $blnHasLocation,
            'nearbyGroups' => $colNearbyGroups,
            'isNearbyUnavailable' => $blnIsNearbyUnavailable,
            'nearbyRadiusKm' => $objNearby->resolveRadiusKm(null),
            'mapData' => $blnIsDestination && $blnHasLocation ? $this->_mapData($listing, $colNearbyGroups) : null,
            'directionsUrl' => $blnIsDestination ? DirectionsLink::forListing($listing) : null,
            'metaDescription' => $this->_metaDescription($listing),
        ]);
    }

    /**
     * What the destination map plots (Objective 3, Phase 5): the destination
     * itself at its stored coordinates, plus the nearby listings already
     * found by NearbySearchService — their server-calculated distance
     * labels included, so the browser never calculates a distance. Public
     * fields only; links use the slug.
     *
     * @param  Collection<int, array{label: string, categorySlug: ?string, items: array<int, array<string, mixed>>}>  $colNearbyGroups
     * @return array{reference: array{name: string, lat: float, lng: float}, places: array<int, array<string, mixed>>}
     */
    private function _mapData(Listing $objListing, Collection $colNearbyGroups): array
    {
        $arrPlaces = $colNearbyGroups
            ->flatMap(fn (array $arrGroup) => $arrGroup['items'])
            ->map(fn (array $arrItem) => [
                'slug' => $arrItem['slug'],
                'name' => $arrItem['name'],
                'kind' => $arrItem['type'],
                'categoryLabel' => $arrItem['subtype'] ?: $arrItem['category'],
                'distanceLabel' => $arrItem['distanceLabel'],
                'lat' => $arrItem['latitude'],
                'lng' => $arrItem['longitude'],
                'href' => $arrItem['url'],
            ])
            ->values()
            ->all();

        return [
            'reference' => ['name' => $objListing->lst_name, 'lat' => (float) $objListing->lst_lat, 'lng' => (float) $objListing->lst_lng],
            'places' => $arrPlaces,
        ];
    }

    /**
     * "Find Nearby" for a published destination: every public listing
     * around it within the chosen radius (1, 5, 10, 25, or 50 km; default
     * 10 km), optionally one category, nearest first, 20 per page. Its own
     * stored coordinates are the reference point, so this is a normal GET.
     * Unknown radius or category values fall back to the defaults.
     */
    public function nearby(Request $objRequest, Listing $listing, NearbySearchService $objNearby): View
    {
        abort_unless($listing->isPubliclyVisible() && $listing->isDestinationOnly(), 404);

        $arrRadiusOptions = config('tourism_directory.nearby.radius_options_km');
        $mixRadius = $objRequest->query('radius');
        $blnIsKnownRadius = is_string($mixRadius) && in_array($mixRadius, array_map('strval', $arrRadiusOptions), true);
        $fltRadiusKm = $objNearby->resolveRadiusKm($blnIsKnownRadius ? (float) $mixRadius : null);
        $colCategories = Category::query()->active()->get();
        $objCategory = $colCategories->first(fn (Category $objOption) => $objOption->legacySlug() === $objRequest->query('category'));
        $blnHasLocation = $listing->hasValidCoordinates();
        $objResults = null;
        $blnIsUnavailable = false;

        if ($blnHasLocation) {
            try {
                $objQuery = $objNearby->findNearbyListingsAround($listing, $fltRadiusKm, $objCategory !== null ? [$objCategory->cat_id] : []);
                $objResults = $objNearby->paginate($objQuery)->withQueryString();
            } catch (\Throwable $objException) {
                // Summary comment: a failed search never breaks the page; only the listing id and
                // exception class are logged (a database error message would contain SQL).
                Log::warning('Nearby search failed for a destination page.', ['listing_id' => $listing->lst_id, 'exception' => $objException::class]);
                $blnIsUnavailable = true;
            }
        }

        return view('listing-nearby', [
            'listing' => $listing,
            'hasLocation' => $blnHasLocation,
            'isUnavailable' => $blnIsUnavailable,
            'results' => $objResults,
            'items' => $objResults !== null ? $objResults->getCollection()->map(fn (Listing $objNearbyListing) => $objNearby->toPublicResult($objNearbyListing))->all() : [],
            'radiusKm' => $fltRadiusKm,
            'radiusOptions' => $arrRadiusOptions,
            'categoryOptions' => $colCategories->mapWithKeys(fn (Category $objOption) => [$objOption->legacySlug() => $this->_categoryOptionLabel($objOption)])->all(),
            'selectedCategory' => $objCategory?->legacySlug() ?? '',
            'selectedCategoryLabel' => $objCategory !== null ? $this->_categoryOptionLabel($objCategory) : null,
        ]);
    }

    /**
     * The destination's nearby listings grouped by category, each group with
     * a display label, a "see all" category slug, and public result rows.
     * Returns [groups, unavailable] — a failed search shows a friendly
     * message instead of an error, and only the listing id is logged.
     *
     * @return array{0: Collection<int, array{label: string, categorySlug: ?string, items: array<int, array<string, mixed>>}>, 1: bool}
     */
    private function _nearbyGroups(Listing $objListing, NearbySearchService $objNearby): array
    {
        try {
            $colGroups = $objNearby->findNearbyGroupedByCategory($objListing)
                ->map(fn (Collection $colListings) => [
                    'label' => $colListings->first()->isDestinationOnly() ? self::OTHER_DESTINATIONS_LABEL : $colListings->first()->categoryName(),
                    'categorySlug' => $colListings->first()->categoryRecord?->legacySlug(),
                    'items' => $colListings->map(fn (Listing $objNearbyListing) => $objNearby->toPublicResult($objNearbyListing))->all(),
                ])
                ->values();

            return [$colGroups, false];
        } catch (\Throwable $objException) {
            Log::warning('Nearby services could not be loaded for a destination page.', ['listing_id' => $objListing->lst_id, 'exception' => $objException::class]);

            return [collect(), true];
        }
    } // end _nearbyGroups

    /**
     * A category's label in the Find Nearby filter.
     */
    private function _categoryOptionLabel(Category $objCategory): string
    {
        return $objCategory->isDestinationCategory() ? 'Destinations' : $objCategory->cat_name;
    }

    /**
     * The page's meta description: the listing's own description, trimmed,
     * or a plain sentence built from its public details.
     */
    private function _metaDescription(Listing $objListing): string
    {
        $strDescription = trim((string) $objListing->lst_description);

        if ($strDescription !== '') {
            return Str::limit($strDescription, 155);
        }

        return "{$objListing->lst_name} in {$objListing->lst_municipality}, Davao Oriental — on iTOUR, the province's official tourism directory.";
    }
}
