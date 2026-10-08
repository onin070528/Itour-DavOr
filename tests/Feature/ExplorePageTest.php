<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — explore page.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use App\Support\TextSummary;
use App\Support\TourismCatalog;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

test('the Explore page shows the new establishment category chips', function () {
    $response = $this->get(route('explore'));

    $response->assertOk();
    $response->assertSee('All');
    $response->assertSee('Tourist Destinations');
    $response->assertSee('Accommodation');
    $response->assertSee('Food &amp; Dining', false);
    $response->assertSee('Farm &amp; Agri-Tourism', false);
    $response->assertSee('Wellness &amp; Spa', false);
    $response->assertSee('Travel &amp; Tours', false);
    $response->assertSee('Tourist Transport');
    $response->assertSee('Recreation &amp; Activities', false);
    $response->assertSee('MICE &amp; Events', false);
    $response->assertSee('Others');
});

test('the Explore page no longer shows the old category chip labels', function () {
    $response = $this->get(route('explore'));

    $response->assertOk();
    $response->assertDontSee('Restaurants');
    $response->assertDontSee('Transportation');
    $response->assertDontSee('Tour Guides');
    $response->assertDontSee('Local Delicacies');
});

test('the new category chips keep the existing category slugs so filtering data is unchanged', function () {
    $response = $this->get(route('explore'));

    $response->assertOk();
    $response->assertSee('data-category-chip="destinations"', false);
    $response->assertSee('data-category-chip="accommodation"', false);
    $response->assertSee('data-category-chip="restaurants"', false);
    $response->assertSee('data-category-chip="transportation"', false);
    $response->assertSee('data-category-chip="tour-guides"', false);
    $response->assertSee('data-category-chip="local-delicacies"', false);
});

test('establishment registration still uses the original, unrenamed category list', function () {
    // Lgu\UsersController::establishmentCategories() filters
    // TourismCatalog::categories() — must stay untouched by the Explore-only
    // relabeling in exploreCategories().
    expect(collect(TourismCatalog::categories())->pluck('label')->all())
        ->toBe(['Tourist Destinations', 'Accommodation', 'Restaurants', 'Transportation', 'Tour Guides', 'Local Delicacies']);
});

/*
 * Objective 3, Phase 4 — the unified Tourism Directory: server-side search, filters, pagination,
 * public visibility, and empty states.
 */

/**
 * A listing for the directory tests: a published destination unless overridden.
 *
 * @param  array<string, mixed>  $arrOverrides
 */
function directoryListing(string $strName, array $arrOverrides = []): Listing
{
    test()->seed(CategorySeeder::class);
    $strCategoryName = $arrOverrides['category_name'] ?? 'Tourist Destinations';
    unset($arrOverrides['category_name']);
    $objCategory = Category::query()->where('cat_name', $strCategoryName)->firstOrFail();

    return Listing::query()->create(array_merge([
        'lst_slug' => Str::slug($strName.'-'.Str::random(6)),
        'lst_name' => $strName,
        'lst_category' => $objCategory->legacySlug(),
        'cat_id' => $objCategory->cat_id,
        'lst_municipality' => 'City of Mati',
        'lst_barangay' => 'Dahican',
        'lst_description' => "About {$strName}.",
        'lst_status' => $objCategory->isDestinationCategory() ? 'Active' : 'PUBLISHED',
    ], $arrOverrides));
}

test('only published destinations and establishments appear in the directory', function () {
    directoryListing('Published Falls');
    directoryListing('Published Resort', ['category_name' => 'Accommodation', 'lst_type' => 'Resort']);

    foreach (['DRAFT', 'FOR_PTO_REVIEW', Listing::STATUS_FOR_CORRECTION, 'Suspended', 'Archived'] as $strStatus) {
        directoryListing("Hidden Destination {$strStatus}", ['lst_status' => $strStatus]);
    } // end foreach hidden destination status

    foreach (['DRAFT', 'FOR_LGU_REVIEW', 'FOR_PTO_REVIEW', Listing::STATUS_FOR_CORRECTION, 'UNPUBLISHED', 'Suspended', 'Archived'] as $strStatus) {
        directoryListing("Hidden Establishment {$strStatus}", ['category_name' => 'Accommodation', 'lst_status' => $strStatus]);
    } // end foreach hidden establishment status

    $objResponse = $this->get(route('explore'));

    $objResponse->assertOk()->assertSee('Published Falls')->assertSee('Published Resort')->assertDontSee('Hidden Destination')->assertDontSee('Hidden Establishment');
    $objResponse->assertSee('2 verified listings');
});

test('keyword search matches name, municipality, category, and type, ignoring case and LIKE wildcards', function () {
    directoryListing('Sleeping Dinosaur Island');
    directoryListing('Aliwagwag Falls', ['lst_municipality' => 'Cateel']);
    directoryListing('Seaside Stay', ['category_name' => 'Accommodation', 'lst_type' => 'Resort']);

    $this->get(route('explore', ['q' => 'sleeping dino']))->assertSee('Sleeping Dinosaur Island')->assertDontSee('Aliwagwag Falls');
    $this->get(route('explore', ['q' => 'CATEEL']))->assertSee('Aliwagwag Falls')->assertDontSee('Sleeping Dinosaur Island');
    $this->get(route('explore', ['q' => 'resort']))->assertSee('Seaside Stay')->assertDontSee('Aliwagwag Falls');
    $this->get(route('explore', ['q' => 'accommodation']))->assertSee('Seaside Stay')->assertDontSee('Sleeping Dinosaur Island');
    $this->get(route('explore', ['q' => '%']))->assertSee('No listings match')->assertDontSee('Seaside Stay');
});

test('destination type, category, and municipality filters work from the configured lists', function () {
    directoryListing('Dahican Beach', ['lst_type' => 'Beach']);
    directoryListing('Aliwagwag Falls', ['lst_type' => 'Waterfall', 'lst_municipality' => 'Cateel']);
    directoryListing('Seaside Stay', ['category_name' => 'Accommodation', 'lst_type' => 'Resort']);

    $this->get(route('explore', ['type' => 'Beach']))->assertSee('Dahican Beach')->assertDontSee('Aliwagwag Falls')->assertDontSee('Seaside Stay');
    $this->get(route('explore', ['category' => 'accommodation']))->assertSee('Seaside Stay')->assertDontSee('Dahican Beach');
    $this->get(route('explore', ['municipality' => 'Cateel']))->assertSee('Aliwagwag Falls')->assertDontSee('Dahican Beach');

    // Summary comment: values outside the configured lists are ignored, never errors.
    $this->get(route('explore', ['type' => 'Island']))->assertOk()->assertSee('Dahican Beach')->assertSee('Aliwagwag Falls');
    $this->get(route('explore', ['category' => 'casinos', 'municipality' => 'Atlantis']))->assertOk()->assertSee('Seaside Stay');

    // Summary comment: the destination type options come from config/tourism_directory.php.
    $objResponse = $this->get(route('explore'));
    foreach (config('tourism_directory.destination_types') as $strType) {
        $objResponse->assertSee('<option value="'.$strType.'"', false);
    } // end foreach configured type
    $objResponse->assertDontSee('value="Island"', false)->assertDontSee('value="Viewpoint"', false);
});

test('the directory paginates at the configured page size', function () {
    foreach (range(1, 25) as $intIndex) {
        directoryListing(sprintf('Spot %02d', $intIndex));
    } // end foreach spot

    $this->get(route('explore'))->assertOk()->assertSee('Spot 20')->assertDontSee('Spot 21')->assertSee('Showing 1–20 of 25');
    $this->get(route('explore', ['page' => 2]))->assertOk()->assertSee('Spot 25')->assertDontSee('Spot 05')->assertSee('Showing 21–25 of 25');
    expect(config('tourism_directory.results_per_page'))->toBe(20);
});

test('clear empty states for no results and for filters with no matches', function () {
    directoryListing('Dahican Beach', ['lst_type' => 'Beach']);

    $this->get(route('explore', ['q' => 'volcano']))->assertOk()->assertSee('No listings match “volcano”', false)->assertSee('Browse the full directory');
    $this->get(route('explore', ['type' => 'Cave']))->assertOk()->assertSee('No listings match the selected filters')->assertSee('Browse the full directory');
});

test('the directory never exposes owner, QR, status, or internal id data — in any view', function () {
    $objListing = directoryListing('Private Resort', ['category_name' => 'Accommodation', 'lst_type' => 'Resort', 'lst_owner_name' => 'Juan Owner', 'lst_lat' => 6.9578, 'lst_lng' => 126.2478]);

    foreach (['grid', 'table', 'map'] as $strView) {
        $objResponse = $this->get(route('explore', ['view' => $strView]))->assertOk()->assertSee('Private Resort');
        $objResponse->assertDontSee($objListing->lst_uuid)->assertDontSee('Juan Owner')->assertDontSee('PUBLISHED')->assertDontSee('isPubliclyVisible');
    } // end foreach view

    // Summary comment: the Map view's JSON carries only the current page's public pin fields.
    $this->get(route('explore', ['view' => 'map']))->assertSee('"places":[{"slug":"'.$objListing->lst_slug.'","name":"Private Resort"', false);
});

test('public visitors still cannot reach management pages or actions', function () {
    $objListing = directoryListing('Managed Falls');

    $this->get(route('pto.directory.index'))->assertRedirect(route('login'));
    $this->post(route('pto.directory.store'), ['name' => 'Injected'])->assertRedirect(route('login'));
    $this->put(route('pto.directory.updateStatus', $objListing), ['status' => 'Archived', 'reason' => 'x'])->assertRedirect(route('login'));
    $this->get(route('lgu.directory.attractions.show', $objListing))->assertRedirect(route('login'));
    expect($objListing->fresh()->lst_status)->toBe('Active');
});

/*
 * Objective 3, Phase 5 — the Explore Map view: current-page public pins only, valid coordinates only,
 * slug links, an accessible list beside the map, and the public-token guard.
 */

/**
 * The pins JSON the Explore Map view hands to the browser.
 *
 * @return array<int, array<string, mixed>>
 */
function exploreMapPins(TestResponse $objResponse): array
{
    preg_match('#<script type="application/json" id="explore-data">(.*?)</script>#s', $objResponse->getContent(), $arrMatch);

    return json_decode($arrMatch[1] ?? '{}', true)['places'] ?? [];
}

test('the Map view plots only valid public coordinates, with public fields and slug links only', function () {
    $objBeach = directoryListing('Pinned Beach', ['lst_type' => 'Beach', 'lst_lat' => 6.9578, 'lst_lng' => 126.2478, 'lst_owner_name' => 'Owner Name']);
    $objResort = directoryListing('Pinned Resort', ['category_name' => 'Accommodation', 'lst_type' => 'Resort', 'lst_lat' => 6.9600, 'lst_lng' => 126.2500]);
    directoryListing('No Pin Falls', ['lst_lat' => null, 'lst_lng' => null]);
    directoryListing('Bad Pin Falls', ['lst_lat' => 95.0, 'lst_lng' => 126.2]);
    directoryListing('Draft Pin Falls', ['lst_status' => 'DRAFT', 'lst_lat' => 6.95, 'lst_lng' => 126.24]);

    $objResponse = $this->get(route('explore', ['view' => 'map']));
    $arrPins = exploreMapPins($objResponse);

    $objResponse->assertOk()
        ->assertSee('id="explore-map-canvas"', false)
        ->assertSee('Map of the listings on this page')
        ->assertSee('data-map-fallback', false)
        ->assertSee('Tourism service')
        ->assertSee('data-map-focus="'.$objBeach->lst_slug.'"', false)
        ->assertSee('No map location yet')
        ->assertSee('Bad Pin Falls')
        ->assertDontSee('Draft Pin Falls');

    expect(array_column($arrPins, 'name'))->toBe(['Pinned Beach', 'Pinned Resort']);
    expect(array_keys($arrPins[0]))->toBe(['slug', 'name', 'kind', 'categoryLabel', 'municipality', 'barangay', 'lat', 'lng', 'href', 'displayImageUrl', 'categoryIcon']);
    expect($arrPins[0]['kind'])->toBe('destination')->and($arrPins[0]['categoryLabel'])->toBe('Beach');
    expect($arrPins[1]['kind'])->toBe('establishment');
    expect($arrPins[0]['href'])->toBe(route('listings.show', $objBeach->lst_slug));
    expect($arrPins[1]['href'])->toBe(route('listings.show', $objResort->lst_slug));
    expect(json_encode($arrPins))->not->toContain($objBeach->lst_uuid)->not->toContain('Owner Name')->not->toContain('lst_id')->not->toContain('PUBLISHED');

    // Summary comment: the pin link resolves to the public detail page by slug.
    $this->get($arrPins[0]['href'])->assertOk()->assertSee('Pinned Beach');
});

test('the Map view only carries the current page of pins', function () {
    foreach (range(1, 25) as $intIndex) {
        directoryListing(sprintf('Pin %02d', $intIndex), ['lst_lat' => 6.9 + $intIndex / 1000, 'lst_lng' => 126.2]);
    } // end foreach pin

    expect(exploreMapPins($this->get(route('explore', ['view' => 'map']))))->toHaveCount(20);
    expect(array_column(exploreMapPins($this->get(route('explore', ['view' => 'map', 'page' => 2]))), 'name'))->toBe(['Pin 21', 'Pin 22', 'Pin 23', 'Pin 24', 'Pin 25']);
});

test('only a public pk. Mapbox token ever reaches the browser', function () {
    directoryListing('Token Falls', ['lst_lat' => 6.9578, 'lst_lng' => 126.2478]);

    config(['services.mapbox.token' => 'sk.test-secret-value']);
    $this->get(route('explore', ['view' => 'map']))->assertOk()->assertDontSee('sk.test-secret-value')->assertSee('data-mapbox-token=""', false);

    config(['services.mapbox.token' => 'pk.test-public-value']);
    $this->get(route('explore', ['view' => 'map']))->assertOk()->assertSee('data-mapbox-token="pk.test-public-value"', false);
});

/*
 * Explore card presentation — a short description excerpt (App\Support\TextSummary) and the public
 * "DOT Accredited" badge from the existing lst_accreditation_status field (Listing::isDotAccredited()).
 */

/** How many DOT Accredited badges a page shows (one icon per badge). */
function dotBadgeCount(TestResponse $objResponse): int
{
    return substr_count($objResponse->getContent(), 'ti-rosette-discount-check');
}

test('a DOT-accredited listing shows the DOT Accredited badge; others show none, and the stored text never appears', function () {
    directoryListing('Accredited Falls', ['lst_accreditation_status' => 'DOT Accredited']);
    directoryListing('Hyphen Resort', ['category_name' => 'Accommodation', 'lst_type' => 'Resort', 'lst_accreditation_status' => 'DOT-accredited']);

    foreach ([null, '', 'Pending', 'Not DOT accredited', 'Accredited', 'DOT accreditation pending'] as $intIndex => $mixValue) {
        directoryListing("Plain Spot {$intIndex}", ['lst_accreditation_status' => $mixValue]);
    } // end foreach non-accredited value

    $objResponse = $this->get(route('explore'))->assertOk();

    expect(dotBadgeCount($objResponse))->toBe(2);
    $objResponse->assertSee('DOT Accredited')
        ->assertDontSee('DOT-accredited')
        ->assertDontSee('DOT accreditation pending')
        ->assertDontSee('Not DOT accredited');

    // Summary comment: the badge sits in the card that is accredited, never in a plain one.
    expect(dotBadgeCount($this->get(route('explore', ['q' => 'Accredited Falls']))))->toBe(1);
    expect(dotBadgeCount($this->get(route('explore', ['q' => 'Plain Spot']))))->toBe(0);

    // Summary comment: the Table view shows the same badge.
    expect(dotBadgeCount($this->get(route('explore', ['view' => 'table']))))->toBe(2);
});

test('the badge comes from the existing accreditation field captured by the LGU establishment form', function () {
    test()->seed(CategorySeeder::class);
    $objMati = Municipality::query()->create(['mun_name' => 'City of Mati', 'mun_code' => 'MATI']);
    $objLgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'usr_organization_subtitle' => 'City of Mati', 'mun_id' => $objMati->mun_id]);

    $this->actingAs($objLgu)->post(route('lgu.directory.establishments.store'), [
        'name' => 'Form Accredited Inn',
        'cat_id' => Category::query()->where('cat_name', 'Accommodation')->value('cat_id'),
        'type' => 'Resort',
        'barangay' => 'Dahican',
        'accreditation_status' => 'DOT-accredited',
    ])->assertSessionHasNoErrors();

    $objListing = Listing::query()->where('lst_name', 'Form Accredited Inn')->firstOrFail();
    expect($objListing->lst_accreditation_status)->toBe('DOT-accredited');
    expect($objListing->isDotAccredited())->toBeTrue();

    // Summary comment: public once published through the existing workflow (set directly here for the test).
    $objListing->forceFill(['lst_status' => 'PUBLISHED'])->save();
    auth()->logout();
    expect(dotBadgeCount($this->get(route('explore', ['q' => 'Form Accredited Inn']))))->toBe(1);
    expect(dotBadgeCount($this->get(route('listings.show', $objListing))))->toBe(1);
});

/** An LGU account for the City of Mati, with its municipality. */
function accreditationLgu(): User
{
    test()->seed(CategorySeeder::class);
    $objMati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);

    return User::factory()->create(['usr_role' => UserRole::Lgu, 'usr_organization_subtitle' => 'City of Mati', 'mun_id' => $objMati->mun_id]);
}

test('an LGU tourist attraction captures the existing accreditation field, and the public badge follows it', function () {
    $objLgu = accreditationLgu();

    $this->actingAs($objLgu)->get(route('lgu.directory.attractions.create'))->assertOk()->assertSee('name="accreditation_status"', false);

    $arrValues = ['Accredited Falls' => 'DOT Accredited', 'Pending Cove' => 'Pending', 'Blank Point' => ''];

    foreach ($arrValues as $strName => $strValue) {
        $this->actingAs($objLgu)->post(route('lgu.directory.attractions.store'), [
            'name' => $strName,
            'barangay' => 'Dahican',
            'type' => 'Beach',
            'accreditation_status' => $strValue,
        ])->assertSessionHasNoErrors();
    } // end foreach submitted value

    $colListings = Listing::query()->whereIn('lst_name', array_keys($arrValues))->get()->keyBy('lst_name');
    expect($colListings['Accredited Falls']->lst_accreditation_status)->toBe('DOT Accredited');
    expect($colListings['Pending Cove']->lst_accreditation_status)->toBe('Pending');
    expect($colListings['Blank Point']->lst_accreditation_status)->toBeNull();
    expect($colListings->every(fn (Listing $objListing) => $objListing->lst_status === 'DRAFT'))->toBeTrue();

    // Summary comment: a Draft is never public, so no badge until it is published.
    auth()->logout();
    expect(dotBadgeCount($this->get(route('explore'))))->toBe(0);

    // Summary comment: public once published through the existing workflow (set directly here for the test).
    Listing::query()->whereIn('lst_name', array_keys($arrValues))->update(['lst_status' => 'Active']);

    expect(dotBadgeCount($this->get(route('explore'))))->toBe(1);
    expect(dotBadgeCount($this->get(route('explore', ['view' => 'table']))))->toBe(1);
    expect(dotBadgeCount($this->get(route('explore', ['q' => 'Accredited Falls']))))->toBe(1);

    foreach ($arrValues as $strName => $strValue) {
        $intExpected = $strName === 'Accredited Falls' ? 1 : 0;
        expect(dotBadgeCount($this->get(route('listings.show', $colListings[$strName]))))->toBe($intExpected);
    } // end foreach listing
});

test('an LGU edit sets or clears a destination accreditation directly, and the older destinations page leaves it alone', function () {
    $objLgu = accreditationLgu();
    $objFalls = directoryListing('Edit Falls', ['mun_id' => $objLgu->mun_id, 'lst_status' => 'Active']);
    $arrBase = ['name' => 'Edit Falls', 'barangay' => 'Dahican', 'description' => 'About Edit Falls.'];

    $this->actingAs($objLgu)->get(route('lgu.directory.attractions.edit', $objFalls))->assertOk()->assertSee('name="accreditation_status"', false);

    // Summary comment: like an establishment's, the field is not held for PTO review (not public content).
    $this->actingAs($objLgu)->put(route('lgu.directory.attractions.update', $objFalls), [...$arrBase, 'accreditation_status' => 'DOT Accredited'])->assertSessionHasNoErrors();
    expect($objFalls->fresh()->lst_accreditation_status)->toBe('DOT Accredited');
    expect($objFalls->fresh()->lst_pending_changes ?? [])->not->toHaveKey('lst_accreditation_status');

    // Summary comment: the older Destinations page form has no accreditation input, so it never clears it.
    $this->actingAs($objLgu)->put(route('lgu.directory.destinations.update', $objFalls), ['name' => 'Edit Falls', 'barangay' => 'Dahican'])->assertSessionHasNoErrors();
    expect($objFalls->fresh()->lst_accreditation_status)->toBe('DOT Accredited');

    $this->actingAs($objLgu)->put(route('lgu.directory.attractions.update', $objFalls), [...$arrBase, 'accreditation_status' => str_repeat('a', 256)])->assertSessionHasErrors('accreditation_status');
    expect($objFalls->fresh()->lst_accreditation_status)->toBe('DOT Accredited');

    $this->actingAs($objLgu)->put(route('lgu.directory.attractions.update', $objFalls), [...$arrBase, 'accreditation_status' => ''])->assertSessionHasNoErrors();
    expect($objFalls->fresh()->lst_accreditation_status)->toBeNull();
    expect($objFalls->fresh()->isDotAccredited())->toBeFalse();
});

test('the PTO directory form captures a destination accreditation through the same field', function () {
    test()->seed(CategorySeeder::class);
    Municipality::query()->firstOrCreate(['mun_code' => 'CAT'], ['mun_name' => 'Cateel']);
    $objPto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $arrFields = [
        'name' => 'Aliwagwag Falls',
        'cat_id' => Category::query()->where('cat_name', 'Tourist Destinations')->value('cat_id'),
        'barangay' => 'Aliwagwag',
        'municipality' => 'Cateel',
        'destination_type' => 'Waterfall',
        'managing_level' => 'pto',
    ];

    $this->actingAs($objPto)->get(route('pto.directory.index'))->assertOk()
        ->assertSee('data-show-when="guide-or-destination"', false)
        ->assertSee('name="accreditation_status"', false);

    $this->actingAs($objPto)->post(route('pto.directory.store'), [...$arrFields, 'accreditation_status' => 'DOT Accredited'])->assertSessionHasNoErrors();

    $objFalls = Listing::query()->where('lst_name', 'Aliwagwag Falls')->firstOrFail();
    expect($objFalls->lst_accreditation_status)->toBe('DOT Accredited');
    expect($objFalls->isDotAccredited())->toBeTrue();
    // Summary comment: still a Draft — accreditation never publishes a record.
    expect($objFalls->lst_status)->toBe('DRAFT');

    $this->actingAs($objPto)->put(route('pto.directory.update', $objFalls), [...$arrFields, 'accreditation_status' => 'Not DOT accredited'])->assertSessionHasNoErrors();
    expect($objFalls->fresh()->lst_accreditation_status)->toBe('Not DOT accredited');
    expect($objFalls->fresh()->isDotAccredited())->toBeFalse();
});

test('accreditation still lives in the one existing column — no new column or flag', function () {
    $arrColumns = array_values(array_filter(
        Schema::getColumnListing('tbl_listings'),
        fn (string $strColumn) => str_contains($strColumn, 'accredit') || str_contains($strColumn, 'dot_'),
    ));

    expect($arrColumns)->toBe(['lst_accreditation_status']);
});

test('Explore cards show a short excerpt while the detail page keeps the complete description', function () {
    $strLong = 'Aliwagwag Falls is a stairway of more than eighty cascades along the Cateel River. '
        .'A canopy walk and zipline give views of nearly every tier. '
        .'Bring water shoes because the stairs get slippery after rain. '
        .'Entrance is collected at the eco-park gate. '
        .'Guides are available daily from seven in the morning.';
    $objDestination = directoryListing('Excerpt Falls', ['lst_description' => $strLong]);
    $objEstablishment = directoryListing('Excerpt Resort', ['category_name' => 'Accommodation', 'lst_type' => 'Resort', 'lst_description' => $strLong]);

    $this->get(route('explore'))
        ->assertOk()
        ->assertSee('Aliwagwag Falls is a stairway of more than eighty cascades along the Cateel River. A canopy walk and zipline give views of nearly every tier.')
        ->assertDontSee('Bring water shoes')
        ->assertDontSee('Guides are available daily');

    foreach ([$objDestination, $objEstablishment] as $objListing) {
        $this->get(route('listings.show', $objListing))->assertOk()->assertSee($strLong);
    } // end foreach listing

    expect(Listing::query()->find($objDestination->lst_id)->lst_description)->toBe($strLong);
});

test('a long or HTML-laden description is shortened safely on the card', function () {
    directoryListing('Run-on Falls', ['lst_description' => str_repeat('scenic ', 60).'finale.']);
    directoryListing('Markup Falls', ['lst_description' => '<b>Bold</b> waterfall <script>alert("x")</script> worth a visit.']);

    $objResponse = $this->get(route('explore'))->assertOk();

    $objResponse->assertSee(trim(str_repeat('scenic ', 35)).TextSummary::ELLIPSIS)->assertDontSee('finale.');
    $objResponse->assertSee('Bold waterfall worth a visit.')
        ->assertDontSee('<script>alert', false)
        ->assertDontSee('alert(&quot;x&quot;)', false)
        ->assertDontSee('<b>Bold</b>', false);
    expect($objResponse->getContent())->toContain('line-clamp-3');
});

test('the excerpt helper keeps whole leading sentences within the limits and never invents text', function () {
    expect(TextSummary::excerpt(null))->toBe('');
    expect(TextSummary::excerpt('  '))->toBe('');
    expect(TextSummary::excerpt('Short and sweet.'))->toBe('Short and sweet.');
    expect(TextSummary::excerpt('One. Two. Three.'))->toBe('One. Two.');
    expect(TextSummary::excerpt("Line one.\n\nLine   two."))->toBe('Line one. Line two.');
    expect(TextSummary::excerpt(str_repeat('a ', 50).'b.'))->toBe(trim(str_repeat('a ', 35)).TextSummary::ELLIPSIS);
});

test('the accreditation flag added to the card data never reaches any public JSON', function () {
    directoryListing('Json Falls', ['lst_accreditation_status' => 'DOT Accredited', 'lst_lat' => 6.9578, 'lst_lng' => 126.2478]);

    foreach ([route('explore', ['view' => 'map']), route('home')] as $strUrl) {
        preg_match_all('#<script type="application/json"[^>]*>(.*?)</script>#s', $this->get($strUrl)->getContent(), $arrMatches);

        foreach ($arrMatches[1] as $strJson) {
            expect($strJson)->not->toContain('isDotAccredited')->not->toContain('Accredited')->not->toContain('accreditation');
        } // end foreach JSON block
    } // end foreach page
});
