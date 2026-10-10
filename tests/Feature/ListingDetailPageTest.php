<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — listing detail page.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

function listingDetailFixture(array $overrides = []): Listing
{
    $category = Category::query()->firstOrCreate(
        ['cat_name' => 'Accommodation'],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );

    return Listing::query()->create(array_merge([
        'lst_slug' => Str::slug('detail-page-fixture-'.Str::random(6)),
        'lst_name' => 'Detail Page Resort',
        'lst_category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'lst_municipality' => 'City of Mati',
        'lst_barangay' => 'Dahican',
        'lst_status' => 'PUBLISHED',
        'lst_image' => 'resort.jpg',
    ], $overrides));
}

test('the detail page shows the published cover image and a gallery of published photos only', function () {
    $listing = listingDetailFixture();
    $uploader = User::factory()->create(['usr_role' => UserRole::Establishment, 'lst_id' => $listing->lst_id]);

    $cover = EstablishmentImage::query()->create([
        'lst_id' => $listing->lst_id,
        'img_path' => 'cover.jpg', 'img_thumbnail_path' => 'cover_thumb.jpg', 'img_alt_text' => 'Pool view',
        'img_credit' => 'Photo by Jane', 'img_source_role' => ImageSourceRole::Establishment,
        'img_status' => ImageStatus::Published, 'img_is_cover' => true, 'img_sort_order' => 1,
        'img_hash' => hash('sha256', uniqid()), 'img_uploaded_by' => $uploader->usr_id, 'img_has_ownership_declared' => true,
    ]);
    EstablishmentImage::query()->create([
        'lst_id' => $listing->lst_id,
        'img_path' => 'pending.jpg', 'img_thumbnail_path' => 'pending_thumb.jpg', 'img_alt_text' => 'Lobby',
        'img_source_role' => ImageSourceRole::Establishment, 'img_status' => ImageStatus::Pending,
        'img_is_cover' => false, 'img_sort_order' => 2, 'img_hash' => hash('sha256', uniqid()),
        'img_uploaded_by' => $uploader->usr_id, 'img_has_ownership_declared' => true,
    ]);

    $response = $this->get(route('listings.show', $listing));

    $response->assertOk();
    $response->assertSee($listing->lst_name);
    $response->assertSee(route('establishmentImages.file', [$cover, 'full']), false);
    $response->assertSee('Photo by Jane');
    $response->assertDontSee('Lobby');
});

test('a listing with no establishment images falls back to its legacy image, never a dead link', function () {
    $listing = listingDetailFixture();

    $response = $this->get(route('listings.show', $listing));

    $response->assertOk();
    $response->assertSee(asset('storage/itour-images/resort.jpg'), false);
});

test('a listing with no image at all shows a category placeholder icon, not a third-party photo', function () {
    $listing = listingDetailFixture(['lst_image' => null]);

    $response = $this->get(route('listings.show', $listing));

    $response->assertOk();
    $response->assertSee('ti-bed', false);
});

test('a suspended listing has no public detail page', function () {
    $listing = listingDetailFixture(['lst_status' => 'Suspended']);

    $this->get(route('listings.show', $listing))->assertNotFound();
});

test('the Explore hub links each listing to its real detail page, not a dead link', function () {
    $listing = listingDetailFixture();

    $response = $this->get(route('explore'));

    $response->assertOk();
    $response->assertSee(route('listings.show', $listing), false);
    $response->assertDontSee('href="#"', false);
});

/*
 * Objective 3, Phase 4 — destination detail page and the Find Nearby page (behavior that needs no
 * SQL distance; the PostgreSQL nearby tests are in tests/Feature/Geospatial).
 */

test('a destination detail page shows its type, visitor information, entrance fee, and managing office — and no private data', function () {
    $destinations = Category::query()->firstOrCreate(['cat_name' => 'Tourist Destinations'], ['cat_sort_order' => 0, 'cat_is_active' => true]);
    $listing = listingDetailFixture([
        'lst_name' => 'Detail Falls',
        'lst_category' => 'destinations',
        'cat_id' => $destinations->cat_id,
        'lst_status' => 'Active',
        'lst_type' => 'Waterfall',
        'lst_visitor_information' => 'Wear water shoes; the stairs are slippery.',
        'lst_entrance_fee' => 'PHP 50 adults',
        'lst_contact_office' => 'Cateel Municipal Tourism Office',
        'lst_owner_name' => 'Hidden Owner',
    ]);

    $response = $this->get(route('listings.show', $listing));

    $response->assertOk()
        ->assertSee('Waterfall')
        ->assertSee('Wear water shoes; the stairs are slippery.')
        ->assertSee('PHP 50 adults')
        ->assertSee('Managing office')
        ->assertSee('Cateel Municipal Tourism Office')
        ->assertSee('<link rel="canonical" href="'.route('listings.show', $listing).'">', false)
        ->assertDontSee('Hidden Owner')
        ->assertDontSee($listing->lst_uuid)
        ->assertDontSee('Active');
});

test('a destination without a map location explains that nearby services cannot be shown', function () {
    $destinations = Category::query()->firstOrCreate(['cat_name' => 'Tourist Destinations'], ['cat_sort_order' => 0, 'cat_is_active' => true]);
    $listing = listingDetailFixture(['lst_category' => 'destinations', 'cat_id' => $destinations->cat_id, 'lst_status' => 'Active', 'lst_lat' => null, 'lst_lng' => null]);

    $this->get(route('listings.show', $listing))->assertOk()->assertSee("map location hasn't been set yet", false)->assertDontSee(route('listings.nearby', $listing), false);
    $this->get(route('listings.nearby', $listing))->assertOk()->assertSee("map location hasn't been set yet", false);
});

test('a failed nearby search shows a friendly message instead of breaking the destination page', function () {
    // SQLite has no trigonometric functions, so the Haversine query fails here — exactly the failure the page must survive.
    $destinations = Category::query()->firstOrCreate(['cat_name' => 'Tourist Destinations'], ['cat_sort_order' => 0, 'cat_is_active' => true]);
    $listing = listingDetailFixture(['lst_category' => 'destinations', 'cat_id' => $destinations->cat_id, 'lst_status' => 'Active', 'lst_lat' => 6.9578, 'lst_lng' => 126.2478]);

    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Only meaningful where the Haversine query cannot run.');
    }

    $this->get(route('listings.show', $listing))->assertOk()->assertSee("Nearby services can't be shown right now", false);
    $this->get(route('listings.nearby', $listing))->assertOk()->assertSee("Nearby services can't be shown right now", false);
});

test('Find Nearby exists only for published destinations', function () {
    $destinations = Category::query()->firstOrCreate(['cat_name' => 'Tourist Destinations'], ['cat_sort_order' => 0, 'cat_is_active' => true]);
    $establishment = listingDetailFixture();
    $draftDestination = listingDetailFixture(['lst_category' => 'destinations', 'cat_id' => $destinations->cat_id, 'lst_status' => 'DRAFT']);
    $archivedDestination = listingDetailFixture(['lst_category' => 'destinations', 'cat_id' => $destinations->cat_id, 'lst_status' => 'Archived']);

    $this->get(route('listings.nearby', $establishment))->assertNotFound();
    $this->get(route('listings.nearby', $draftDestination))->assertNotFound();
    $this->get(route('listings.nearby', $archivedDestination))->assertNotFound();
    $this->get(route('listings.show', $draftDestination))->assertNotFound();
});

test('the public directory pages are rate-limited per visitor', function () {
    expect(RateLimiter::limiter('public-directory'))->not->toBeNull();

    $route = app('router')->getRoutes()->getByName('listings.nearby');
    expect($route->gatherMiddleware())->toContain('throttle:public-directory');
    expect(app('router')->getRoutes()->getByName('explore')->gatherMiddleware())->toContain('throttle:public-directory');
});

/*
 * Objective 3, Phase 5 — the destination detail map and the management location picker.
 */

test('a destination with a valid location gets a map of its stored coordinates and public data only', function () {
    $destinations = Category::query()->firstOrCreate(['cat_name' => 'Tourist Destinations'], ['cat_sort_order' => 0, 'cat_is_active' => true]);
    $listing = listingDetailFixture(['lst_name' => 'Mapped Falls', 'lst_category' => 'destinations', 'cat_id' => $destinations->cat_id, 'lst_status' => 'Active', 'lst_lat' => 7.7947, 'lst_lng' => 126.355, 'lst_owner_name' => 'Hidden Owner']);
    config(['services.mapbox.token' => 'pk.test-public-value']);

    $response = $this->get(route('listings.show', $listing));
    preg_match('#<script type="application/json" id="listing-map-data">(.*?)</script>#s', $response->getContent(), $match);
    $mapData = json_decode($match[1] ?? '{}', true);

    $response->assertOk()
        ->assertSee('id="listing-map"', false)
        ->assertSee('data-mapbox-token="pk.test-public-value"', false)
        ->assertSee('This destination')
        ->assertSee('data-map-fallback', false);
    expect($mapData['reference'])->toBe(['name' => 'Mapped Falls', 'lat' => 7.7947, 'lng' => 126.355]);
    expect(array_keys($mapData))->toBe(['reference', 'places']);
    expect($match[1])->not->toContain('Hidden Owner')->not->toContain($listing->lst_uuid);
});

test('a destination without a valid location shows no map and no fabricated point', function () {
    $destinations = Category::query()->firstOrCreate(['cat_name' => 'Tourist Destinations'], ['cat_sort_order' => 0, 'cat_is_active' => true]);
    $missing = listingDetailFixture(['lst_category' => 'destinations', 'cat_id' => $destinations->cat_id, 'lst_status' => 'Active', 'lst_lat' => null, 'lst_lng' => null]);
    $invalid = listingDetailFixture(['lst_category' => 'destinations', 'cat_id' => $destinations->cat_id, 'lst_status' => 'Active', 'lst_lat' => 95.0, 'lst_lng' => 126.355]);

    foreach ([$missing, $invalid] as $listing) {
        $this->get(route('listings.show', $listing))
            ->assertOk()
            ->assertSee('Map location not set yet.')
            ->assertDontSee('id="listing-map"', false)
            ->assertDontSee('listing-map-data');
    } // end foreach destination without a usable location
});

test('establishment detail pages keep no map (destinations only in Phase 5)', function () {
    $listing = listingDetailFixture(['lst_lat' => 6.96, 'lst_lng' => 126.25]);

    $this->get(route('listings.show', $listing))->assertOk()->assertDontSee('id="listing-map"', false);
});

test('the LGU and PTO forms render the location picker with the configured bounds, locked under review', function () {
    $mati = Municipality::query()->create(['mun_name' => 'City of Mati', 'mun_code' => 'MATI']);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'usr_organization_subtitle' => 'City of Mati', 'mun_id' => $mati->mun_id]);
    $destinations = Category::query()->firstOrCreate(['cat_name' => 'Tourist Destinations'], ['cat_sort_order' => 0, 'cat_is_active' => true]);
    $underReview = listingDetailFixture(['lst_category' => 'destinations', 'cat_id' => $destinations->cat_id, 'lst_status' => 'FOR_PTO_REVIEW', 'mun_id' => $mati->mun_id]);
    $draft = listingDetailFixture(['lst_category' => 'destinations', 'cat_id' => $destinations->cat_id, 'lst_status' => 'DRAFT', 'mun_id' => $mati->mun_id]);
    $bounds = config('tourism_directory.coordinate_bounds');

    $this->actingAs($lgu)->get(route('lgu.directory.attractions.create'))
        ->assertOk()
        ->assertSee('data-location-picker', false)
        ->assertSee('data-latitude-input="attraction-lat"', false)
        ->assertSee('data-min-latitude="'.$bounds['min_latitude'].'"', false)
        ->assertSee('data-max-longitude="'.$bounds['max_longitude'].'"', false)
        ->assertSee('data-disabled="false"', false);

    $this->actingAs($lgu)->get(route('lgu.directory.attractions.edit', $draft))->assertOk()->assertSee('data-disabled="false"', false);
    $this->actingAs($lgu)->get(route('lgu.directory.attractions.edit', $underReview))->assertOk()->assertSee('data-disabled="true"', false);

    // Summary comment: the PTO directory loads Mapbox GL once even with both the tourism map and the picker on the page.
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $content = $this->actingAs($pto)->get(route('pto.directory.index'))->assertOk()->assertSee('data-latitude-input="listing-form-lat"', false)->getContent();
    expect(substr_count($content, 'mapbox-gl-js/v3.7.0/mapbox-gl.js'))->toBe(1);
});

test('the picker never bypasses server validation: a point outside Davao Oriental is still rejected', function () {
    $mati = Municipality::query()->create(['mun_name' => 'City of Mati', 'mun_code' => 'MATI']);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'usr_organization_subtitle' => 'City of Mati', 'mun_id' => $mati->mun_id]);

    $this->actingAs($lgu)->post(route('lgu.directory.attractions.store'), ['name' => 'Picked Too Far', 'barangay' => 'Nowhere', 'lat' => '14.599512', 'lng' => '120.984222'])
        ->assertSessionHasErrors(['lat', 'lng']);
    expect(Listing::query()->where('lst_name', 'Picked Too Far')->exists())->toBeFalse();
});
