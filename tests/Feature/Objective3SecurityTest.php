<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Objective 3, Phase 7 — security and regression hardening of the
 * Tourism Directory: LGU municipality isolation across every Objective 3
 * management route, PTO-managed destinations, forged fields, workflow
 * shortcuts, public data exposure, N+1 checks, and the test-database guard.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\ManagingLevel;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\SecurityLog;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

function hardeningMunicipality(string $strName, string $strCode): Municipality
{
    return Municipality::query()->firstOrCreate(['mun_code' => $strCode], ['mun_name' => $strName]);
}

function hardeningLgu(Municipality $objMunicipality): User
{
    return User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_name' => "{$objMunicipality->mun_name} Tourism Office",
        'usr_organization_subtitle' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
    ]);
}

/**
 * A listing in $objMunicipality — a Draft destination unless overridden.
 *
 * @param  array<string, mixed>  $arrOverrides
 */
function hardeningListing(Municipality $objMunicipality, array $arrOverrides = [], ?ManagingLevel $objManagingLevel = null): Listing
{
    test()->seed(CategorySeeder::class);
    $strCategoryName = $arrOverrides['category_name'] ?? 'Tourist Destinations';
    unset($arrOverrides['category_name']);
    $objCategory = Category::query()->where('cat_name', $strCategoryName)->firstOrFail();
    $objListing = Listing::query()->make(array_merge([
        'lst_slug' => Str::slug('hardening-'.Str::random(8)),
        'lst_name' => "{$objMunicipality->mun_name} Hardening Site",
        'lst_category' => $objCategory->legacySlug(),
        'cat_id' => $objCategory->cat_id,
        'lst_type' => $objCategory->isDestinationCategory() ? null : 'Resort',
        'lst_municipality' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_status' => 'DRAFT',
    ], $arrOverrides));
    $objListing->lst_managing_level = $objManagingLevel;
    $objListing->save();

    return $objListing;
}

/**
 * The access_denied records written for $objUser since $intAfterId.
 *
 * @return array<int, array<string, mixed>>
 */
function hardeningDenials(User $objUser, int $intAfterId = 0): array
{
    return SecurityLog::query()
        ->where('usr_id', $objUser->usr_id)
        ->where('sec_event_type', 'access_denied')
        ->where('sec_id', '>', $intAfterId)
        ->orderBy('sec_id')
        ->get()
        ->pluck('sec_details')
        ->all();
}

function hardeningLastSecurityLogId(): int
{
    return (int) SecurityLog::query()->max('sec_id');
}

test('an LGU cannot reach any Objective 3 management route for another municipality (403, security-logged, nothing changed)', function () {
    $objMati = hardeningMunicipality('City of Mati', 'MATI');
    $objBaganga = hardeningMunicipality('Baganga', 'BAG');
    $objMatiLgu = hardeningLgu($objMati);
    $objAttraction = hardeningListing($objBaganga, ['lst_name' => 'Baganga Falls']);
    $objEstablishment = hardeningListing($objBaganga, ['lst_name' => 'Baganga Inn', 'category_name' => 'Accommodation']);

    $arrAttempts = [
        ['get', route('lgu.directory.attractions.show', $objAttraction), []],
        ['get', route('lgu.directory.attractions.edit', $objAttraction), []],
        ['put', route('lgu.directory.attractions.update', $objAttraction), ['name' => 'Hijacked', 'barangay' => 'X']],
        ['patch', route('lgu.directory.attractions.submit', $objAttraction), []],
        ['put', route('lgu.directory.destinations.update', $objAttraction), ['name' => 'Hijacked', 'barangay' => 'X']],
        ['patch', route('lgu.directory.destinations.archive', $objAttraction), []],
        ['get', route('lgu.directory.establishments.show', $objEstablishment), []],
        ['get', route('lgu.directory.establishments.edit', $objEstablishment), []],
        ['put', route('lgu.directory.establishments.update', $objEstablishment), ['name' => 'Hijacked', 'barangay' => 'X']],
        ['patch', route('lgu.directory.establishments.submit', $objEstablishment), []],
        ['patch', route('lgu.directory.establishments.return', $objEstablishment), ['reason' => 'x']],
        ['get', route('lgu.images.manage', $objAttraction), []],
        ['patch', route('lgu.images.approveBatch', $objEstablishment), ['image_ids' => [1]]],
        ['patch', route('lgu.images.returnBatch', $objEstablishment), ['image_ids' => [1], 'reason' => 'x']],
        ['put', route('lgu.images.reorder', $objAttraction), ['order' => [1]]],
        ['post', route('lgu.images.store'), ['listing_id' => $objAttraction->lst_id]],
    ];

    foreach ($arrAttempts as [$strMethod, $strUrl, $arrPayload]) {
        $intBefore = hardeningLastSecurityLogId();
        $this->actingAs($objMatiLgu)->{$strMethod}($strUrl, $arrPayload)->assertForbidden();
        expect(hardeningDenials($objMatiLgu, $intBefore))->not->toBeEmpty("No security log for {$strMethod} {$strUrl}");
    } // end foreach cross-municipality attempt

    expect($objAttraction->fresh()->only(['lst_name', 'lst_status']))->toBe(['lst_name' => 'Baganga Falls', 'lst_status' => 'DRAFT']);
    expect($objEstablishment->fresh()->only(['lst_name', 'lst_status']))->toBe(['lst_name' => 'Baganga Inn', 'lst_status' => 'DRAFT']);
});

test('the LGU photo pages log a cross-municipality attempt as a municipality-scope denial', function () {
    $objMatiLgu = hardeningLgu(hardeningMunicipality('City of Mati', 'MATI'));
    $objBagangaListing = hardeningListing(hardeningMunicipality('Baganga', 'BAG'), ['category_name' => 'Accommodation']);

    foreach ([['get', route('lgu.images.manage', $objBagangaListing)], ['patch', route('lgu.images.approveBatch', $objBagangaListing)], ['patch', route('lgu.images.returnBatch', $objBagangaListing)]] as [$strMethod, $strUrl]) {
        $intBefore = hardeningLastSecurityLogId();
        $this->actingAs($objMatiLgu)->{$strMethod}($strUrl, ['image_ids' => [1], 'reason' => 'x'])->assertForbidden();
        expect(hardeningDenials($objMatiLgu, $intBefore))->toBe([['ability' => 'municipality_scope', 'resource' => Listing::class, 'target_municipality_id' => $objBagangaListing->mun_id]]);
    } // end foreach photo page
});

test('a PTO-managed destination is view-only for its LGU: each blocked action writes exactly one denial', function () {
    $objMati = hardeningMunicipality('City of Mati', 'MATI');
    $objLgu = hardeningLgu($objMati);
    $objMuseum = hardeningListing($objMati, ['lst_name' => 'PTO Museum'], ManagingLevel::Pto);

    $this->actingAs($objLgu)->get(route('lgu.directory.attractions.show', $objMuseum))->assertOk();

    foreach ([
        ['get', route('lgu.directory.attractions.edit', $objMuseum), []],
        ['put', route('lgu.directory.attractions.update', $objMuseum), ['name' => 'Renamed', 'barangay' => 'X']],
        ['patch', route('lgu.directory.attractions.submit', $objMuseum), []],
        ['put', route('lgu.directory.destinations.update', $objMuseum), ['name' => 'Renamed', 'barangay' => 'X']],
        ['put', route('lgu.images.reorder', $objMuseum), ['order' => [1]]],
        ['post', route('lgu.images.store'), ['listing_id' => $objMuseum->lst_id]],
    ] as [$strMethod, $strUrl, $arrPayload]) {
        $intBefore = hardeningLastSecurityLogId();
        $this->actingAs($objLgu)->{$strMethod}($strUrl, $arrPayload)->assertForbidden();
        expect(hardeningDenials($objLgu, $intBefore))->toHaveCount(1);
    } // end foreach blocked action

    expect($objMuseum->fresh()->only(['lst_name', 'lst_status']))->toBe(['lst_name' => 'PTO Museum', 'lst_status' => 'DRAFT']);
});

test('forged status, municipality, managing-level, and audit fields are ignored', function () {
    test()->seed(CategorySeeder::class);
    $objMati = hardeningMunicipality('City of Mati', 'MATI');
    $objBaganga = hardeningMunicipality('Baganga', 'BAG');
    $objLgu = hardeningLgu($objMati);
    $objOther = User::factory()->create();
    $arrForged = [
        'lst_status' => 'Active', 'status' => 'Active', 'mun_id' => $objBaganga->mun_id, 'municipality_id' => $objBaganga->mun_id,
        'lst_managing_level' => 'pto', 'managing_level' => 'pto', 'lst_created_by' => $objOther->usr_id, 'lst_updated_by' => $objOther->usr_id,
        'lst_uuid' => (string) Str::uuid(), 'lst_slug' => 'forged-slug',
    ];

    $this->actingAs($objLgu)->post(route('lgu.directory.attractions.store'), ['name' => 'Forged Falls', 'barangay' => 'Dahican', ...$arrForged])->assertSessionHasNoErrors();
    $objCreated = Listing::query()->where('lst_name', 'Forged Falls')->firstOrFail();

    expect($objCreated->lst_status)->toBe('DRAFT');
    expect($objCreated->mun_id)->toBe($objMati->mun_id);
    expect($objCreated->lst_managing_level)->toBe(ManagingLevel::Lgu);
    expect($objCreated->lst_created_by)->toBe($objLgu->usr_id);
    expect($objCreated->lst_slug)->not->toBe('forged-slug');

    $this->actingAs($objLgu)->put(route('lgu.directory.attractions.update', $objCreated), ['name' => 'Forged Falls', 'barangay' => 'Dahican', ...$arrForged])->assertSessionHasNoErrors();
    $objUpdated = $objCreated->fresh();

    expect($objUpdated->only(['lst_status', 'mun_id', 'lst_slug', 'lst_uuid']))->toBe($objCreated->only(['lst_status', 'mun_id', 'lst_slug', 'lst_uuid']));
    expect($objUpdated->lst_managing_level)->toBe(ManagingLevel::Lgu);
    expect($objUpdated->lst_updated_by)->toBe($objLgu->usr_id);

    // Summary comment: the PTO create action ignores a forged status too — new records are always Draft.
    $objPto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $this->actingAs($objPto)->post(route('pto.directory.store'), [
        'name' => 'Forged PTO Falls', 'cat_id' => Category::query()->where('cat_name', 'Tourist Destinations')->value('cat_id'),
        'barangay' => 'Poblacion', 'municipality' => 'City of Mati', 'lst_status' => 'Active', 'status' => 'Active',
    ])->assertSessionHasNoErrors();
    expect(Listing::query()->where('lst_name', 'Forged PTO Falls')->value('lst_status'))->toBe('DRAFT');
});

test('no workflow shortcut reaches Published: Change Status, publish-from-Draft, and LGU publish are all refused', function () {
    $objMati = hardeningMunicipality('City of Mati', 'MATI');
    $objPto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $objLgu = hardeningLgu($objMati);

    foreach (['DRAFT', 'Suspended', 'Archived', 'Active'] as $strStartStatus) {
        $objListing = hardeningListing($objMati, ['lst_status' => $strStartStatus]);

        foreach (['Active', 'PUBLISHED', 'FOR_PTO_REVIEW'] as $strTarget) {
            $this->actingAs($objPto)->put(route('pto.directory.updateStatus', $objListing), ['status' => $strTarget, 'reason' => 'Shortcut attempt'])->assertSessionHasErrors('status');
        } // end foreach shortcut target

        expect($objListing->fresh()->lst_status)->toBe($strStartStatus);
    } // end foreach starting status

    // Summary comment: publish only decides a request that is Pending Review.
    $objDraft = hardeningListing($objMati);
    $this->actingAs($objPto)->patch(route('pto.directory.publish', $objDraft))->assertRedirect();
    expect($objDraft->fresh()->lst_status)->toBe('DRAFT');

    // Summary comment: the LGU never reaches the PTO publish or status routes at all.
    $objPending = hardeningListing($objMati, ['lst_status' => 'FOR_PTO_REVIEW']);
    $this->actingAs($objLgu)->patch(route('pto.directory.publish', $objPending))->assertForbidden();
    $this->actingAs($objLgu)->put(route('pto.directory.updateStatus', $objPending), ['status' => 'Archived', 'reason' => 'x'])->assertForbidden();
    expect($objPending->fresh()->lst_status)->toBe('FOR_PTO_REVIEW');
});

test('public pages and their JSON carry no internal ids, UUIDs, owners, or workflow status', function () {
    $objMati = hardeningMunicipality('City of Mati', 'MATI');
    $objDestination = hardeningListing($objMati, ['lst_name' => 'Exposure Falls', 'lst_status' => 'Active', 'lst_lat' => 6.9578, 'lst_lng' => 126.2478, 'lst_owner_name' => 'Hidden Owner One']);
    $objEstablishment = hardeningListing($objMati, ['lst_name' => 'Exposure Resort', 'category_name' => 'Accommodation', 'lst_status' => 'PUBLISHED', 'lst_lat' => 6.96, 'lst_lng' => 126.25, 'lst_owner_name' => 'Hidden Owner Two']);
    hardeningListing($objMati, ['lst_name' => 'Pending Secret Falls', 'lst_status' => 'FOR_PTO_REVIEW']);

    $arrPages = [
        route('home'),
        route('explore'),
        route('explore', ['view' => 'table']),
        route('explore', ['view' => 'map']),
        route('listings.show', $objDestination),
        route('listings.show', $objEstablishment),
        route('listings.nearby', $objDestination),
    ];

    foreach ($arrPages as $strUrl) {
        $strHtml = $this->get($strUrl)->assertOk()->getContent();

        expect($strHtml)
            ->not->toContain($objDestination->lst_uuid)
            ->not->toContain($objEstablishment->lst_uuid)
            ->not->toContain('Hidden Owner')
            ->not->toContain('Pending Secret Falls')
            ->not->toContain('lst_id')
            ->not->toContain('"status":')
            ->not->toContain('isPubliclyVisible')
            ->not->toContain('FOR_PTO_REVIEW');
    } // end foreach public page

    // Summary comment: the landing modal JSON is an explicit allow-list.
    preg_match('#id="listing-details-data">(.*?)</script>#s', $this->get(route('home'))->getContent(), $arrMatch);
    $arrModal = json_decode($arrMatch[1], true)[$objDestination->lst_slug];
    expect(array_keys($arrModal))->toBe(['id', 'name', 'category', 'municipality', 'barangay', 'lat', 'lng', 'description', 'rating', 'tags', 'displayImageUrl', 'categoryIcon', 'contactOffice', 'contactPhone', 'hours', 'href', 'email', 'website', 'categoryLabel', 'directionsUrl']);
});

test('the Explore directory runs a fixed number of queries however many listings a page shows (no N+1)', function () {
    $objMati = hardeningMunicipality('City of Mati', 'MATI');
    $fnQueryCount = function (): int {
        $intQueries = 0;
        DB::listen(function () use (&$intQueries) {
            $intQueries++;
        });
        $this->get(route('explore'))->assertOk();

        return $intQueries;
    };

    foreach (range(1, 3) as $intIndex) {
        hardeningListing($objMati, ['lst_name' => "Few {$intIndex}", 'lst_status' => 'Active']);
    } // end foreach few
    $intFewQueries = $fnQueryCount();

    foreach (range(1, 17) as $intIndex) {
        hardeningListing($objMati, ['lst_name' => "Many {$intIndex}", 'lst_status' => 'Active', 'category_name' => $intIndex % 2 ? 'Accommodation' : 'Tourist Destinations']);
    } // end foreach many
    $intManyQueries = $fnQueryCount();

    expect($intManyQueries)->toBe($intFewQueries);
});

test('the test-database guard allows only the isolated SQLite file, in-memory SQLite, and itour_testing', function () {
    expect(TestCase::isDisposableTestDatabase('sqlite', 'storage/testing.sqlite'))->toBeTrue();
    expect(TestCase::isDisposableTestDatabase('sqlite', 'C:\\app\\storage\\testing.sqlite'))->toBeTrue();
    expect(TestCase::isDisposableTestDatabase('sqlite', ':memory:'))->toBeTrue();
    expect(TestCase::isDisposableTestDatabase('pgsql', 'itour_testing'))->toBeTrue();

    expect(TestCase::isDisposableTestDatabase('sqlite', 'database/database.sqlite'))->toBeFalse();
    expect(TestCase::isDisposableTestDatabase('sqlite', 'storage/testing.sqlite.bak'))->toBeFalse();
    expect(TestCase::isDisposableTestDatabase('pgsql', 'iTour_DB'))->toBeFalse();
    expect(TestCase::isDisposableTestDatabase('pgsql', 'itour_testing_copy'))->toBeFalse();
    expect(TestCase::isDisposableTestDatabase('mysql', 'itour_testing'))->toBeFalse();
});
