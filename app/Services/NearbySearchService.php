<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: The single nearest-neighbor (nearby) search for the Tourism
 * Directory — straight-line Haversine distance calculated in PostgreSQL.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Services;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only place in iTOUR that calculates geographic distance (CLAUDE.md
 * section 10). Controllers, models, views, and JavaScript never compute
 * distance themselves; they call this service.
 *
 * Method: the Haversine formula on a sphere of radius 6371 km, evaluated
 * by PostgreSQL over tbl_listings.lst_lat / lst_lng (decimal degrees, the
 * source of truth — no PostGIS, no spatial column):
 *
 *   a = sin²(Δlat / 2) + cos(lat1) × cos(lat2) × sin²(Δlng / 2)
 *   a = LEAST(1, GREATEST(0, a))     (clamp against floating-point drift)
 *   c = 2 × asin(sqrt(a))
 *   distance = 6371 × c              (kilometres)
 *
 * A latitude/longitude bounding box first narrows the candidate rows (using
 * the (lst_lat, lst_lng) index); the Haversine distance is still the final
 * radius test and the sort key. Coordinates here are always passed as
 * (latitude, longitude); Mapbox/GeoJSON use the opposite [lng, lat] order.
 *
 * Privacy: a reference point (for example a tourist's Find Near Me
 * location) is only ever a bound query parameter. This service never
 * writes it to the database, a log, the session, or the cache.
 */
class NearbySearchService
{
    /** Mean Earth radius in kilometres used by the Haversine formula. */
    public const EARTH_RADIUS_KM = 6371;

    /**
     * Publicly visible listings with valid coordinates within the radius of
     * the reference point, nearest first (ties by name, then id), each with
     * a `distance_km` attribute. Returns a query so callers can limit or
     * paginate it (see paginate()); nothing is loaded here.
     *
     * @param  float  $fltLatitude  Reference latitude, -90..90.
     * @param  float  $fltLongitude  Reference longitude, -180..180.
     * @param  ?float  $fltRadiusKm  Search radius; null uses the configured default, larger than the maximum is capped.
     * @param  ?int  $intExcludedListingId  The reference listing itself, when searching around one.
     * @param  array<int, int|string>  $arrCategoryIds  tbl_categories ids to keep (empty = all categories).
     */
    public function findNearbyListings(
        float $fltLatitude,
        float $fltLongitude,
        ?float $fltRadiusKm = null,
        ?int $intExcludedListingId = null,
        array $arrCategoryIds = [],
    ): Builder {
        $this->_assertValidCoordinate($fltLatitude, $fltLongitude);

        $fltSearchRadiusKm = $this->resolveRadiusKm($fltRadiusKm);
        $arrBox = $this->_boundingBox($fltLatitude, $fltLongitude, $fltSearchRadiusKm);
        $arrCategoryFilter = array_values(array_unique(array_map('intval', $arrCategoryIds)));

        // Summary comment: the reference point is a one-row subquery joined to
        // every candidate, so it is bound exactly once and the Haversine
        // expression itself contains only column names.
        $objReference = DB::query()->selectRaw(
            'CAST(? AS DOUBLE PRECISION) AS ref_latitude, CAST(? AS DOUBLE PRECISION) AS ref_longitude',
            [$fltLatitude, $fltLongitude],
        );

        // Summary comment: candidates = public listings with valid
        // coordinates inside the bounding box, each with its distance.
        $objCandidates = Listing::query()
            ->crossJoinSub($objReference, 'reference')
            ->select('tbl_listings.*')
            ->selectRaw($this->_haversineDistanceSql('reference.ref_latitude', 'reference.ref_longitude', 'tbl_listings.lst_lat', 'tbl_listings.lst_lng').' AS distance_km')
            ->publiclyVisible()
            ->whereNotNull('tbl_listings.lst_lat')
            ->whereNotNull('tbl_listings.lst_lng')
            ->whereBetween('tbl_listings.lst_lat', [-90, 90])
            ->whereBetween('tbl_listings.lst_lng', [-180, 180])
            ->whereBetween('tbl_listings.lst_lat', [$arrBox['min_latitude'], $arrBox['max_latitude']])
            ->whereBetween('tbl_listings.lst_lng', [$arrBox['min_longitude'], $arrBox['max_longitude']])
            ->when($intExcludedListingId !== null, fn (Builder $objQuery) => $objQuery->where('tbl_listings.lst_id', '!=', $intExcludedListingId))
            ->when($arrCategoryFilter !== [], fn (Builder $objQuery) => $objQuery->whereIn('tbl_listings.cat_id', $arrCategoryFilter));

        // Summary comment: the outer query applies the authoritative
        // Haversine radius test and the nearest-first ordering. The
        // subquery is aliased as tbl_listings so rows hydrate as Listing models.
        return Listing::query()
            ->fromSub($objCandidates, 'tbl_listings')
            ->where('distance_km', '<=', $fltSearchRadiusKm)
            ->orderBy('distance_km')
            ->orderBy('lst_name')
            ->orderBy('lst_id');
    } // end findNearbyListings

    /**
     * Listings near another listing (a destination's "Find Nearby"): its own
     * stored coordinates are the reference point, and it is left out of its
     * own results. A listing without valid coordinates has no nearby search
     * — callers check Listing::hasValidCoordinates() first.
     *
     * @param  array<int, int|string>  $arrCategoryIds  tbl_categories ids to keep (empty = all categories).
     */
    public function findNearbyListingsAround(Listing $objReference, ?float $fltRadiusKm = null, array $arrCategoryIds = []): Builder
    {
        if (! $objReference->hasValidCoordinates()) {
            throw new InvalidArgumentException('This listing has no valid map location, so it has no nearby search.');
        }

        return $this->findNearbyListings((float) $objReference->lst_lat, (float) $objReference->lst_lng, $fltRadiusKm, $objReference->lst_id, $arrCategoryIds);
    }

    /**
     * One page of a nearby query (config 'results_per_page', 20), with the
     * relations the public results need loaded up front (no N+1 queries).
     */
    public function paginate(Builder $objNearbyQuery, ?int $intPage = null): LengthAwarePaginator
    {
        $intPerPage = (int) config('tourism_directory.results_per_page');

        return $objNearbyQuery
            ->with($this->_publicResultRelations())
            ->paginate($intPerPage, ['*'], 'page', $intPage);
    }

    /**
     * Nearby listings around $objReference grouped by category (the
     * destination page's "Nearby Tourism Services"), at most
     * $intPerGroup per category (config 'results_per_group', 5), nearest
     * first inside each group. Groups are ordered by their nearest listing.
     * One query: PostgreSQL ranks each category's rows with ROW_NUMBER().
     *
     * @return Collection<string, Collection<int, Listing>> Keyed by category name.
     */
    public function findNearbyGroupedByCategory(Listing $objReference, ?float $fltRadiusKm = null, ?int $intPerGroup = null): Collection
    {
        $intGroupLimit = $intPerGroup ?? (int) config('tourism_directory.nearby.results_per_group');

        $objRanked = $this->findNearbyListingsAround($objReference, $fltRadiusKm)
            ->reorder()
            ->select('tbl_listings.*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY tbl_listings.cat_id ORDER BY tbl_listings.distance_km ASC, tbl_listings.lst_name ASC, tbl_listings.lst_id ASC) AS group_rank');

        return Listing::query()
            ->fromSub($objRanked, 'tbl_listings')
            ->where('group_rank', '<=', $intGroupLimit)
            ->orderBy('distance_km')
            ->orderBy('lst_name')
            ->with($this->_publicResultRelations())
            ->get()
            ->groupBy(fn (Listing $objListing) => $objListing->categoryName());
    } // end findNearbyGroupedByCategory

    /**
     * The Haversine distance in kilometres between two points, calculated
     * by PostgreSQL with the same expression the nearby search uses.
     */
    public function calculateDistanceKm(float $fltFromLatitude, float $fltFromLongitude, float $fltToLatitude, float $fltToLongitude): float
    {
        $this->_assertValidCoordinate($fltFromLatitude, $fltFromLongitude);
        $this->_assertValidCoordinate($fltToLatitude, $fltToLongitude);

        $strSql = 'SELECT '.$this->_haversineDistanceSql('pair.from_latitude', 'pair.from_longitude', 'pair.to_latitude', 'pair.to_longitude').' AS distance_km '
            .'FROM (SELECT CAST(? AS DOUBLE PRECISION) AS from_latitude, CAST(? AS DOUBLE PRECISION) AS from_longitude, '
            .'CAST(? AS DOUBLE PRECISION) AS to_latitude, CAST(? AS DOUBLE PRECISION) AS to_longitude) AS pair';

        return (float) DB::selectOne($strSql, [$fltFromLatitude, $fltFromLongitude, $fltToLatitude, $fltToLongitude])->distance_km;
    }

    /**
     * The radius actually searched: the configured default when none is
     * given, never more than the configured maximum, never zero or less.
     */
    public function resolveRadiusKm(?float $fltRadiusKm): float
    {
        $fltDefaultRadiusKm = (float) config('tourism_directory.nearby.default_radius_km');
        $fltMaximumRadiusKm = (float) config('tourism_directory.nearby.max_radius_km');
        $blnIsUsable = $fltRadiusKm !== null && $fltRadiusKm > 0;

        if (! $blnIsUsable) {
            return $fltDefaultRadiusKm;
        }

        return min($fltRadiusKm, $fltMaximumRadiusKm);
    }

    /**
     * A straight-line distance for display: "450 m away" under 1 km
     * (rounded to 10 m, never below 10 m), otherwise "1.2 km away".
     */
    public function formatDistance(float $fltDistanceKm): string
    {
        $intMeters = (int) (round($fltDistanceKm * 1000 / 10) * 10);

        if ($intMeters < 1000) {
            return max(10, $intMeters).' m away';
        }

        return number_format($fltDistanceKm, 1).' km away';
    }

    /**
     * A tourist's coordinate rounded to the configured precision (about
     * 11 m at 4 decimal places) before it is used — Find Near Me never
     * works with, or keeps, the exact location.
     */
    public function roundCoordinate(float $fltCoordinate): float
    {
        return round($fltCoordinate, (int) config('tourism_directory.nearby.coordinate_precision'));
    }

    /**
     * The public, safe shape of one nearby result: no internal id, owner,
     * QR uuid, status, or workflow field. Expects a listing returned by this
     * service (it carries `distance_km`).
     *
     * @return array{type: string, slug: string, name: string, category: string, subtype: ?string, municipality: string, barangay: ?string, latitude: float, longitude: float, distanceMeters: int, distanceLabel: string, imageUrl: ?string, url: string}
     */
    public function toPublicResult(Listing $objListing): array
    {
        $fltDistanceKm = (float) $objListing->getAttribute('distance_km');

        return [
            'type' => $objListing->isDestinationOnly() ? 'destination' : 'establishment',
            'slug' => $objListing->lst_slug,
            'name' => $objListing->lst_name,
            'category' => $objListing->categoryName(),
            'subtype' => $objListing->lst_type,
            'municipality' => $objListing->lst_municipality,
            'barangay' => $objListing->lst_barangay,
            'latitude' => (float) $objListing->lst_lat,
            'longitude' => (float) $objListing->lst_lng,
            'distanceMeters' => (int) round($fltDistanceKm * 1000),
            'distanceLabel' => $this->formatDistance($fltDistanceKm),
            'imageUrl' => $objListing->publicCoverImageUrl(),
            'url' => route('listings.show', $objListing->lst_slug),
        ];
    }

    /**
     * The Haversine distance in kilometres between two points given as SQL
     * column references, with `a` clamped to [0, 1] before ASIN(SQRT(a)).
     * The arguments are always internal column names chosen in this class —
     * never request input — so no value is ever concatenated into the SQL.
     */
    private function _haversineDistanceSql(string $strLatitudeA, string $strLongitudeA, string $strLatitudeB, string $strLongitudeB): string
    {
        $intEarthRadiusKm = self::EARTH_RADIUS_KM;

        return "{$intEarthRadiusKm} * 2 * ASIN(SQRT(LEAST(1, GREATEST(0, "
            ."POWER(SIN(RADIANS({$strLatitudeB} - {$strLatitudeA}) / 2), 2) "
            ."+ COS(RADIANS({$strLatitudeA})) * COS(RADIANS({$strLatitudeB})) "
            ."* POWER(SIN(RADIANS({$strLongitudeB} - {$strLongitudeA}) / 2), 2)"
            .'))))';
    }

    /**
     * Relations every public nearby result reads: its category name and its
     * PUBLISHED photos (for the cover image).
     *
     * @return array<int|string, mixed>
     */
    private function _publicResultRelations(): array
    {
        return [
            'categoryRecord',
            'establishmentImages' => fn ($objQuery) => $objQuery->where('img_status', 'PUBLISHED'),
        ];
    }

    /**
     * The smallest latitude/longitude box that contains every point within
     * $fltRadiusKm of the reference point on the same 6371 km sphere, so the
     * prefilter never drops a row the Haversine test would keep. Falls back
     * to the full longitude range near the poles or across the 180th
     * meridian.
     *
     * @return array{min_latitude: float, max_latitude: float, min_longitude: float, max_longitude: float}
     */
    private function _boundingBox(float $fltLatitude, float $fltLongitude, float $fltRadiusKm): array
    {
        $fltAngularRadius = $fltRadiusKm / self::EARTH_RADIUS_KM;
        $fltLatitudeDelta = rad2deg($fltAngularRadius);
        $fltMinLatitude = max(-90.0, $fltLatitude - $fltLatitudeDelta);
        $fltMaxLatitude = min(90.0, $fltLatitude + $fltLatitudeDelta);

        $fltSineOfRadius = sin($fltAngularRadius);
        $fltCosineOfLatitude = cos(deg2rad($fltLatitude));
        $blnTouchesPole = $fltMinLatitude <= -90.0 || $fltMaxLatitude >= 90.0 || $fltSineOfRadius >= $fltCosineOfLatitude;

        if ($blnTouchesPole) {
            return ['min_latitude' => $fltMinLatitude, 'max_latitude' => $fltMaxLatitude, 'min_longitude' => -180.0, 'max_longitude' => 180.0];
        }

        $fltLongitudeDelta = rad2deg(asin($fltSineOfRadius / $fltCosineOfLatitude));
        $fltMinLongitude = $fltLongitude - $fltLongitudeDelta;
        $fltMaxLongitude = $fltLongitude + $fltLongitudeDelta;
        $blnCrossesAntimeridian = $fltMinLongitude < -180.0 || $fltMaxLongitude > 180.0;

        if ($blnCrossesAntimeridian) {
            return ['min_latitude' => $fltMinLatitude, 'max_latitude' => $fltMaxLatitude, 'min_longitude' => -180.0, 'max_longitude' => 180.0];
        }

        return ['min_latitude' => $fltMinLatitude, 'max_latitude' => $fltMaxLatitude, 'min_longitude' => $fltMinLongitude, 'max_longitude' => $fltMaxLongitude];
    } // end _boundingBox

    /**
     * Rejects a coordinate outside the valid latitude/longitude range
     * (callers validate request input first; this is a last guard).
     */
    private function _assertValidCoordinate(float $fltLatitude, float $fltLongitude): void
    {
        $blnIsValidLatitude = $fltLatitude >= -90.0 && $fltLatitude <= 90.0;
        $blnIsValidLongitude = $fltLongitude >= -180.0 && $fltLongitude <= 180.0;

        if (! $blnIsValidLatitude || ! $blnIsValidLongitude) {
            throw new InvalidArgumentException('The coordinate is outside the valid latitude/longitude range.');
        }
    }
}
