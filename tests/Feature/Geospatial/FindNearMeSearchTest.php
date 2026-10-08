<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Objective 3, Phase 6 — Find Near Me searches through the real
 * endpoint and the PostgreSQL Haversine (NearbySearchService). Runs only on
 * the dedicated disposable database itour_testing (group "geospatial").
 *
 *   DB_CONNECTION=pgsql DB_DATABASE=itour_testing php artisan test --group=geospatial
 *
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Models\Category;
use App\Models\Listing;
use Database\Seeders\CategorySeeder;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

pest()->group('geospatial');

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Geospatial tests need PostgreSQL: DB_CONNECTION=pgsql DB_DATABASE=itour_testing php artisan test --group=geospatial');
    }

    test()->seed(CategorySeeder::class);
});

/**
 * A listing at a fixed fixture point (not a real place) on latitude 6.96.
 * Defaults to a published destination.
 *
 * @param  array<string, mixed>  $arrOverrides
 */
function nearMeListing(string $strName, float $fltLongitude, array $arrOverrides = []): Listing
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
        'lst_lat' => 6.9600,
        'lst_lng' => $fltLongitude,
        'lst_status' => $objCategory->isDestinationCategory() ? 'Active' : 'PUBLISHED',
    ], $arrOverrides));
}

/** The visitor's position for these tests, with extra precision (not a real visitor). */
function nearMePayload(array $arrExtra = []): array
{
    return ['latitude' => 6.955049, 'longitude' => 126.217049, ...$arrExtra];
}

test('Find Near Me returns public listings nearest first with server distance labels, and never the visitor location', function () {
    nearMeListing('Near Beach', 126.2200);
    nearMeListing('Near Resort', 126.2300, ['category_name' => 'Accommodation', 'lst_owner_name' => 'Secret Owner']);
    nearMeListing('Draft Diner', 126.2190, ['category_name' => 'Food & Dining', 'lst_status' => 'DRAFT']);
    nearMeListing('Archived Falls', 126.2180, ['lst_status' => 'Archived']);
    nearMeListing('Far Falls', 126.6900);

    $objResponse = $this->postJson(route('findNearMe'), nearMePayload());

    $objResponse->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('radiusKm', 10)
        ->assertJsonPath('total', 2)
        ->assertJsonPath('results.0.name', 'Near Beach')
        ->assertJsonPath('results.1.name', 'Near Resort');

    $arrFirst = $objResponse->json('results.0');
    expect(array_keys($arrFirst))->toBe(['type', 'slug', 'name', 'category', 'subtype', 'municipality', 'barangay', 'latitude', 'longitude', 'distanceMeters', 'distanceLabel', 'imageUrl', 'url']);
    expect($arrFirst['distanceLabel'])->toEndWith('m away');

    // Summary comment: neither the exact nor the rounded visitor position comes back, nor any private field.
    $strBody = $objResponse->getContent();
    expect($strBody)->not->toContain('6.955')->not->toContain('126.217')->not->toContain('Secret Owner')->not->toContain('uuid')->not->toContain('lst_id')->not->toContain('PUBLISHED');
});

test('the search uses the server-rounded position (4 decimal places)', function () {
    // Summary comment: a listing exactly at the rounded point is 0 m away; from the
    // unrounded position it would be about 7 m away.
    nearMeListing('Exact Spot', 126.2170, ['lst_lat' => 6.9550]);

    $this->postJson(route('findNearMe'), nearMePayload())
        ->assertOk()
        ->assertJsonPath('results.0.name', 'Exact Spot')
        ->assertJsonPath('results.0.distanceMeters', 0);
});

test('a Find Near Me search is read-only and leaves no location in logs or the session', function () {
    nearMeListing('Near Beach', 126.2200);
    $arrStatements = [];
    $arrLogLines = [];
    DB::listen(function ($objQuery) use (&$arrStatements) {
        $arrStatements[] = strtolower(ltrim($objQuery->sql));
    });
    Event::listen(MessageLogged::class, function (MessageLogged $objMessage) use (&$arrLogLines) {
        $arrLogLines[] = $objMessage->message;
    });

    $this->postJson(route('findNearMe'), nearMePayload())->assertOk();

    expect($arrStatements)->not->toBeEmpty();

    foreach ($arrStatements as $strStatement) {
        expect($strStatement)->toStartWith('select');
    } // end foreach statement

    expect($arrLogLines)->toBe([]);
    expect(json_encode(session()->all()))->not->toContain('6.955')->not->toContain('126.217');
});

test('radius, category, and pagination are applied on the server', function () {
    foreach (range(1, 25) as $intIndex) {
        nearMeListing(sprintf('Stay %02d', $intIndex), 126.2170 + $intIndex * 0.001, ['category_name' => 'Accommodation']);
    } // end foreach stay

    nearMeListing('Ten Km Beach', 126.2900);

    // Summary comment: the stays sit 0.005 degrees (about 0.55 km) north of the visitor, so only
    // Stay 01-07 are within 1 km: sqrt(0.553^2 + (0.110 x i)^2) <= 1 for i <= 7.
    $this->postJson(route('findNearMe'), nearMePayload(['radius' => 1]))->assertOk()->assertJsonPath('radiusKm', 1)->assertJsonPath('total', 7)->assertJsonPath('results.6.name', 'Stay 07');
    $this->postJson(route('findNearMe'), nearMePayload(['radius' => 10]))->assertOk()->assertJsonPath('total', 26)->assertJsonCount(20, 'results')->assertJsonPath('lastPage', 2);
    $this->postJson(route('findNearMe'), nearMePayload(['radius' => 10, 'page' => 2]))->assertOk()->assertJsonCount(6, 'results')->assertJsonPath('results.5.name', 'Ten Km Beach');
    $this->postJson(route('findNearMe'), nearMePayload(['radius' => 10, 'category' => 'destinations']))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('category', 'Tourist Destinations');
    $this->postJson(route('findNearMe'), nearMePayload(['radius' => 50]))->assertOk()->assertJsonPath('radiusKm', 50);
});

test('no places in range returns an empty result, not an error', function () {
    nearMeListing('Far Falls', 126.6900);

    $this->postJson(route('findNearMe'), nearMePayload(['radius' => 1]))->assertOk()->assertJsonPath('total', 0)->assertJsonPath('results', []);
});
