<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Objective 3, Phase 4 — the public pages that use the PostgreSQL
 * Haversine nearby search: a destination's grouped Nearby Tourism Services
 * and its Find Nearby list. Runs only on the dedicated disposable database
 * itour_testing (group "geospatial"); skipped on SQLite.
 *
 *   DB_CONNECTION=pgsql DB_DATABASE=itour_testing php artisan test --group=geospatial
 *
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Models\Category;
use App\Models\Listing;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

pest()->group('geospatial');

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Geospatial tests need PostgreSQL: DB_CONNECTION=pgsql DB_DATABASE=itour_testing php artisan test --group=geospatial');
    }

    test()->seed(CategorySeeder::class);
});

/**
 * A listing at a fixed fixture point (not a real place) on latitude 6.955.
 * Defaults to a published destination.
 *
 * @param  array<string, mixed>  $arrOverrides
 */
function pageListing(string $strName, float $fltLongitudeOffset, array $arrOverrides = []): Listing
{
    $strCategoryName = $arrOverrides['category_name'] ?? 'Tourist Destinations';
    unset($arrOverrides['category_name']);
    $objCategory = Category::query()->where('cat_name', $strCategoryName)->firstOrFail();

    return Listing::query()->create(array_merge([
        'lst_slug' => Str::slug($strName.'-'.Str::random(6)),
        'lst_name' => $strName,
        'lst_category' => $objCategory->legacySlug(),
        'cat_id' => $objCategory->cat_id,
        'lst_municipality' => 'City of Mati',
        'lst_barangay' => 'Poblacion',
        'lst_lat' => 6.9550,
        'lst_lng' => 126.2170 + $fltLongitudeOffset,
        'lst_status' => $objCategory->isDestinationCategory() ? 'Active' : 'PUBLISHED',
    ], $arrOverrides));
}

test('the destination page groups nearby services by category, at most 5 each, nearest first', function () {
    $objReference = pageListing('Reference Falls', 0, ['lst_type' => 'Waterfall']);

    foreach (range(1, 7) as $intIndex) {
        pageListing("Resort {$intIndex}", $intIndex * 0.002, ['category_name' => 'Accommodation']);
    } // end foreach resort

    pageListing('Beach One', 0.001);
    pageListing('Diner One', 0.003, ['category_name' => 'Food & Dining']);
    pageListing('Hidden Draft Diner', 0.0005, ['category_name' => 'Food & Dining', 'lst_status' => 'DRAFT']);
    pageListing('Far Beach', 0.5);

    $objResponse = $this->get(route('listings.show', $objReference));

    $objResponse->assertOk()
        ->assertSee('Nearby Tourism Services')
        ->assertSee('Other Nearby Destinations')
        ->assertSeeInOrder(['Resort 1', 'Resort 2', 'Resort 3', 'Resort 4', 'Resort 5'])
        ->assertDontSee('Resort 6')
        ->assertDontSee('Resort 7')
        ->assertSee('Beach One')
        ->assertSee('Diner One')
        ->assertSee('110 m away')
        ->assertDontSee('Hidden Draft Diner')
        ->assertDontSee('Far Beach')
        ->assertSee(route('listings.nearby', [$objReference, 'category' => 'accommodation']), false);

    // Summary comment: the reference destination never lists itself — its exact
    // URL appears once, in the page's own canonical tag.
    expect(substr_count($objResponse->getContent(), 'href="'.route('listings.show', $objReference).'"'))->toBe(1);
});

test('a destination with no services in range shows the empty state with a wider search', function () {
    $objReference = pageListing('Lonely Falls', 0);
    pageListing('Far Beach', 0.5);

    $this->get(route('listings.show', $objReference))
        ->assertOk()
        ->assertSee('No tourism services were found within 10 km.')
        ->assertSee(route('listings.nearby', [$objReference, 'radius' => 50]), false);
});

test('Find Nearby filters by radius and category, paginates at 20, and keeps private data out', function () {
    $objReference = pageListing('Reference Falls', 0);

    foreach (range(1, 25) as $intIndex) {
        pageListing(sprintf('Stay %02d', $intIndex), $intIndex * 0.001, ['category_name' => 'Accommodation', 'lst_owner_name' => 'Secret Owner']);
    } // end foreach stay

    pageListing('Ten Km Beach', 0.08);

    // Summary comment: 1 km keeps only the stays within 1 km (offsets up to 0.009 degrees).
    $this->get(route('listings.nearby', [$objReference, 'radius' => 1]))
        ->assertOk()
        ->assertSee('9 places within 1 km')
        ->assertSee('Stay 09')
        ->assertDontSee('Stay 10');

    $objFirstPage = $this->get(route('listings.nearby', [$objReference, 'radius' => 10]));
    $objFirstPage->assertOk()->assertSee('26 places within 10 km')->assertSee('Stay 20')->assertDontSee('Stay 21')->assertSee('Showing 1–20 of 26');
    $objFirstPage->assertDontSee('Secret Owner');

    $this->get(route('listings.nearby', [$objReference, 'radius' => 10, 'page' => 2]))
        ->assertOk()
        ->assertSee('Stay 25')
        ->assertSee('Ten Km Beach')
        ->assertSee('Showing 21–26 of 26');

    $this->get(route('listings.nearby', [$objReference, 'radius' => 10, 'category' => 'destinations']))
        ->assertOk()
        ->assertSee('1 place within 10 km · Destinations')
        ->assertSee('Ten Km Beach')
        ->assertDontSee('Stay 01');

    // Summary comment: an unknown radius falls back to the 10 km default instead of erroring.
    $this->get(route('listings.nearby', [$objReference, 'radius' => 999]))->assertOk()->assertSee('26 places within 10 km');
});

test('Find Nearby shows the empty state with a wider radius, all categories, and the directory', function () {
    $objReference = pageListing('Reference Falls', 0);
    pageListing('Twenty Km Stay', 0.18, ['category_name' => 'Accommodation']);

    $this->get(route('listings.nearby', [$objReference, 'radius' => 5, 'category' => 'accommodation']))
        ->assertOk()
        ->assertSee('No tourism services were found within the selected radius.')
        ->assertSee('Search within 10 km')
        ->assertSee('Show all categories')
        ->assertSee('Browse the full directory');
});

test('the destination map carries the reference point and the server-found nearby pins only', function () {
    $objReference = pageListing('Mapped Falls', 0, ['lst_type' => 'Waterfall']);
    pageListing('Map Beach', 0.001);
    pageListing('Map Resort', 0.002, ['category_name' => 'Accommodation', 'lst_owner_name' => 'Secret Owner']);
    pageListing('Map Draft Diner', 0.003, ['category_name' => 'Food & Dining', 'lst_status' => 'DRAFT']);
    pageListing('Map Far Beach', 0.5);

    $objResponse = $this->get(route('listings.show', $objReference))->assertOk()->assertSee('id="listing-map"', false);
    preg_match('#<script type="application/json" id="listing-map-data">(.*?)</script>#s', $objResponse->getContent(), $arrMatch);
    $arrMapData = json_decode($arrMatch[1], true);

    expect($arrMapData['reference'])->toBe(['name' => 'Mapped Falls', 'lat' => 6.955, 'lng' => 126.217]);
    expect(array_column($arrMapData['places'], 'name'))->toEqualCanonicalizing(['Map Beach', 'Map Resort']);
    expect(array_keys($arrMapData['places'][0]))->toBe(['slug', 'name', 'kind', 'categoryLabel', 'distanceLabel', 'lat', 'lng', 'href']);

    $arrByName = collect($arrMapData['places'])->keyBy('name');
    expect($arrByName['Map Beach']['kind'])->toBe('destination');
    expect($arrByName['Map Resort']['kind'])->toBe('establishment');
    expect($arrByName['Map Resort']['distanceLabel'])->toBe('220 m away');
    expect($arrByName['Map Resort']['href'])->toBe(route('listings.show', $arrByName['Map Resort']['slug']));
    expect($arrMatch[1])->not->toContain('Secret Owner')->not->toContain('lst_id')->not->toContain('uuid');

    // Summary comment: every pin has its "Show on map" control in the nearby list.
    foreach ($arrMapData['places'] as $arrPlace) {
        $objResponse->assertSee('data-map-focus="'.$arrPlace['slug'].'"', false);
    } // end foreach pin
});

test('the destination page, Find Nearby, and Find Near Me run a fixed number of queries however many results they show (no N+1)', function () {
    $objReference = pageListing('Query Falls', 0);
    $fnQueryCount = function (Closure $fnRequest): int {
        $intQueries = 0;
        DB::listen(function () use (&$intQueries) {
            $intQueries++;
        });
        $fnRequest();

        return $intQueries;
    };
    $fnAllPages = fn () => [
        $fnQueryCount(fn () => $this->get(route('listings.show', $objReference))->assertOk()),
        $fnQueryCount(fn () => $this->get(route('listings.nearby', $objReference))->assertOk()),
        $fnQueryCount(fn () => $this->postJson(route('findNearMe'), ['latitude' => 6.9551, 'longitude' => 126.2171])->assertOk()),
    ];

    pageListing('Few Resort', 0.001, ['category_name' => 'Accommodation']);
    pageListing('Few Beach', 0.002);
    $arrFew = $fnAllPages();

    foreach (range(1, 12) as $intIndex) {
        pageListing("Many {$intIndex}", 0.002 + $intIndex * 0.001, ['category_name' => $intIndex % 2 ? 'Food & Dining' : 'Accommodation']);
    } // end foreach extra listing
    $arrMany = $fnAllPages();

    expect($arrMany)->toBe($arrFew);
});
