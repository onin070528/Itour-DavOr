<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Objective 3, Phase 6 — Find Near Me request rules, privacy, rate
 * limiting, and destination-only directions (the parts that need no SQL
 * distance; the PostgreSQL searches are in tests/Feature/Geospatial).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Models\Category;
use App\Models\Listing;
use App\Support\DirectionsLink;
use Database\Seeders\CategorySeeder;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/** A tourist position inside Davao Oriental with extra precision (not a real visitor). */
const NEAR_ME_LATITUDE = 6.955049;
const NEAR_ME_LONGITUDE = 126.217049;

/**
 * Every SQL statement that touches tbl_listings while $fnAction runs.
 *
 * @return array<int, string>
 */
function listingQueriesDuring(Closure $fnAction): array
{
    $arrStatements = [];
    DB::listen(function ($objQuery) use (&$arrStatements) {
        if (str_contains($objQuery->sql, 'tbl_listings')) {
            $arrStatements[] = $objQuery->sql;
        }
    });

    $fnAction();

    return $arrStatements;
}

test('Find Near Me accepts POST only — never a location in a GET URL', function () {
    $this->get('/find-near-me?latitude=6.955&longitude=126.217')->assertMethodNotAllowed();

    $objRoute = app('router')->getRoutes()->getByName('findNearMe');
    expect($objRoute->methods())->toBe(['POST']);
    expect($objRoute->gatherMiddleware())->toContain('throttle:find-near-me');
});

test('invalid coordinates are rejected before any listing search runs', function () {
    test()->seed(CategorySeeder::class);

    foreach ([[], ['latitude' => 'north', 'longitude' => 126.2], ['latitude' => 95, 'longitude' => 126.2], ['latitude' => 6.9, 'longitude' => 190], ['latitude' => 6.9]] as $arrPayload) {
        $arrQueries = listingQueriesDuring(fn () => $this->postJson(route('findNearMe'), $arrPayload)->assertUnprocessable()->assertJsonStructure(['message', 'errors']));
        expect($arrQueries)->toBe([]);
    } // end foreach invalid payload
});

test('a location outside Davao Oriental is rejected cleanly, with no search', function () {
    test()->seed(CategorySeeder::class);

    $arrQueries = listingQueriesDuring(function () {
        $this->postJson(route('findNearMe'), ['latitude' => 14.599512, 'longitude' => 120.984222])
            ->assertUnprocessable()
            ->assertJsonPath('errors.location.0', 'You appear to be outside Davao Oriental, so there are no nearby places to show. You can still browse the full directory.')
            ->assertDontSee('14.599512')
            ->assertDontSee('120.984222');
    });

    expect($arrQueries)->toBe([]);
});

test('only the configured radii are accepted — nothing above the 50 km maximum', function () {
    test()->seed(CategorySeeder::class);

    $this->postJson(route('findNearMe'), ['latitude' => NEAR_ME_LATITUDE, 'longitude' => NEAR_ME_LONGITUDE, 'radius' => 999])->assertUnprocessable()->assertJsonValidationErrors('radius');
    $this->postJson(route('findNearMe'), ['latitude' => NEAR_ME_LATITUDE, 'longitude' => NEAR_ME_LONGITUDE, 'radius' => 51])->assertUnprocessable()->assertJsonValidationErrors('radius');
    $this->postJson(route('findNearMe'), ['latitude' => NEAR_ME_LATITUDE, 'longitude' => NEAR_ME_LONGITUDE, 'category' => 'casinos'])->assertUnprocessable()->assertJsonValidationErrors('category');

    foreach (config('tourism_directory.nearby.radius_options_km') as $intRadius) {
        $this->postJson(route('findNearMe'), ['latitude' => NEAR_ME_LATITUDE, 'longitude' => NEAR_ME_LONGITUDE, 'radius' => $intRadius])->assertJsonMissingValidationErrors('radius');
    } // end foreach configured radius
});

test('a failed request never redirects with the location flashed into the session', function () {
    test()->seed(CategorySeeder::class);

    // Summary comment: even a plain (non-JSON) form post answers with JSON, so nothing becomes "old input".
    $objResponse = $this->post(route('findNearMe'), ['latitude' => 14.599512, 'longitude' => 120.984222]);

    $objResponse->assertUnprocessable()->assertHeader('Content-Type', 'application/json');
    expect(json_encode(session()->all()))->not->toContain('14.599512')->not->toContain('120.984222');
    expect(session()->getOldInput())->toBe([]);
});

test('a search failure shows a safe message and logs no coordinates, SQL, or details', function () {
    // SQLite has no trigonometric functions, so the Haversine query fails here — the failure path under test.
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Only meaningful where the Haversine query cannot run.');
    }

    test()->seed(CategorySeeder::class);
    $arrLogLines = [];
    Event::listen(MessageLogged::class, function (MessageLogged $objMessage) use (&$arrLogLines) {
        $arrLogLines[] = $objMessage->message.' '.json_encode($objMessage->context);
    });

    $objResponse = $this->postJson(route('findNearMe'), ['latitude' => NEAR_ME_LATITUDE, 'longitude' => NEAR_ME_LONGITUDE]);

    $objResponse->assertStatus(503)->assertExactJson(['message' => "Nearby places can't be shown right now. Please try again later."]);
    $objResponse->assertHeader('Cache-Control', 'no-store, private');
    expect($arrLogLines)->toHaveCount(1);
    expect($arrLogLines[0])->toContain('Find Near Me search failed.')->not->toContain('6.955')->not->toContain('126.217')->not->toContain('select');
    expect(json_encode(session()->all()))->not->toContain('6.955')->not->toContain('126.217');
});

test('Find Near Me is rate-limited per visitor with its own configurable limit', function () {
    test()->seed(CategorySeeder::class);
    config(['tourism_directory.nearby.find_near_me_per_minute' => 2]);

    $this->postJson(route('findNearMe'), ['latitude' => 95, 'longitude' => 1])->assertUnprocessable();
    $this->postJson(route('findNearMe'), ['latitude' => 95, 'longitude' => 1])->assertUnprocessable();
    $this->postJson(route('findNearMe'), ['latitude' => 95, 'longitude' => 1])->assertTooManyRequests();

    expect(config('tourism_directory.nearby.find_near_me_per_minute'))->toBe(2);
});

test('directions links carry the destination only — no origin, visitor location, or internal values', function () {
    $strUrl = DirectionsLink::toDestination(7.7947, 126.355);

    expect($strUrl)->toBe('https://www.google.com/maps/dir/?api=1&destination=7.794700%2C126.355000');
    expect($strUrl)->not->toContain('origin')->not->toContain(substr((string) NEAR_ME_LATITUDE, 0, 5));
    expect(DirectionsLink::toDestination(null, 126.355))->toBeNull();
    expect(DirectionsLink::toDestination(95.0, 126.355))->toBeNull();
});

test('the destination page and the landing modal use destination-only directions links', function () {
    test()->seed(CategorySeeder::class);
    $objCategory = Category::query()->where('cat_name', 'Tourist Destinations')->firstOrFail();
    $objListing = Listing::query()->create([
        'lst_slug' => Str::slug('Directions Falls '.Str::random(6)),
        'lst_name' => 'Directions Falls',
        'lst_category' => 'destinations',
        'cat_id' => $objCategory->cat_id,
        'lst_municipality' => 'Cateel',
        'lst_barangay' => 'Aliwagwag',
        'lst_lat' => 7.7947,
        'lst_lng' => 126.355,
        'lst_status' => 'Active',
        'lst_owner_name' => 'Owner Person',
    ]);
    $strExpected = DirectionsLink::forListing($objListing);

    $this->get(route('listings.show', $objListing))->assertOk()->assertSee('href="'.e($strExpected).'"', false)->assertSee('Get Directions');

    $objLanding = $this->get(route('home'))->assertOk();
    preg_match('#<script type="application/json" id="listing-details-data">(.*?)</script>#s', $objLanding->getContent(), $arrMatch);
    $arrModalListing = json_decode($arrMatch[1], true)[$objListing->lst_slug];

    expect($arrModalListing['directionsUrl'])->toBe($strExpected);
    // Summary comment: the only query parameters are api and the destination coordinates — no id, uuid, owner, or origin.
    parse_str((string) parse_url($arrModalListing['directionsUrl'], PHP_URL_QUERY), $arrQuery);
    expect($arrQuery)->toBe(['api' => '1', 'destination' => '7.794700,126.355000']);
    expect($arrModalListing['directionsUrl'])->not->toContain($objListing->lst_uuid)->not->toContain('Owner');
});

test('Explore offers Find Near Me with the privacy notice and configured radii', function () {
    test()->seed(CategorySeeder::class);

    $objResponse = $this->get(route('explore'))->assertOk()
        ->assertSee('data-find-near-me', false)
        ->assertSee('data-endpoint="/find-near-me"', false)
        ->assertSee('iTOUR uses your current location only to identify nearby tourism destinations and services. Your exact location is not permanently stored.')
        ->assertSee('Allow Location')
        ->assertSee('Not Now')
        ->assertSee('<option value="10" selected>10 km</option>', false);

    foreach (config('tourism_directory.nearby.radius_options_km') as $intRadius) {
        $objResponse->assertSee('<option value="'.$intRadius.'"', false);
    } // end foreach configured radius
});

test('the Nearby page has the map, location search, category pills, configured radii, and the privacy notice', function () {
    test()->seed(CategorySeeder::class);

    $objResponse = $this->get(route('nearby'))->assertOk()
        ->assertSee('Search a location, choose a category, and see the closest tourism services.')
        ->assertSee('id="nearby-map"', false)
        ->assertSee('Use my location')
        ->assertSee('data-nearby-category=""', false)
        ->assertSee('data-nearby-category="accommodation"', false)
        ->assertSee('data-endpoint="/find-near-me"', false)
        ->assertSee('Your exact location is not permanently stored.')
        ->assertSee('<option value="10" selected>10 km</option>', false);

    foreach (config('tourism_directory.nearby.radius_options_km') as $intRadius) {
        $objResponse->assertSee('<option value="'.$intRadius.'"', false);
    } // end foreach configured radius

    // Summary comment: the map section moved off the landing page, which keeps its other sections.
    $this->get(route('home'))->assertOk()->assertDontSee('id="nearby-map"', false)->assertSee('Signature experiences of Davao Oriental');
});

test('the topbar links to the Nearby page with the new labels', function () {
    $objResponse = $this->get(route('home'))->assertOk()
        ->assertSeeInOrder(['>Home<', '>Explore<', '>Find Nearby<', '>Emergency Hotlines<'], false)
        ->assertSee('href="'.route('nearby').'"', false);

    expect($objResponse->getContent())->not->toContain('#near-you');
});

test('the Nearby page exposes only public place fields, with server-built destination-only directions', function () {
    test()->seed(CategorySeeder::class);
    $objCategory = Category::query()->where('cat_name', 'Tourist Destinations')->firstOrFail();
    $objListing = Listing::query()->create([
        'lst_slug' => Str::slug('Nearby Falls '.Str::random(6)),
        'lst_name' => 'Nearby Falls',
        'lst_category' => 'destinations',
        'cat_id' => $objCategory->cat_id,
        'lst_municipality' => 'Cateel',
        'lst_barangay' => 'Aliwagwag',
        'lst_lat' => 7.7947,
        'lst_lng' => 126.355,
        'lst_status' => 'Active',
        'lst_owner_name' => 'Owner Person',
    ]);

    $strHtml = $this->get(route('nearby'))->assertOk()->getContent();
    preg_match('#<script type="application/json" id="nearby-map-data">(.*?)</script>#s', $strHtml, $arrMatch);
    $arrPlace = collect(json_decode($arrMatch[1], true))->firstWhere('slug', $objListing->lst_slug);

    expect($arrPlace['directionsUrl'])->toBe(DirectionsLink::toDestination(7.7947, 126.355));
    expect(array_keys($arrPlace))->not->toContain('lst_uuid')->not->toContain('status')->not->toContain('email');
    expect($strHtml)->not->toContain($objListing->lst_uuid);
});

test('the browser code keeps no location, calculates no distance, and calls no directions service', function () {
    $strApp = file_get_contents(resource_path('js/app.js'));
    $strFindNearMe = file_get_contents(resource_path('js/find_near_me.js'));

    // Summary comment: the old browser Haversine and the Mapbox Directions call are gone from app.js.
    expect($strApp)->not->toContain('haversineDistanceKm')->not->toContain('directions/v5')->not->toContain('navigator.geolocation');

    // Summary comment: Find Near Me asks once, posts once, and never stores or tracks the location.
    expect($strFindNearMe)->toContain('getCurrentPosition')->toContain("method: 'POST'")
        ->not->toContain('watchPosition')->not->toContain('api.mapbox.com')
        ->not->toMatch('/localStorage\s*\./')->not->toMatch('/sessionStorage\s*\./')
        ->not->toMatch('/indexedDB\s*\./')->not->toMatch('/document\.cookie\s*=/');
});
