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
    return Municipality::query()->create(['name' => $strName, 'code' => $strCode]);
}

function managementLgu(Municipality $objMunicipality): User
{
    return User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_name' => "{$objMunicipality->name} Tourism Office",
        'organization_subtitle' => $objMunicipality->name,
        'municipality_id' => $objMunicipality->id,
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
        'slug' => Str::slug("{$objMunicipality->name}-inn-".Str::random(6)),
        'name' => "{$objMunicipality->name} Inn",
        'category' => 'accommodation',
        'cat_id' => $objCategory->cat_id,
        'type' => 'Hotel',
        'municipality' => $objMunicipality->name,
        'municipality_id' => $objMunicipality->id,
        'barangay' => 'Poblacion',
        'status' => 'DRAFT',
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
    managementEstablishment($objMati, ['name' => 'Mati Seaside Inn']);
    managementEstablishment($objBaganga, ['name' => 'Baganga Riverside Inn']);
    managementEstablishment($objMati, ['name' => 'Mati Falls Destination', 'category' => 'destinations', 'status' => 'Active']);

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
        'municipality_id' => $objBaganga->id,
        'municipality' => 'Baganga',
        'status' => 'PUBLISHED',
    ]));

    $objListing = Listing::query()->where('name', 'Dahican Beach Resort')->firstOrFail();

    $objResponse->assertSessionHasNoErrors()->assertRedirect(route('lgu.directory.establishments.show', $objListing));
    expect($objListing->municipality_id)->toBe($objMati->id);
    expect($objListing->municipality)->toBe('City of Mati');
    expect($objListing->status)->toBe('DRAFT');
    expect($objListing->category)->toBe('accommodation');
    expect($objListing->type)->toBe('Resort');
    expect($objListing->fresh()->reporting_mode)->toBe(ReportingMethod::ManualPaper);
});

test('registering an establishment creates no account and records a create operation log', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objLgu = managementLgu($objMati);
    $intUserCountBefore = User::query()->count();

    test()->actingAs($objLgu)->post(route('lgu.directory.establishments.store'), managementPayload())->assertSessionHasNoErrors();

    $objListing = Listing::query()->where('name', 'Dahican Beach Resort')->firstOrFail();

    expect(User::query()->count())->toBe($intUserCountBefore);
    expect($objListing->establishmentUser)->toBeNull();
    expect(OperationLog::query()->where('entity_type', 'establishment')->where('entity_id', $objListing->id)->where('action', 'create')->where('user_id', $objLgu->id)->exists())->toBeTrue();
});

test('a type outside the chosen category, or the Tourist Destinations category, is rejected', function () {
    $objLgu = managementLgu(managementMunicipality('City of Mati', 'MATI'));

    test()->actingAs($objLgu)->post(route('lgu.directory.establishments.store'), managementPayload(['type' => 'Restaurant']))
        ->assertSessionHasErrors('type');

    test()->actingAs($objLgu)->post(route('lgu.directory.establishments.store'), managementPayload([
        'cat_id' => managementCategory('Tourist Destinations')->cat_id,
        'type' => null,
    ]))->assertSessionHasErrors('cat_id');

    expect(Listing::query()->where('name', 'Dahican Beach Resort')->exists())->toBeFalse();
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

    $objListing = Listing::query()->where('name', 'Dahican Beach Resort')->firstOrFail();
    $objImage = EstablishmentImage::query()->where('listing_id', $objListing->id)->first();

    expect($objImage)->not->toBeNull();
    expect($objImage->img_status)->toBe(ImageStatus::Pending);
    expect($objImage->img_source_role)->toBe(ImageSourceRole::Lgu);
    expect($objImage->img_uploaded_by)->toBe($objLgu->id);
});

test('photos without the ownership confirmation are rejected and nothing is saved', function () {
    $objLgu = managementLgu(managementMunicipality('City of Mati', 'MATI'));

    test()->actingAs($objLgu)->post(route('lgu.directory.establishments.store'), managementPayload([
        'photos' => [UploadedFile::fake()->image('beach.jpg', 1600, 1200)],
    ]))->assertSessionHasErrors('ownership_declared');

    expect(Listing::query()->where('name', 'Dahican Beach Resort')->exists())->toBeFalse();
});

test('an LGU can view, open the edit page of, and update its own establishment', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objLgu = managementLgu($objMati);
    $objListing = managementEstablishment($objMati);

    test()->actingAs($objLgu)->get(route('lgu.directory.establishments.show', $objListing))
        ->assertOk()
        ->assertSee($objListing->name)
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
    expect($objListing->name)->toBe('Renamed Inn');
    expect($objListing->type)->toBe('Cafe');
    expect($objListing->category)->toBe('restaurants');
    expect(OperationLog::query()->where('entity_id', $objListing->id)->where('action', 'update')->exists())->toBeTrue();
});

test('an LGU cannot view, edit, or update another municipality\'s establishment (403, security-logged)', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objBaganga = managementMunicipality('Baganga', 'BAG');
    $objMatiLgu = managementLgu($objMati);
    $objBagangaListing = managementEstablishment($objBaganga, ['name' => 'Baganga Inn']);

    test()->actingAs($objMatiLgu)->get(route('lgu.directory.establishments.show', $objBagangaListing))->assertForbidden();
    test()->actingAs($objMatiLgu)->get(route('lgu.directory.establishments.edit', $objBagangaListing))->assertForbidden();
    test()->actingAs($objMatiLgu)->put(route('lgu.directory.establishments.update', $objBagangaListing), managementPayload(['name' => 'Hijacked']))
        ->assertForbidden();

    expect($objBagangaListing->fresh()->name)->toBe('Baganga Inn');
    expect(SecurityLog::query()->where('user_id', $objMatiLgu->id)->count())->toBeGreaterThanOrEqual(3);
});

test('updating with a forged municipality never moves the establishment', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objBaganga = managementMunicipality('Baganga', 'BAG');
    $objListing = managementEstablishment($objMati);

    test()->actingAs(managementLgu($objMati))->put(route('lgu.directory.establishments.update', $objListing), managementPayload([
        'municipality_id' => $objBaganga->id,
        'municipality' => 'Baganga',
    ]))->assertSessionHasNoErrors();

    expect($objListing->fresh()->municipality_id)->toBe($objMati->id);
    expect($objListing->fresh()->municipality)->toBe('City of Mati');
});

test('while a destination request is with the PTO, public destination content is locked but contact details can change', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objListing = managementEstablishment($objMati, ['name' => 'Pending Inn', 'status' => 'FOR_PTO_REVIEW']);

    test()->actingAs(managementLgu($objMati))->put(route('lgu.directory.establishments.update', $objListing), [
        'name' => 'Sneaky New Name',
        'description' => 'Unapproved description',
        'contact_phone' => '09998887777',
        'hours' => '24 hours',
    ])->assertSessionHasNoErrors();

    $objListing->refresh();
    expect($objListing->name)->toBe('Pending Inn');
    expect($objListing->description)->toBeNull();
    expect($objListing->contact_phone)->toBe('09998887777');
    expect($objListing->hours)->toBe('24 hours');
    expect($objListing->status)->toBe('FOR_PTO_REVIEW');
});

test('while published, public content edits are held for PTO review and contact details save straight away', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objListing = managementEstablishment($objMati, ['name' => 'Published Inn', 'barangay' => 'Dahican', 'status' => 'PUBLISHED']);

    test()->actingAs(managementLgu($objMati))->put(route('lgu.directory.establishments.update', $objListing), managementPayload([
        'name' => 'Renamed Inn',
        'type' => 'Hotel',
        'description' => 'Unapproved description',
        'contact_phone' => '09998887777',
    ]))->assertSessionHasNoErrors();

    $objListing->refresh();
    expect($objListing->name)->toBe('Published Inn');
    expect($objListing->description)->toBeNull();
    expect($objListing->contact_phone)->toBe('09998887777');
    expect($objListing->status)->toBe('PUBLISHED');
    expect($objListing->lst_pending_changes)->toEqual(['name' => 'Renamed Inn', 'description' => 'Unapproved description']);
});

test('a destination is not reachable through the establishment pages', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objDestination = managementEstablishment($objMati, ['category' => 'destinations', 'status' => 'Active']);

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
        'municipality_id' => $objBaganga->id,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $objAttraction = Listing::query()->where('name', 'Aliwagwag Falls')->firstOrFail();

    expect($objAttraction->category)->toBe('destinations');
    expect($objAttraction->status)->toBe('DRAFT');
    expect($objAttraction->municipality_id)->toBe($objMati->id);
    expect($objAttraction->municipality)->toBe('City of Mati');
    expect($objAttraction->getRawOriginal('reporting_mode'))->toBeNull();
    expect($objAttraction->establishmentUser)->toBeNull();
    expect($objAttraction->isQrEnabled())->toBeFalse();
});

test('an LGU can submit a destination-only attraction for PTO review but cannot publish it', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objLgu = managementLgu($objMati);
    $objAttraction = managementEstablishment($objMati, [
        'name' => 'Aliwagwag Falls',
        'category' => 'destinations',
        'status' => 'DRAFT',
    ]);

    test()->actingAs($objLgu)->patch(route('lgu.directory.attractions.submit', $objAttraction))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($objAttraction->fresh()->status)->toBe('FOR_PTO_REVIEW');
    expect($objAttraction->fresh()->isPubliclyVisible())->toBeFalse();

    auth()->logout();
    test()->get(route('listings.show', $objAttraction->fresh()->slug))->assertNotFound();
});

test('a destination-only attraction is published through the PTO workflow and then appears publicly', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objLgu = managementLgu($objMati);
    $objPto = User::factory()->create(['role' => UserRole::PtoAdministrator]);
    $objAttraction = managementEstablishment($objMati, [
        'name' => 'Aliwagwag Falls',
        'category' => 'destinations',
        'status' => 'DRAFT',
    ]);

    test()->actingAs($objLgu)->patch(route('lgu.directory.attractions.submit', $objAttraction))
        ->assertSessionHasNoErrors();

    test()->actingAs($objPto)->patch(route('pto.directory.publish', $objAttraction))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $objAttraction->refresh();

    expect($objAttraction->status)->toBe('Active');
    expect($objAttraction->isPubliclyVisible())->toBeTrue();
    test()->get(route('listings.show', $objAttraction->slug))->assertOk()->assertSee('Aliwagwag Falls');
});

test('an LGU cannot access a destination-only attraction from another municipality', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objBaganga = managementMunicipality('Baganga', 'BAG');
    $objAttraction = managementEstablishment($objBaganga, [
        'name' => 'Baganga Falls',
        'category' => 'destinations',
        'status' => 'DRAFT',
    ]);

    test()->actingAs(managementLgu($objMati))
        ->get(route('lgu.directory.attractions.show', $objAttraction))
        ->assertForbidden();
});

test('non-LGU roles cannot reach the LGU establishment pages', function () {
    $objMati = managementMunicipality('City of Mati', 'MATI');
    $objListing = managementEstablishment($objMati);
    $objPto = User::factory()->create(['role' => UserRole::PtoAdministrator]);
    $objEstablishment = User::factory()->create(['role' => UserRole::Establishment, 'municipality_id' => $objMati->id, 'establishment_id' => $objListing->id]);

    foreach ([$objPto, $objEstablishment] as $objUser) {
        test()->actingAs($objUser)->get(route('lgu.directory.establishments.create'))->assertForbidden();
        test()->actingAs($objUser)->post(route('lgu.directory.establishments.store'), managementPayload())->assertForbidden();
    } // end foreach non-LGU user
});
