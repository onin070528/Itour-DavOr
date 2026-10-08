<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Phase 2 — LGU establishment management: municipality-scoped list, add (with photos), view, edit, validation, authorization, and audit logging.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Enums\ReportingMethod;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\OperationLog;
use App\Models\SecurityLog;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function managementMunicipality(string $strName, string $strCode): Municipality
{
    return Municipality::query()->create(['mun_name' => $strName, 'mun_code' => $strCode]);
}

function managementLgu(Municipality $objMunicipality): User
{
    return User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_name' => "{$objMunicipality->mun_name} Tourism Office",
        'usr_organization_subtitle' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
    ]);
}

function managementCategory(string $strCategoryName): Category
{
    test()->seed(CategorySeeder::class);

    return Category::query()->where('cat_name', $strCategoryName)->firstOrFail();
}

/**
 * @param  array<string, mixed>  $arrOverrides
 */
function managementEstablishment(Municipality $objMunicipality, array $arrOverrides = []): Listing
{
    $objCategory = managementCategory('Accommodation');

    return Listing::query()->create(array_merge([
        'lst_slug' => Str::slug("{$objMunicipality->mun_name}-inn-".Str::random(6)),
        'lst_name' => "{$objMunicipality->mun_name} Inn",
        'lst_category' => 'accommodation',
        'cat_id' => $objCategory->cat_id,
        'lst_type' => 'Hotel',
        'lst_municipality' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_status' => 'DRAFT',
    ], $arrOverrides));
}

/**
 * @param  array<string, mixed>  $arrOverrides
 * @return array<string, mixed>
 */
function managementPayload(array $arrOverrides = []): array
{
    return array_merge([
        'name' => 'Dahican Beach Resort',
        'cat_id' => managementCategory('Accommodation')->cat_id,
        'type' => 'Resort',
        'barangay' => 'Dahican',
        'contact_phone' => '09171234567',
        'description' => 'Beachfront resort.',
    ], $arrOverrides);
}

test('the LGU establishment list shows only its own municipality\'s establishments, never destinations', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objBaganga = managementMunicipality('Baganga', 'BAG');
    managementEstablishment($objMati, ['lst_name' => 'Mati Seaside Inn']);
    managementEstablishment($objBaganga, ['lst_name' => 'Baganga Riverside Inn']);
    managementEstablishment($objMati, ['lst_name' => 'Mati Falls Destination', 'lst_category' => 'destinations', 'lst_status' => 'Active']);

    test()->actingAs(managementLgu($objMati))->get(route('lgu.directory.establishments'))
        ->assertOk()
        ->assertSee('Mati Seaside Inn')
        ->assertSee('Manual/Paper')
        ->assertSee('Not Requested')
        ->assertDontSee('Baganga Riverside Inn')
        ->assertDontSee('Mati Falls Destination');
});

test('an LGU registers an establishment in its own municipality only — a forged municipality is ignored', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objBaganga = managementMunicipality('Baganga', 'BAG');
    $objLgu = managementLgu($objMati);

    $objResponse = test()->actingAs($objLgu)->post(route('lgu.directory.establishments.store'), managementPayload([
        'municipality_id' => $objBaganga->mun_id,
        'municipality' => 'Baganga',
        'status' => 'PUBLISHED',
    ]));

    $objListing = Listing::query()->where('lst_name', 'Dahican Beach Resort')->firstOrFail();

    $objResponse->assertSessionHasNoErrors()->assertRedirect(route('lgu.directory.establishments.show', $objListing));
    expect($objListing->mun_id)->toBe($objMati->mun_id);
    expect($objListing->lst_municipality)->toBe('City of Mati');
    expect($objListing->lst_status)->toBe('DRAFT');
    expect($objListing->lst_category)->toBe('accommodation');
    expect($objListing->lst_type)->toBe('Resort');
    expect($objListing->fresh()->lst_reporting_mode)->toBe(ReportingMethod::ManualPaper);
});

test('registering an establishment creates no account and records a create operation log', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objLgu = managementLgu($objMati);
    $intUserCountBefore = User::query()->count();

    test()->actingAs($objLgu)->post(route('lgu.directory.establishments.store'), managementPayload())->assertSessionHasNoErrors();

    $objListing = Listing::query()->where('lst_name', 'Dahican Beach Resort')->firstOrFail();

    expect(User::query()->count())->toBe($intUserCountBefore);
    expect($objListing->establishmentUser)->toBeNull();
    expect(OperationLog::query()->where('opl_entity_type', 'establishment')->where('opl_entity_id', $objListing->lst_id)->where('opl_action', 'create')->where('usr_id', $objLgu->usr_id)->exists())->toBeTrue();
});

test('a type outside the chosen category, or the Tourist Destinations category, is rejected', function () {
    $objLgu = managementLgu(managementMunicipality('City of Mati', 'MATI'));

    test()->actingAs($objLgu)->post(route('lgu.directory.establishments.store'), managementPayload(['type' => 'Restaurant']))
        ->assertSessionHasErrors('type');

    test()->actingAs($objLgu)->post(route('lgu.directory.establishments.store'), managementPayload([
        'cat_id' => managementCategory('Tourist Destinations')->cat_id,
        'type' => null,
    ]))->assertSessionHasErrors('cat_id');

    expect(Listing::query()->where('lst_name', 'Dahican Beach Resort')->exists())->toBeFalse();
});

test('photos added on the Add form go through the existing photo workflow for PTO approval', function () {
    Storage::fake('local');
    Storage::fake('public');
    $objLgu = managementLgu(managementMunicipality('City of Mati', 'MATI'));

    test()->actingAs($objLgu)->post(route('lgu.directory.establishments.store'), managementPayload([
        'photos' => [UploadedFile::fake()->image('beach.jpg', 1600, 1200)],
        'ownership_declared' => '1',
        'credit' => 'Mati City Tourism Office',
    ]))->assertSessionHasNoErrors();

    $objListing = Listing::query()->where('lst_name', 'Dahican Beach Resort')->firstOrFail();
    $objImage = EstablishmentImage::query()->where('lst_id', $objListing->lst_id)->first();

    expect($objImage)->not->toBeNull();
    expect($objImage->img_status)->toBe(ImageStatus::Pending);
    expect($objImage->img_source_role)->toBe(ImageSourceRole::Lgu);
    expect($objImage->img_uploaded_by)->toBe($objLgu->usr_id);
});

test('photos without the ownership confirmation are rejected and nothing is saved', function () {
    $objLgu = managementLgu(managementMunicipality('City of Mati', 'MATI'));

    test()->actingAs($objLgu)->post(route('lgu.directory.establishments.store'), managementPayload([
        'photos' => [UploadedFile::fake()->image('beach.jpg', 1600, 1200)],
    ]))->assertSessionHasErrors('ownership_declared');

    expect(Listing::query()->where('lst_name', 'Dahican Beach Resort')->exists())->toBeFalse();
});

test('an LGU can view, open the edit page of, and update its own establishment', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objLgu = managementLgu($objMati);
    $objListing = managementEstablishment($objMati);

    test()->actingAs($objLgu)->get(route('lgu.directory.establishments.show', $objListing))
        ->assertOk()
        ->assertSee($objListing->lst_name)
        ->assertSee('Manual/Paper');
    test()->actingAs($objLgu)->get(route('lgu.directory.establishments.edit', $objListing))->assertOk();
    test()->actingAs($objLgu)->get(route('lgu.directory.establishments.create'))
        ->assertOk()
        ->assertSee('Assigned from your LGU account.')
        ->assertDontSee('name="municipality', false);

    test()->actingAs($objLgu)->put(route('lgu.directory.establishments.update', $objListing), managementPayload([
        'name' => 'Renamed Inn',
        'cat_id' => managementCategory('Food & Dining')->cat_id,
        'type' => 'Cafe',
    ]))->assertSessionHasNoErrors()->assertRedirect(route('lgu.directory.establishments.show', $objListing));

    $objListing->refresh();
    expect($objListing->lst_name)->toBe('Renamed Inn');
    expect($objListing->lst_type)->toBe('Cafe');
    expect($objListing->lst_category)->toBe('restaurants');
    expect(OperationLog::query()->where('opl_entity_id', $objListing->lst_id)->where('opl_action', 'update')->exists())->toBeTrue();
});

test('an LGU cannot view, edit, or update another municipality\'s establishment (403, security-logged)', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objBaganga = managementMunicipality('Baganga', 'BAG');
    $objMatiLgu = managementLgu($objMati);
    $objBagangaListing = managementEstablishment($objBaganga, ['lst_name' => 'Baganga Inn']);

    test()->actingAs($objMatiLgu)->get(route('lgu.directory.establishments.show', $objBagangaListing))->assertForbidden();
    test()->actingAs($objMatiLgu)->get(route('lgu.directory.establishments.edit', $objBagangaListing))->assertForbidden();
    test()->actingAs($objMatiLgu)->put(route('lgu.directory.establishments.update', $objBagangaListing), managementPayload(['name' => 'Hijacked']))
        ->assertForbidden();

    expect($objBagangaListing->fresh()->lst_name)->toBe('Baganga Inn');
    expect(SecurityLog::query()->where('usr_id', $objMatiLgu->usr_id)->count())->toBeGreaterThanOrEqual(3);
});

test('updating with a forged municipality never moves the establishment', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objBaganga = managementMunicipality('Baganga', 'BAG');
    $objListing = managementEstablishment($objMati);

    test()->actingAs(managementLgu($objMati))->put(route('lgu.directory.establishments.update', $objListing), managementPayload([
        'municipality_id' => $objBaganga->mun_id,
        'municipality' => 'Baganga',
    ]))->assertSessionHasNoErrors();

    expect($objListing->fresh()->mun_id)->toBe($objMati->mun_id);
    expect($objListing->fresh()->lst_municipality)->toBe('City of Mati');
});

test('while a destination request is with the PTO, public destination content is locked but contact details can change', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objListing = managementEstablishment($objMati, ['lst_name' => 'Pending Inn', 'lst_status' => 'FOR_PTO_REVIEW']);

    test()->actingAs(managementLgu($objMati))->put(route('lgu.directory.establishments.update', $objListing), [
        'name' => 'Sneaky New Name',
        'description' => 'Unapproved description',
        'contact_phone' => '09998887777',
        'hours' => '24 hours',
    ])->assertSessionHasNoErrors();

    $objListing->refresh();
    expect($objListing->lst_name)->toBe('Pending Inn');
    expect($objListing->lst_description)->toBeNull();
    expect($objListing->lst_contact_phone)->toBe('09998887777');
    expect($objListing->lst_hours)->toBe('24 hours');
    expect($objListing->lst_status)->toBe('FOR_PTO_REVIEW');
});

test('while published, public content edits are held for PTO review and contact details save straight away', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objListing = managementEstablishment($objMati, ['lst_name' => 'Published Inn', 'lst_barangay' => 'Dahican', 'lst_status' => 'PUBLISHED']);

    test()->actingAs(managementLgu($objMati))->put(route('lgu.directory.establishments.update', $objListing), managementPayload([
        'name' => 'Renamed Inn',
        'type' => 'Hotel',
        'description' => 'Unapproved description',
        'contact_phone' => '09998887777',
    ]))->assertSessionHasNoErrors();

    $objListing->refresh();
    expect($objListing->lst_name)->toBe('Published Inn');
    expect($objListing->lst_description)->toBeNull();
    expect($objListing->lst_contact_phone)->toBe('09998887777');
    expect($objListing->lst_status)->toBe('PUBLISHED');
    expect($objListing->lst_pending_changes)->toEqual(['lst_name' => 'Renamed Inn', 'lst_description' => 'Unapproved description']);
});

test('a destination is not reachable through the establishment pages', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objDestination = managementEstablishment($objMati, ['lst_category' => 'destinations', 'lst_status' => 'Active']);

    test()->actingAs(managementLgu($objMati))->get(route('lgu.directory.establishments.show', $objDestination))->assertNotFound();
});

test('an LGU can create a destination-only attraction without an account, QR, or reporting method', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objBaganga = managementMunicipality('Baganga', 'BAG');
    $objLgu = managementLgu($objMati);

    test()->actingAs($objLgu)->post(route('lgu.directory.attractions.store'), [
        'name' => 'Aliwagwag Falls',
        'barangay' => 'Dapnan',
        'description' => 'A destination-only tourism record.',
        'municipality_id' => $objBaganga->mun_id,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $objAttraction = Listing::query()->where('lst_name', 'Aliwagwag Falls')->firstOrFail();

    expect($objAttraction->lst_category)->toBe('destinations');
    expect($objAttraction->lst_status)->toBe('DRAFT');
    expect($objAttraction->mun_id)->toBe($objMati->mun_id);
    expect($objAttraction->lst_municipality)->toBe('City of Mati');
    expect($objAttraction->getRawOriginal('lst_reporting_mode'))->toBeNull();
    expect($objAttraction->establishmentUser)->toBeNull();
    expect($objAttraction->isQrEnabled())->toBeFalse();
});

test('an LGU can submit a destination-only attraction for PTO review but cannot publish it', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objLgu = managementLgu($objMati);
    $objAttraction = managementEstablishment($objMati, [
        'lst_name' => 'Aliwagwag Falls',
        'lst_category' => 'destinations',
        'lst_status' => 'DRAFT',
    ]);

    test()->actingAs($objLgu)->patch(route('lgu.directory.attractions.submit', $objAttraction))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($objAttraction->fresh()->lst_status)->toBe('FOR_PTO_REVIEW');
    expect($objAttraction->fresh()->isPubliclyVisible())->toBeFalse();

    auth()->logout();
    test()->get(route('listings.show', $objAttraction->fresh()->lst_slug))->assertNotFound();
});

test('a destination-only attraction is published through the PTO workflow and then appears publicly', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objLgu = managementLgu($objMati);
    $objPto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $objAttraction = managementEstablishment($objMati, [
        'lst_name' => 'Aliwagwag Falls',
        'lst_category' => 'destinations',
        'lst_status' => 'DRAFT',
    ]);

    test()->actingAs($objLgu)->patch(route('lgu.directory.attractions.submit', $objAttraction))
        ->assertSessionHasNoErrors();

    test()->actingAs($objPto)->patch(route('pto.directory.publish', $objAttraction))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $objAttraction->refresh();

    expect($objAttraction->lst_status)->toBe('Active');
    expect($objAttraction->isPubliclyVisible())->toBeTrue();
    test()->get(route('listings.show', $objAttraction->lst_slug))->assertOk()->assertSee('Aliwagwag Falls');
});

test('an LGU cannot access a destination-only attraction from another municipality', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objBaganga = managementMunicipality('Baganga', 'BAG');
    $objAttraction = managementEstablishment($objBaganga, [
        'lst_name' => 'Baganga Falls',
        'lst_category' => 'destinations',
        'lst_status' => 'DRAFT',
    ]);

    test()->actingAs(managementLgu($objMati))
        ->get(route('lgu.directory.attractions.show', $objAttraction))
        ->assertForbidden();
});

test('non-LGU roles cannot reach the LGU establishment pages', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objListing = managementEstablishment($objMati);
    $objPto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $objEstablishment = User::factory()->create(['usr_role' => UserRole::Establishment, 'mun_id' => $objMati->mun_id, 'lst_id' => $objListing->lst_id]);

    foreach ([$objPto, $objEstablishment] as $objUser) {
        test()->actingAs($objUser)->get(route('lgu.directory.establishments.create'))->assertForbidden();
        test()->actingAs($objUser)->post(route('lgu.directory.establishments.store'), managementPayload())->assertForbidden();
    } // end foreach non-LGU user
});

test('two attractions with the same name get distinct, stable slugs', function () {
    $objLgu = managementLgu(managementMunicipality('City of Mati', 'MATI'));

    foreach ([1, 2] as $intAttempt) {
        test()->actingAs($objLgu)->post(route('lgu.directory.attractions.store'), [
            'name' => 'Aliwagwag Falls',
            'barangay' => 'Dapnan',
        ])->assertSessionHasNoErrors();
    } // end foreach attempt

    expect(Listing::query()->where('lst_name', 'Aliwagwag Falls')->orderBy('lst_id')->pluck('lst_slug')->all())
        ->toBe(['aliwagwag-falls', 'aliwagwag-falls-2']);
});

test('attraction coordinates must be numeric, paired, and inside Davao Oriental', function () {
    $objLgu = managementLgu(managementMunicipality('City of Mati', 'MATI'));
    $strOutsideMessage = 'The selected location must be inside Davao Oriental. Move the map marker into the province.';

    // Summary comment: one half of the pair alone is rejected.
    test()->actingAs($objLgu)->post(route('lgu.directory.attractions.store'), ['name' => 'Half Pin', 'barangay' => 'Dahican', 'lat' => 6.9578])
        ->assertSessionHasErrors(['lat' => 'Set both latitude and longitude, or leave both empty.']);

    // Summary comment: a real coordinate outside the province (Manila) is rejected on both fields.
    test()->actingAs($objLgu)->post(route('lgu.directory.attractions.store'), ['name' => 'Manila Pin', 'barangay' => 'Ermita', 'lat' => 14.5995, 'lng' => 120.9842])
        ->assertSessionHasErrors(['lat' => $strOutsideMessage, 'lng' => $strOutsideMessage]);

    test()->actingAs($objLgu)->post(route('lgu.directory.attractions.store'), ['name' => 'Text Pin', 'barangay' => 'Dahican', 'lat' => 'north', 'lng' => 126.2478])
        ->assertSessionHasErrors('lat');

    expect(Listing::query()->whereIn('lst_name', ['Half Pin', 'Manila Pin', 'Text Pin'])->exists())->toBeFalse();

    // Summary comment: a point inside Davao Oriental, or no location at all, is accepted.
    test()->actingAs($objLgu)->post(route('lgu.directory.attractions.store'), ['name' => 'Dahican Pin', 'barangay' => 'Dahican', 'lat' => 6.9578, 'lng' => 126.2478])
        ->assertSessionHasNoErrors();
    test()->actingAs($objLgu)->post(route('lgu.directory.attractions.store'), ['name' => 'No Pin Yet', 'barangay' => 'Dahican'])
        ->assertSessionHasNoErrors();

    expect(Listing::query()->where('lst_name', 'Dahican Pin')->first()?->lst_lat)->toBe(6.9578);
    expect(Listing::query()->where('lst_name', 'No Pin Yet')->first()?->lst_lat)->toBeNull();
});

test('the establishment and PTO directory forms apply the same Davao Oriental coordinate guard', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objPto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $arrOutside = ['lat' => 14.5995, 'lng' => 120.9842];

    test()->actingAs(managementLgu($objMati))->post(route('lgu.directory.establishments.store'), managementPayload($arrOutside))
        ->assertSessionHasErrors(['lat', 'lng']);

    test()->actingAs($objPto)->post(route('pto.directory.store'), managementPayload([...$arrOutside, 'municipality' => 'City of Mati']))
        ->assertSessionHasErrors(['lat', 'lng']);

    expect(Listing::query()->where('lst_name', 'Dahican Beach Resort')->exists())->toBeFalse();
});
