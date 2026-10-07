<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Phase 1 — establishment Category -> Type single source and validation, reporting method default, municipality guard, and the approved backfill.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use App\Enums\ReportingMethod;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\SecurityLog;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function classificationMunicipality(string $strName, string $strCode): Municipality
{
    return Municipality::query()->create(['mun_name' => $strName, 'mun_code' => $strCode]);
}

function classificationLgu(Municipality $objMunicipality): User
{
    return User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_name' => "{$objMunicipality->mun_name} Tourism Office",
        'usr_organization_subtitle' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
    ]);
}

function classificationPto(): User
{
    return User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
}

function classificationListing(Municipality $objMunicipality, string $strCategory): Listing
{
    return Listing::query()->create([
        'lst_slug' => Str::slug("{$objMunicipality->mun_name}-{$strCategory}-".Str::random(6)),
        'lst_name' => "{$objMunicipality->mun_name} Test {$strCategory}",
        'lst_category' => $strCategory,
        'lst_municipality' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_status' => 'DRAFT',
    ]);
}

/**
 * The seeded category with the given name.
 */
function classificationCategory(string $strCategoryName): Category
{
    test()->seed(CategorySeeder::class);

    return Category::query()->where('cat_name', $strCategoryName)->firstOrFail();
}

/**
 * A minimal valid PTO directory payload for an establishment.
 *
 * @param  array<string, mixed>  $arrOverrides
 * @return array<string, mixed>
 */
function classificationPtoPayload(Category $objCategory, array $arrOverrides = []): array
{
    return array_merge([
        'name' => 'Classification Test Resort',
        'cat_id' => $objCategory->cat_id,
        'municipality' => 'City of Mati',
        'barangay' => 'Dahican',
    ], $arrOverrides);
}

test('the type config covers exactly the seeded establishment categories', function () {
    test()->seed(CategorySeeder::class);

    $arrEstablishmentCategoryNames = Category::query()->forEstablishments()->orderBy('cat_sort_order')->pluck('cat_name')->all();

    expect(array_keys(config('establishment_categories.types')))->toBe($arrEstablishmentCategoryNames);
    expect(Category::query()->where('cat_name', 'Tourist Destinations')->first()->establishmentTypes())->toBe([]);
    expect(Category::query()->forEstablishments()->pluck('cat_name'))->not->toContain('Tourist Destinations');
});

test('the tour guide type belongs to Travel & Tours and is recognized alongside the legacy value', function () {
    expect(classificationCategory('Travel & Tours')->establishmentTypes())->toContain(config('establishment_categories.tour_guide_type'));

    expect((new Listing(['lst_type' => 'Tour Guide Service']))->isTourGuide())->toBeTrue();
    expect((new Listing(['lst_type' => 'Tour Guide']))->isTourGuide())->toBeTrue();
    expect((new Listing(['lst_type' => 'Tour Operator']))->isTourGuide())->toBeFalse();
});

test('PTO saving an establishment with a type from its own category stores the type', function () {
    classificationMunicipality('City of Mati', 'MATI');
    $objAccommodation = classificationCategory('Accommodation');

    test()->actingAs(classificationPto())
        ->post(route('pto.directory.store'), classificationPtoPayload($objAccommodation, ['type' => 'Resort']))
        ->assertSessionHasNoErrors();

    expect(Listing::query()->where('lst_name', 'Classification Test Resort')->value('lst_type'))->toBe('Resort');
});

test('PTO saving an establishment with a type from another category is rejected', function () {
    classificationMunicipality('City of Mati', 'MATI');
    $objAccommodation = classificationCategory('Accommodation');

    test()->actingAs(classificationPto())
        ->post(route('pto.directory.store'), classificationPtoPayload($objAccommodation, ['type' => 'Restaurant']))
        ->assertSessionHasErrors('type');

    expect(Listing::query()->where('lst_name', 'Classification Test Resort')->exists())->toBeFalse();
});

test('an establishment category requires a type, a destination prohibits one', function () {
    classificationMunicipality('City of Mati', 'MATI');
    $objPto = classificationPto();

    test()->actingAs($objPto)
        ->post(route('pto.directory.store'), classificationPtoPayload(classificationCategory('Accommodation')))
        ->assertSessionHasErrors('type');

    test()->actingAs($objPto)
        ->post(route('pto.directory.store'), classificationPtoPayload(classificationCategory('Tourist Destinations'), ['type' => 'Resort']))
        ->assertSessionHasErrors('type');
});

test('a newly created establishment defaults to Manual/Paper reporting', function () {
    $objListing = classificationListing(classificationMunicipality('City of Mati', 'MATI'), 'accommodation')->refresh();

    expect($objListing->lst_reporting_mode)->toBe(ReportingMethod::ManualPaper);
    expect($objListing->reportingMethod()->label())->toBe('Manual/Paper');
});

test('reporting_mode cannot be mass-assigned', function () {
    $objListing = new Listing(['lst_reporting_mode' => ReportingMethod::OnlineItour->value]);

    expect($objListing->getAttributes())->not->toHaveKey('lst_reporting_mode');
});

test('activating an establishment\'s account switches it to Online iTOUR', function () {
    Mail::fake();
    $objMati = classificationMunicipality('City of Mati', 'MATI');
    $objListing = classificationListing($objMati, 'accommodation');

    test()->actingAs(classificationLgu($objMati))->post(route('lgu.directory.establishments.switchToOnline', $objListing), [
        'account_name' => 'Juan Dela Cruz',
        'account_email' => 'online-inn@example.test',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($objListing->fresh()->lst_reporting_mode)->toBe(ReportingMethod::OnlineItour);
});

test('an LGU user cannot move a listing to another municipality, even through the model', function () {
    $objMati = classificationMunicipality('City of Mati', 'MATI');
    $objBaganga = classificationMunicipality('Baganga', 'BAG');
    $objListing = classificationListing($objMati, 'accommodation');
    $objLgu = classificationLgu($objMati);

    test()->actingAs($objLgu);

    expect(fn () => $objListing->update(['mun_id' => $objBaganga->mun_id]))->toThrow(AuthorizationException::class);
    expect($objListing->fresh()->mun_id)->toBe($objMati->mun_id);
    expect(SecurityLog::query()->where('usr_id', $objLgu->usr_id)->exists())->toBeTrue();
});

test('PTO and non-request contexts can still reassign a listing municipality', function () {
    $objMati = classificationMunicipality('City of Mati', 'MATI');
    $objBaganga = classificationMunicipality('Baganga', 'BAG');
    $objListing = classificationListing($objMati, 'accommodation');

    // Summary comment: no signed-in user (seeders, console commands).
    $objListing->update(['mun_id' => $objBaganga->mun_id]);
    expect($objListing->fresh()->mun_id)->toBe($objBaganga->mun_id);

    test()->actingAs(User::factory()->create(['usr_role' => UserRole::PtoAdministrator]));
    $objListing->update(['mun_id' => $objMati->mun_id]);
    expect($objListing->fresh()->mun_id)->toBe($objMati->mun_id);
});

test('the backfill fills only the approved values and its rollback restores them', function () {
    $objMati = classificationMunicipality('City of Mati', 'MATI');
    $objAccommodation = classificationCategory('Accommodation');

    // Summary comment: rows shaped like the pre-backfill data (old DIGITAL default, no type).
    $fnMakeRow = function (string $strSlug, string $strCategory, ?int $intCategoryId) use ($objMati): int {
        return DB::table('tbl_listings')->insertGetId([
            'lst_slug' => $strSlug,
            'lst_uuid' => (string) Str::uuid(),
            'lst_name' => $strSlug,
            'lst_category' => $strCategory,
            'cat_id' => $intCategoryId,
            'lst_municipality' => $objMati->mun_name,
            'mun_id' => $objMati->mun_id,
            'lst_barangay' => 'Poblacion',
            'lst_status' => 'DRAFT',
            'lst_reporting_mode' => ReportingMethod::OnlineItour->value,
        ]);
    };

    $intBotanikaId = $fnMakeRow('botanika-nature-resort', 'accommodation', $objAccommodation->cat_id);
    $intTerminalId = $fnMakeRow('tourist-transport-terminal', 'transportation', null);
    $intSurfId = $fnMakeRow('dahican-surf-guides', 'tour-guides', null);
    $intDemoId = $fnMakeRow('itour-demo-establishment-baganga', 'accommodation', null);
    // Different data than expected: must be left alone.
    $intMismatchId = $fnMakeRow('badjao-seafront', 'accommodation', null);

    $objMigration = require database_path('migrations/2026_10_07_160100_backfill_establishment_classification_on_listings_table.php');
    $objMigration->up();

    $fnRow = fn (int $intId) => DB::table('tbl_listings')->where('lst_id', $intId)->first();

    expect($fnRow($intBotanikaId)->lst_type)->toBe('Resort');
    expect($fnRow($intBotanikaId)->lst_reporting_mode)->toBe('DIGITAL');
    expect($fnRow($intTerminalId)->lst_type)->toBe('Van / Shuttle Service');
    expect($fnRow($intTerminalId)->lst_reporting_mode)->toBe('PAPER_LGU');
    expect($fnRow($intSurfId)->lst_type)->toBeNull();
    expect($fnRow($intDemoId)->cat_id)->toBe($objAccommodation->cat_id);
    expect($fnRow($intDemoId)->lst_reporting_mode)->toBe('PAPER_LGU');
    expect($fnRow($intMismatchId)->lst_type)->toBeNull();

    $objMigration->down();

    expect($fnRow($intBotanikaId)->lst_type)->toBeNull();
    expect($fnRow($intTerminalId)->lst_reporting_mode)->toBe('DIGITAL');
    expect($fnRow($intDemoId)->cat_id)->toBeNull();
    expect($fnRow($intDemoId)->lst_reporting_mode)->toBe('DIGITAL');
});

test('the backfill never switches an establishment with a linked account to Manual/Paper', function () {
    $objMati = classificationMunicipality('City of Mati', 'MATI');
    $intTerminalId = DB::table('tbl_listings')->insertGetId([
        'lst_slug' => 'tourist-transport-terminal',
        'lst_uuid' => (string) Str::uuid(),
        'lst_name' => 'Terminal',
        'lst_category' => 'transportation',
        'lst_municipality' => $objMati->mun_name,
        'mun_id' => $objMati->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_status' => 'DRAFT',
        'lst_reporting_mode' => ReportingMethod::OnlineItour->value,
    ]);
    User::factory()->create(['usr_role' => UserRole::Establishment, 'mun_id' => $objMati->mun_id, 'lst_id' => $intTerminalId]);

    $objMigration = require database_path('migrations/2026_10_07_160100_backfill_establishment_classification_on_listings_table.php');
    $objMigration->up();

    expect(DB::table('tbl_listings')->where('lst_id', $intTerminalId)->value('lst_reporting_mode'))->toBe('DIGITAL');
});

test('Dahican Surf Guides & Tours is reclassified as Recreation & Activities / Diving / Water Activity, and the rollback restores it', function () {
    $objMati = classificationMunicipality('City of Mati', 'MATI');
    $objTravel = classificationCategory('Travel & Tours');
    $objRecreation = Category::query()->where('cat_name', 'Recreation & Activities')->firstOrFail();
    $intSurfId = DB::table('tbl_listings')->insertGetId([
        'lst_slug' => 'dahican-surf-guides',
        'lst_uuid' => (string) Str::uuid(),
        'lst_name' => 'Dahican Surf Guides & Tours',
        'lst_category' => 'tour-guides',
        'cat_id' => $objTravel->cat_id,
        'lst_municipality' => $objMati->mun_name,
        'mun_id' => $objMati->mun_id,
        'lst_barangay' => 'Brgy. Dahican',
        'lst_status' => 'DRAFT',
    ]);

    $objMigration = require database_path('migrations/2026_10_07_170000_reclassify_dahican_surf_guides_listing.php');
    $objMigration->up();

    $objRow = DB::table('tbl_listings')->where('lst_id', $intSurfId)->first();
    expect($objRow->lst_category)->toBe('recreation-activities');
    expect($objRow->cat_id)->toBe($objRecreation->cat_id);
    expect($objRow->lst_type)->toBe('Diving / Water Activity');
    expect(Listing::query()->find($intSurfId)->isTourGuide())->toBeFalse();

    $objMigration->down();

    $objRow = DB::table('tbl_listings')->where('lst_id', $intSurfId)->first();
    expect($objRow->lst_category)->toBe('tour-guides');
    expect($objRow->cat_id)->toBe($objTravel->cat_id);
    expect($objRow->lst_type)->toBeNull();
});
