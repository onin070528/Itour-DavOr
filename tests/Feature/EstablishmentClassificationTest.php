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
    return Municipality::query()->create(['name' => $strName, 'code' => $strCode]);
}

function classificationLgu(Municipality $objMunicipality): User
{
    return User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_name' => "{$objMunicipality->name} Tourism Office",
        'organization_subtitle' => $objMunicipality->name,
        'municipality_id' => $objMunicipality->id,
    ]);
}

function classificationPto(): User
{
    return User::factory()->create(['role' => UserRole::PtoAdministrator]);
}

function classificationListing(Municipality $objMunicipality, string $strCategory): Listing
{
    return Listing::query()->create([
        'slug' => Str::slug("{$objMunicipality->name}-{$strCategory}-".Str::random(6)),
        'name' => "{$objMunicipality->name} Test {$strCategory}",
        'category' => $strCategory,
        'municipality' => $objMunicipality->name,
        'municipality_id' => $objMunicipality->id,
        'barangay' => 'Poblacion',
        'status' => 'DRAFT',
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

    expect((new Listing(['type' => 'Tour Guide Service']))->isTourGuide())->toBeTrue();
    expect((new Listing(['type' => 'Tour Guide']))->isTourGuide())->toBeTrue();
    expect((new Listing(['type' => 'Tour Operator']))->isTourGuide())->toBeFalse();
});

test('PTO saving an establishment with a type from its own category stores the type', function () {
    classificationMunicipality('City of Mati', 'MATI');
    $objAccommodation = classificationCategory('Accommodation');

    test()->actingAs(classificationPto())
        ->post(route('pto.directory.store'), classificationPtoPayload($objAccommodation, ['type' => 'Resort']))
        ->assertSessionHasNoErrors();

    expect(Listing::query()->where('name', 'Classification Test Resort')->value('type'))->toBe('Resort');
});

test('PTO saving an establishment with a type from another category is rejected', function () {
    classificationMunicipality('City of Mati', 'MATI');
    $objAccommodation = classificationCategory('Accommodation');

    test()->actingAs(classificationPto())
        ->post(route('pto.directory.store'), classificationPtoPayload($objAccommodation, ['type' => 'Restaurant']))
        ->assertSessionHasErrors('type');

    expect(Listing::query()->where('name', 'Classification Test Resort')->exists())->toBeFalse();
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

    expect($objListing->reporting_mode)->toBe(ReportingMethod::ManualPaper);
    expect($objListing->reportingMethod()->label())->toBe('Manual/Paper');
});

test('reporting_mode cannot be mass-assigned', function () {
    $objListing = new Listing(['reporting_mode' => ReportingMethod::OnlineItour->value]);

    expect($objListing->getAttributes())->not->toHaveKey('reporting_mode');
});

test('activating an establishment\'s account switches it to Online iTOUR', function () {
    Mail::fake();
    $objMati = classificationMunicipality('City of Mati', 'MATI');
    $objListing = classificationListing($objMati, 'accommodation');

    test()->actingAs(classificationLgu($objMati))->post(route('lgu.directory.establishments.switchToOnline', $objListing), [
        'account_name' => 'Juan Dela Cruz',
        'account_email' => 'online-inn@example.test',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($objListing->fresh()->reporting_mode)->toBe(ReportingMethod::OnlineItour);
});

test('an LGU user cannot move a listing to another municipality, even through the model', function () {
    $objMati = classificationMunicipality('City of Mati', 'MATI');
    $objBaganga = classificationMunicipality('Baganga', 'BAG');
    $objListing = classificationListing($objMati, 'accommodation');
    $objLgu = classificationLgu($objMati);

    test()->actingAs($objLgu);

    expect(fn () => $objListing->update(['municipality_id' => $objBaganga->id]))->toThrow(AuthorizationException::class);
    expect($objListing->fresh()->municipality_id)->toBe($objMati->id);
    expect(SecurityLog::query()->where('user_id', $objLgu->id)->exists())->toBeTrue();
});

test('PTO and non-request contexts can still reassign a listing municipality', function () {
    $objMati = classificationMunicipality('City of Mati', 'MATI');
    $objBaganga = classificationMunicipality('Baganga', 'BAG');
    $objListing = classificationListing($objMati, 'accommodation');

    // Summary comment: no signed-in user (seeders, console commands).
    $objListing->update(['municipality_id' => $objBaganga->id]);
    expect($objListing->fresh()->municipality_id)->toBe($objBaganga->id);

    test()->actingAs(User::factory()->create(['role' => UserRole::PtoAdministrator]));
    $objListing->update(['municipality_id' => $objMati->id]);
    expect($objListing->fresh()->municipality_id)->toBe($objMati->id);
});

test('the backfill fills only the approved values and its rollback restores them', function () {
    $objMati = classificationMunicipality('City of Mati', 'MATI');
    $objAccommodation = classificationCategory('Accommodation');

    // Summary comment: rows shaped like the pre-backfill data (old DIGITAL default, no type).
    $fnMakeRow = function (string $strSlug, string $strCategory, ?int $intCategoryId) use ($objMati): int {
        return DB::table('listings')->insertGetId([
            'slug' => $strSlug,
            'uuid' => (string) Str::uuid(),
            'name' => $strSlug,
            'category' => $strCategory,
            'cat_id' => $intCategoryId,
            'municipality' => $objMati->name,
            'municipality_id' => $objMati->id,
            'barangay' => 'Poblacion',
            'status' => 'DRAFT',
            'reporting_mode' => ReportingMethod::OnlineItour->value,
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

    $fnRow = fn (int $intId) => DB::table('listings')->where('id', $intId)->first();

    expect($fnRow($intBotanikaId)->type)->toBe('Resort');
    expect($fnRow($intBotanikaId)->reporting_mode)->toBe('DIGITAL');
    expect($fnRow($intTerminalId)->type)->toBe('Van / Shuttle Service');
    expect($fnRow($intTerminalId)->reporting_mode)->toBe('PAPER_LGU');
    expect($fnRow($intSurfId)->type)->toBeNull();
    expect($fnRow($intDemoId)->cat_id)->toBe($objAccommodation->cat_id);
    expect($fnRow($intDemoId)->reporting_mode)->toBe('PAPER_LGU');
    expect($fnRow($intMismatchId)->type)->toBeNull();

    $objMigration->down();

    expect($fnRow($intBotanikaId)->type)->toBeNull();
    expect($fnRow($intTerminalId)->reporting_mode)->toBe('DIGITAL');
    expect($fnRow($intDemoId)->cat_id)->toBeNull();
    expect($fnRow($intDemoId)->reporting_mode)->toBe('DIGITAL');
});

test('the backfill never switches an establishment with a linked account to Manual/Paper', function () {
    $objMati = classificationMunicipality('City of Mati', 'MATI');
    $intTerminalId = DB::table('listings')->insertGetId([
        'slug' => 'tourist-transport-terminal',
        'uuid' => (string) Str::uuid(),
        'name' => 'Terminal',
        'category' => 'transportation',
        'municipality' => $objMati->name,
        'municipality_id' => $objMati->id,
        'barangay' => 'Poblacion',
        'status' => 'DRAFT',
        'reporting_mode' => ReportingMethod::OnlineItour->value,
    ]);
    User::factory()->create(['role' => UserRole::Establishment, 'municipality_id' => $objMati->id, 'establishment_id' => $intTerminalId]);

    $objMigration = require database_path('migrations/2026_10_07_160100_backfill_establishment_classification_on_listings_table.php');
    $objMigration->up();

    expect(DB::table('listings')->where('id', $intTerminalId)->value('reporting_mode'))->toBe('DIGITAL');
});

test('Dahican Surf Guides & Tours is reclassified as Recreation & Activities / Diving / Water Activity, and the rollback restores it', function () {
    $objMati = classificationMunicipality('City of Mati', 'MATI');
    $objTravel = classificationCategory('Travel & Tours');
    $objRecreation = Category::query()->where('cat_name', 'Recreation & Activities')->firstOrFail();
    $intSurfId = DB::table('listings')->insertGetId([
        'slug' => 'dahican-surf-guides',
        'uuid' => (string) Str::uuid(),
        'name' => 'Dahican Surf Guides & Tours',
        'category' => 'tour-guides',
        'cat_id' => $objTravel->cat_id,
        'municipality' => $objMati->name,
        'municipality_id' => $objMati->id,
        'barangay' => 'Brgy. Dahican',
        'status' => 'DRAFT',
    ]);

    $objMigration = require database_path('migrations/2026_10_07_170000_reclassify_dahican_surf_guides_listing.php');
    $objMigration->up();

    $objRow = DB::table('listings')->where('id', $intSurfId)->first();
    expect($objRow->category)->toBe('recreation-activities');
    expect($objRow->cat_id)->toBe($objRecreation->cat_id);
    expect($objRow->type)->toBe('Diving / Water Activity');
    expect(Listing::query()->find($intSurfId)->isTourGuide())->toBeFalse();

    $objMigration->down();

    $objRow = DB::table('listings')->where('id', $intSurfId)->first();
    expect($objRow->category)->toBe('tour-guides');
    expect($objRow->cat_id)->toBe($objTravel->cat_id);
    expect($objRow->type)->toBeNull();
});
