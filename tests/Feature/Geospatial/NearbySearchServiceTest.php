<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Objective 3, Phase 3 — PostgreSQL tests of the Haversine nearby
 * search (App\Services\NearbySearchService). Runs only on the dedicated
 * disposable PostgreSQL database itour_testing; skipped on SQLite, which
 * has no trigonometric functions.
 *
 *   DB_CONNECTION=pgsql DB_DATABASE=itour_testing php artisan test --group=geospatial
 *
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Services\NearbySearchService;
use Database\Seeders\CategorySeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

pest()->group('geospatial');

/*
 * Fixed fixture coordinates (not real places). Every point lies on the
 * reference latitude, east of the reference, so the distances are easy to
 * reason about: one degree of longitude at 6.955 N is about 110.38 km.
 */
const GEO_REFERENCE_LATITUDE = 6.9550;
const GEO_REFERENCE_LONGITUDE = 126.2170;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Geospatial tests need PostgreSQL: DB_CONNECTION=pgsql DB_DATABASE=itour_testing php artisan test --group=geospatial');
    }

    test()->seed(CategorySeeder::class);
});

/**
 * An independent Haversine in PHP — used only by these tests to check the
 * PostgreSQL result, never by the application.
 */
function geoReferenceDistanceKm(float $fltLatitudeA, float $fltLongitudeA, float $fltLatitudeB, float $fltLongitudeB): float
{
    $fltDeltaLatitude = deg2rad($fltLatitudeB - $fltLatitudeA);
    $fltDeltaLongitude = deg2rad($fltLongitudeB - $fltLongitudeA);
    $fltA = sin($fltDeltaLatitude / 2) ** 2 + cos(deg2rad($fltLatitudeA)) * cos(deg2rad($fltLatitudeB)) * sin($fltDeltaLongitude / 2) ** 2;

    return 6371 * 2 * asin(sqrt(min(1, max(0, $fltA))));
}

function geoMunicipality(): Municipality
{
    return Municipality::query()->firstOrCreate(['mun_code' => 'GEO'], ['mun_name' => 'Geo Town']);
}

/**
 * A listing at the given point. Defaults to a published destination.
 *
 * @param  array<string, mixed>  $arrOverrides
 */
function geoListing(string $strName, ?float $fltLatitude, ?float $fltLongitude, array $arrOverrides = []): Listing
{
    $objMunicipality = geoMunicipality();
    $strCategoryName = $arrOverrides['category_name'] ?? 'Tourist Destinations';
    unset($arrOverrides['category_name']);
    $objCategory = Category::query()->where('cat_name', $strCategoryName)->firstOrFail();
    $blnIsDestination = $objCategory->isDestinationCategory();

    return Listing::query()->create(array_merge([
        'lst_slug' => Str::slug($strName.'-'.Str::random(6)),
        'lst_name' => $strName,
        'lst_category' => $objCategory->legacySlug(),
        'cat_id' => $objCategory->cat_id,
        'lst_municipality' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_lat' => $fltLatitude,
        'lst_lng' => $fltLongitude,
        'lst_status' => $blnIsDestination ? 'Active' : 'PUBLISHED',
    ], $arrOverrides));
}

/**
 * The names returned by a nearby query, in order.
 *
 * @return array<int, string>
 */
function geoNames(Builder $objQuery): array
{
    return $objQuery->pluck('lst_name')->all();
}

/**
 * The standard line of points east of the reference.
 */
function geoSeedLine(): void
{
    geoListing('Point A 0.99 km', GEO_REFERENCE_LATITUDE, 126.2260);
    geoListing('Point B 4.97 km', GEO_REFERENCE_LATITUDE, 126.2620);
    geoListing('Point C 9.93 km', GEO_REFERENCE_LATITUDE, 126.3070);
    geoListing('Point D 11.04 km', GEO_REFERENCE_LATITUDE, 126.3170);
    geoListing('Point E 49.67 km', GEO_REFERENCE_LATITUDE, 126.6670);
}

test('PostgreSQL Haversine gives the 0.99 km fixture distance and matches the reference formula', function () {
    $fltDistanceKm = app(NearbySearchService::class)->calculateDistanceKm(GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE, GEO_REFERENCE_LATITUDE, 126.2260);

    expect(abs($fltDistanceKm - 0.99) / 0.99)->toBeLessThan(0.01);
    expect(abs($fltDistanceKm - geoReferenceDistanceKm(GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE, GEO_REFERENCE_LATITUDE, 126.2260)))->toBeLessThan(1e-9);
});

test('the clamp keeps identical and antipodal points valid (no out-of-range ASIN error)', function () {
    $objService = app(NearbySearchService::class);

    expect($objService->calculateDistanceKm(GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE, GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE))->toBe(0.0);

    // Summary comment: the exact antipode is half the circumference (pi x 6371 km).
    $fltAntipodeKm = $objService->calculateDistanceKm(GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE, -GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE - 180);
    expect(abs($fltAntipodeKm - M_PI * 6371) / (M_PI * 6371))->toBeLessThan(0.0001);
});

test('results are nearest first and inside the radius only, with matching distances', function () {
    geoSeedLine();
    $objService = app(NearbySearchService::class);

    $colNearby = $objService->findNearbyListings(GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE, 5)->get();

    expect($colNearby->pluck('lst_name')->all())->toBe(['Point A 0.99 km', 'Point B 4.97 km']);

    foreach ($colNearby as $objListing) {
        $fltExpectedKm = geoReferenceDistanceKm(GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE, $objListing->lst_lat, $objListing->lst_lng);
        expect(abs((float) $objListing->distance_km - $fltExpectedKm))->toBeLessThan(1e-9);
    } // end foreach nearby listing
});

test('each supported radius (1, 5, 10, 25, 50 km), the 10 km default, and the 50 km cap', function () {
    geoSeedLine();
    $objService = app(NearbySearchService::class);
    $fnNames = fn (?float $fltRadiusKm) => geoNames($objService->findNearbyListings(GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE, $fltRadiusKm));

    expect(config('tourism_directory.nearby.radius_options_km'))->toBe([1, 5, 10, 25, 50]);
    expect($fnNames(1))->toBe(['Point A 0.99 km']);
    expect($fnNames(5))->toBe(['Point A 0.99 km', 'Point B 4.97 km']);
    expect($fnNames(10))->toBe(['Point A 0.99 km', 'Point B 4.97 km', 'Point C 9.93 km']);
    expect($fnNames(25))->toBe(['Point A 0.99 km', 'Point B 4.97 km', 'Point C 9.93 km', 'Point D 11.04 km']);
    expect($fnNames(50))->toBe(['Point A 0.99 km', 'Point B 4.97 km', 'Point C 9.93 km', 'Point D 11.04 km', 'Point E 49.67 km']);

    // Summary comment: no radius = the 10 km default; a larger request is capped at 50 km; zero/negative = default.
    expect($fnNames(null))->toBe($fnNames(10));
    expect($fnNames(999))->toBe($fnNames(50));
    expect($fnNames(0))->toBe($fnNames(10));
    expect($fnNames(-5))->toBe($fnNames(10));
});

test('the bounding box only narrows candidates — the Haversine distance decides', function () {
    // Summary comment: both points lie inside the 10 km bounding box; only
    // the one within 10 km on the diagonal is returned, the box corner
    // (about 14 km away) is not.
    geoListing('Diagonal 9.9 km', 7.01796, 126.28042);
    geoListing('Box corner 14 km', 7.04400, 126.30670);

    $colNearby = app(NearbySearchService::class)->findNearbyListings(GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE, 10)->get();

    expect($colNearby->pluck('lst_name')->all())->toBe(['Diagonal 9.9 km']);
    expect((float) $colNearby->first()->distance_km)->toBeLessThan(10.0);
    expect(geoReferenceDistanceKm(GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE, 7.04400, 126.30670))->toBeGreaterThan(10.0);
});

test('only publicly visible listings with valid coordinates are searched', function () {
    geoListing('Published Destination', GEO_REFERENCE_LATITUDE, 126.2200);
    geoListing('Published Establishment', GEO_REFERENCE_LATITUDE, 126.2210, ['category_name' => 'Accommodation']);

    foreach (['DRAFT', 'FOR_PTO_REVIEW', Listing::STATUS_FOR_CORRECTION, 'Suspended', 'Archived'] as $strStatus) {
        geoListing("Destination {$strStatus}", GEO_REFERENCE_LATITUDE, 126.2200, ['lst_status' => $strStatus]);
    } // end foreach hidden destination status

    foreach (['DRAFT', 'FOR_LGU_REVIEW', 'FOR_PTO_REVIEW', 'UNPUBLISHED', 'Suspended', 'Archived'] as $strStatus) {
        geoListing("Establishment {$strStatus}", GEO_REFERENCE_LATITUDE, 126.2200, ['category_name' => 'Accommodation', 'lst_status' => $strStatus]);
    } // end foreach hidden establishment status

    // Summary comment: a live listing whose own status says "Active" but is an establishment is not public either.
    geoListing('Establishment marked Active', GEO_REFERENCE_LATITUDE, 126.2200, ['category_name' => 'Accommodation', 'lst_status' => 'Active']);
    geoListing('No coordinates', null, null);
    geoListing('Latitude only', GEO_REFERENCE_LATITUDE, null);
    geoListing('Invalid latitude', 95.0, 126.2200);
    geoListing('Invalid longitude', GEO_REFERENCE_LATITUDE, 190.0);

    expect(geoNames(app(NearbySearchService::class)->findNearbyListings(GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE, 50)))
        ->toBe(['Published Destination', 'Published Establishment']);
});

test('category filter, destinations-only filter, and the reference listing excluded from its own results', function () {
    $objReference = geoListing('Reference Falls', GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE);
    geoListing('Near Beach', GEO_REFERENCE_LATITUDE, 126.2200);
    geoListing('Near Resort', GEO_REFERENCE_LATITUDE, 126.2190, ['category_name' => 'Accommodation']);
    geoListing('Near Diner', GEO_REFERENCE_LATITUDE, 126.2210, ['category_name' => 'Food & Dining']);
    $objService = app(NearbySearchService::class);
    $intDestinationsId = Category::query()->where('cat_name', 'Tourist Destinations')->value('cat_id');
    $intAccommodationId = Category::query()->where('cat_name', 'Accommodation')->value('cat_id');
    $intFoodId = Category::query()->where('cat_name', 'Food & Dining')->value('cat_id');

    expect(geoNames($objService->findNearbyListingsAround($objReference)))->toBe(['Near Resort', 'Near Beach', 'Near Diner']);
    expect(geoNames($objService->findNearbyListingsAround($objReference, null, [$intDestinationsId])))->toBe(['Near Beach']);
    expect(geoNames($objService->findNearbyListingsAround($objReference, null, [$intAccommodationId, $intFoodId])))->toBe(['Near Resort', 'Near Diner']);

    // Summary comment: a coordinate search from the same point does include the reference listing itself.
    expect(geoNames($objService->findNearbyListings(GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE, null, null, [$intDestinationsId])))->toBe(['Reference Falls', 'Near Beach']);
});

test('results paginate at 20 per page, continuing nearest first across pages', function () {
    foreach (range(1, 25) as $intIndex) {
        geoListing(sprintf('Spot %02d', $intIndex), GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE + $intIndex * 0.001);
    } // end foreach spot

    $objService = app(NearbySearchService::class);
    $objFirstPage = $objService->paginate($objService->findNearbyListings(GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE), 1);
    $objSecondPage = $objService->paginate($objService->findNearbyListings(GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE), 2);

    expect($objFirstPage->total())->toBe(25);
    expect($objFirstPage->perPage())->toBe(20);
    expect($objFirstPage->count())->toBe(20);
    expect($objSecondPage->count())->toBe(5);
    expect([...$objFirstPage->pluck('lst_name'), ...$objSecondPage->pluck('lst_name')])
        ->toBe(array_map(fn (int $intIndex) => sprintf('Spot %02d', $intIndex), range(1, 25)));
});

test('nearby results grouped by category: at most 5 per group, nearest first', function () {
    $objReference = geoListing('Reference Falls', GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE);

    foreach (range(1, 7) as $intIndex) {
        geoListing("Resort {$intIndex}", GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE + $intIndex * 0.002, ['category_name' => 'Accommodation']);
    } // end foreach resort

    geoListing('Beach 1', GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE + 0.001);
    geoListing('Beach 2', GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE + 0.005);
    geoListing('Diner 1', GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE + 0.003, ['category_name' => 'Food & Dining']);

    $colGroups = app(NearbySearchService::class)->findNearbyGroupedByCategory($objReference);

    expect($colGroups->keys()->all())->toBe(['Tourist Destinations', 'Accommodation', 'Food & Dining']);
    expect($colGroups['Tourist Destinations']->pluck('lst_name')->all())->toBe(['Beach 1', 'Beach 2']);
    expect($colGroups['Accommodation']->pluck('lst_name')->all())->toBe(['Resort 1', 'Resort 2', 'Resort 3', 'Resort 4', 'Resort 5']);
    expect($colGroups['Food & Dining']->pluck('lst_name')->all())->toBe(['Diner 1']);
});

test('no listings in range returns an empty result, not an error', function () {
    geoListing('Far Away', GEO_REFERENCE_LATITUDE, 126.6670);

    $objService = app(NearbySearchService::class);

    expect($objService->findNearbyListings(GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE, 1)->get())->toBeEmpty();
    expect($objService->paginate($objService->findNearbyListings(GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE, 1))->total())->toBe(0);
});

test('the public result shape carries distance and no internal fields', function () {
    $objReference = geoListing('Reference Falls', GEO_REFERENCE_LATITUDE, GEO_REFERENCE_LONGITUDE);
    geoListing('Near Resort', GEO_REFERENCE_LATITUDE, 126.2260, ['category_name' => 'Accommodation', 'lst_owner_name' => 'Private Owner', 'lst_type' => 'Resort']);
    $objService = app(NearbySearchService::class);

    $arrResult = $objService->toPublicResult($objService->findNearbyListingsAround($objReference)->firstOrFail());

    expect(array_keys($arrResult))->toBe(['type', 'slug', 'name', 'category', 'subtype', 'municipality', 'barangay', 'latitude', 'longitude', 'distanceMeters', 'distanceLabel', 'imageUrl', 'url']);
    expect($arrResult['type'])->toBe('establishment');
    expect($arrResult['category'])->toBe('Accommodation');
    expect($arrResult['distanceMeters'])->toBe(993);
    expect($arrResult['distanceLabel'])->toBe('990 m away');
    expect(json_encode($arrResult))->not->toContain('Private Owner');
});

test('a coordinate search writes nothing and logs nothing', function () {
    geoSeedLine();
    $arrStatements = [];
    $arrLogMessages = [];
    DB::listen(function ($objQuery) use (&$arrStatements) {
        $arrStatements[] = $objQuery->sql;
    });
    Event::listen(MessageLogged::class, function (MessageLogged $objMessage) use (&$arrLogMessages) {
        $arrLogMessages[] = $objMessage->message;
    });

    $objService = app(NearbySearchService::class);
    $fltLatitude = $objService->roundCoordinate(6.955049);
    $fltLongitude = $objService->roundCoordinate(126.217049);
    $objService->paginate($objService->findNearbyListings($fltLatitude, $fltLongitude, 10));

    expect($fltLatitude)->toBe(6.955);
    expect($fltLongitude)->toBe(126.217);
    expect($arrStatements)->not->toBeEmpty();

    foreach ($arrStatements as $strStatement) {
        expect(strtolower(ltrim($strStatement)))->toStartWith('select');
    } // end foreach statement

    expect($arrLogMessages)->toBeEmpty();
});
