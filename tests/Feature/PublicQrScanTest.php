<?php

use App\Enums\ReportingMethod;
use App\Enums\UserRole;
use App\Models\Arrival;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function qrScanCategoryFixture(string $name, bool $qrEnabled): Category
{
    return Category::query()->firstOrCreate(
        ['cat_name' => $name],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => $qrEnabled]
    );
}

/**
 * A QR-ready listing. By default it is also adopted — Online iTOUR with a
 * linked, active establishment account — since
 * Listing::isAcceptingRegistrations() requires both. Without an account it
 * stays on the Manual/Paper default.
 */
function qrScanListingFixture(array $overrides = [], bool $blnWithAccount = true): Listing
{
    $category = qrScanCategoryFixture('Accommodation', true);

    $listing = Listing::query()->create(array_merge([
        'slug' => Str::slug('qr-scan-fixture-'.Str::random(6)),
        'name' => 'Botanika Nature Resort',
        'category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'municipality' => 'City of Mati',
        'barangay' => 'Dahican',
        'status' => 'PUBLISHED',
    ], $overrides));

    if ($blnWithAccount) {
        $listing->forceFill(['reporting_mode' => ReportingMethod::OnlineItour])->save();
        User::factory()->create([
            'role' => UserRole::Establishment,
            'organization_name' => $listing->name,
            'organization_subtitle' => 'Brgy. Dahican, City of Mati',
            'establishment_id' => $listing->id,
        ]);
    }

    return $listing;
}

test('scanning a QR-enabled, active establishment shows the registration form', function () {
    $listing = qrScanListingFixture();

    $response = test()->get(route('lgu.establishmentQr', ['establishment' => $listing->uuid]));

    $response->assertOk();
    $response->assertSee('establishment-qr-form', false);
});

test('scanning a Tour Guide record is refused', function () {
    $category = qrScanCategoryFixture('Travel & Tours', true);
    $listing = qrScanListingFixture([
        'name' => 'Dahican Surf Guides', 'cat_id' => $category->cat_id, 'type' => 'Tour Guide',
        'lat' => null, 'lng' => null,
    ]);

    $response = test()->get(route('lgu.establishmentQr', ['establishment' => $listing->uuid]));

    $response->assertOk();
    $response->assertSee('This establishment is not accepting registrations');
    $response->assertDontSee('establishment-qr-form', false);
});

test('scanning an Others-category record with QR scanning off is refused', function () {
    $category = qrScanCategoryFixture('Others', false);
    $listing = qrScanListingFixture(['name' => 'Misc Shop', 'cat_id' => $category->cat_id]);

    $response = test()->get(route('lgu.establishmentQr', ['establishment' => $listing->uuid]));

    $response->assertOk();
    $response->assertSee('This establishment is not accepting registrations');
});

test('scanning a suspended establishment is refused', function () {
    $listing = qrScanListingFixture(['status' => 'Suspended']);

    $response = test()->get(route('lgu.establishmentQr', ['establishment' => $listing->uuid]));

    $response->assertOk();
    $response->assertSee('This establishment is not accepting registrations');
});

test('submitting a check-in for a QR-disabled establishment saves nothing, even if posted directly', function () {
    $listing = qrScanListingFixture(['status' => 'Suspended']);

    $response = test()->post(route('checkin.store', $listing->uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912', 'visitType' => 'Daytour',
    ]);

    $response->assertStatus(422);
    expect(Arrival::query()->where('listing_id', $listing->id)->count())->toBe(0);
});

test('a legacy listing with no uuid is treated as not QR-enabled and does not crash the PTO directory', function () {
    // uuid is not mass-assignable (set only by Listing::booted()'s creating
    // hook), so a pre-uuid-era row is simulated with a raw update, the same
    // state 16 legacy listings were found in before being backfilled.
    $listing = qrScanListingFixture();
    DB::table('listings')->where('id', $listing->id)->update(['uuid' => null]);

    expect($listing->fresh()->isQrEnabled())->toBeFalse();

    $pto = User::factory()->create([
        'role' => UserRole::PtoAdministrator,
        'organization_name' => 'Provincial Tourism Office',
        'organization_subtitle' => 'Province of Davao Oriental',
    ]);
    test()->actingAs($pto)->get(route('pto.directory.index'))->assertOk();
});

test('a filled honeypot field is rejected and saves no arrival', function () {
    $listing = qrScanListingFixture();

    $response = test()->postJson(route('checkin.store', $listing->uuid), [
        'visitorName' => 'Jane Doe',
        'visitorContact' => '0912',
        'visitType' => 'Daytour',
        'website' => 'https://spam.example',
        'male' => 1, 'adults' => 1, 'local' => 1,
    ]);

    $response->assertStatus(422);
    expect(Arrival::query()->where('listing_id', $listing->id)->count())->toBe(0);
});

test('forged municipality and establishment ids cannot change QR arrival ownership', function () {
    $mati = Municipality::query()->create(['name' => 'City of Mati', 'code' => 'MATI']);
    $baganga = Municipality::query()->create(['name' => 'Baganga', 'code' => 'BAGANGA']);
    $matiListing = qrScanListingFixture(['municipality_id' => $mati->id]);
    $bagangaListing = qrScanListingFixture([
        'name' => 'Baganga Resort',
        'municipality' => 'Baganga',
        'municipality_id' => $baganga->id,
    ]);

    $response = test()->postJson(route('checkin.store', $matiListing->uuid), [
        'visitorName' => 'Jane Doe',
        'visitorContact' => '0912',
        'visitType' => 'Daytour',
        'male' => 1,
        'adults' => 1,
        'local' => 1,
        'municipality_id' => $bagangaListing->municipality_id,
        'establishment_id' => $bagangaListing->id,
    ]);

    $response->assertOk();

    $arrival = Arrival::query()->sole();

    expect($arrival->listing_id)->toBe($matiListing->id)
        ->and($arrival->listing->municipality_id)->toBe($matiListing->municipality_id)
        ->and($arrival->listing_id)->not->toBe($bagangaListing->id);
});

test('turning a category QR switch off then on keeps old arrivals and re-enables the same QR code', function () {
    $category = qrScanCategoryFixture('Accommodation', true);
    $listing = qrScanListingFixture(['cat_id' => $category->cat_id]);
    $listing->arrivals()->create([
        'source' => 'self_checkin', 'date' => now()->toDateString(), 'visitor_name' => 'Old Guest',
        'visitor_contact' => '0900', 'party_size' => 1, 'status' => 'Recorded',
    ]);

    $category->update(['cat_is_qr_enabled' => false]);
    test()->get(route('lgu.establishmentQr', ['establishment' => $listing->uuid]))
        ->assertSee('This establishment is not accepting registrations');
    expect(Arrival::query()->where('listing_id', $listing->id)->count())->toBe(1);

    $category->update(['cat_is_qr_enabled' => true]);
    test()->get(route('lgu.establishmentQr', ['establishment' => $listing->uuid]))
        ->assertSee('establishment-qr-form', false);
    expect($listing->fresh()->uuid)->toBe($listing->uuid);
    expect(Arrival::query()->where('listing_id', $listing->id)->count())->toBe(1);
});

test('scanning a listing with no linked establishment account is refused, and a direct post saves nothing', function () {
    $listing = qrScanListingFixture([], false);

    expect($listing->isQrEnabled())->toBeTrue();
    expect($listing->isAcceptingRegistrations())->toBeFalse();

    test()->get(route('lgu.establishmentQr', ['establishment' => $listing->uuid]))
        ->assertOk()
        ->assertSee('This establishment is not accepting registrations')
        ->assertDontSee('establishment-qr-form', false);

    test()->postJson(route('checkin.store', $listing->uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912', 'visitType' => 'Daytour', 'male' => 1, 'adults' => 1, 'local' => 1,
    ])->assertStatus(422);
    expect(Arrival::query()->where('listing_id', $listing->id)->count())->toBe(0);
});

test('an establishment with its own QR switch off is refused', function () {
    $listing = qrScanListingFixture();
    $listing->forceFill(['lst_is_qr_enabled' => false])->save();

    expect($listing->fresh()->isAcceptingRegistrations())->toBeFalse();

    test()->get(route('lgu.establishmentQr', ['establishment' => $listing->uuid]))
        ->assertSee('This establishment is not accepting registrations');
});

test('a new listing has its own QR switch on by default', function () {
    $listing = qrScanListingFixture();

    expect($listing->fresh()->lst_is_qr_enabled)->toBeTrue();
    expect($listing->fresh()->isAcceptingRegistrations())->toBeTrue();
});

test('the PTO directory offers no QR for a listing without a linked account', function () {
    $listing = qrScanListingFixture(['name' => 'No Account Inn'], false);

    $pto = User::factory()->create([
        'role' => UserRole::PtoAdministrator,
        'organization_name' => 'Provincial Tourism Office',
        'organization_subtitle' => 'Province of Davao Oriental',
    ]);

    test()->actingAs($pto)->get(route('pto.directory.index'))
        ->assertOk()
        ->assertDontSee('qr-view-'.$listing->id, false);
});

test('listings:backfill-uuids fills only missing uuids and never changes an existing one', function () {
    $listingWithUuid = qrScanListingFixture();
    $listingWithoutUuid = qrScanListingFixture(['name' => 'Legacy Inn'], false);
    $strExistingUuid = $listingWithUuid->uuid;
    DB::table('listings')->where('id', $listingWithoutUuid->id)->update(['uuid' => null]);

    test()->artisan('listings:backfill-uuids')->assertSuccessful();

    expect($listingWithUuid->fresh()->uuid)->toBe($strExistingUuid);
    expect($listingWithoutUuid->fresh()->uuid)->not->toBeNull();
    expect(Listing::query()->whereNull('uuid')->count())->toBe(0);
});

// --- Public check-in, Phase 3 ---

test('an unknown check-in uuid is a 404 for both the form and the submit', function () {
    $strUnknownUuid = (string) Str::uuid();

    test()->get(route('lgu.establishmentQr', ['establishment' => $strUnknownUuid]))->assertNotFound();
    test()->postJson(route('checkin.store', $strUnknownUuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912', 'visitType' => 'Daytour', 'male' => 1, 'adults' => 1, 'local' => 1,
    ])->assertNotFound();
});

test('the form offers visit type, the headcount, and the Davao Oriental municipality picker', function () {
    Municipality::query()->firstOrCreate(['code' => 'CATEEL'], ['name' => 'Cateel']);
    $listing = qrScanListingFixture();

    test()->get(route('lgu.establishmentQr', ['establishment' => $listing->uuid]))
        ->assertOk()
        ->assertSee('name="visitType" value="Daytour"', false)
        ->assertSee('name="visitType" value="Overnight"', false)
        ->assertSee('How many are in your group?')
        ->assertSee('Within Davao Oriental')
        ->assertSee('<option value="Cateel">Cateel</option>', false);
});

test('a valid QR check-in saves exactly one self-checkin row with today\'s date, visit type, contact, and no encoder', function () {
    $listing = qrScanListingFixture();

    test()->postJson(route('checkin.store', $listing->uuid), [
        'visitorName' => 'Maria Santos', 'visitorContact' => '0917 000 1111', 'visitType' => 'Overnight',
        'male' => 1, 'female' => 2, 'adults' => 2, 'children' => 1, 'local' => 2, 'foreign' => 1,
        'foreignCountry' => 'Japan',
    ])->assertOk()->assertJson(['message' => 'Registration submitted.']);

    $arrival = Arrival::query()->where('listing_id', $listing->id)->sole();
    expect($arrival->source->value)->toBe('self_checkin');
    expect($arrival->date->toDateString())->toBe(now()->toDateString());
    expect($arrival->visit_type)->toBe('Overnight');
    expect($arrival->visitor_name)->toBe('Maria Santos');
    expect($arrival->visitor_contact)->toBe('0917 000 1111');
    expect($arrival->party_size)->toBe(3);
    expect($arrival->recorded_by)->toBeNull();
});

test('the QR check-in requires a visit type, a name, and a contact number, and saves nothing without them', function () {
    $listing = qrScanListingFixture();

    test()->postJson(route('checkin.store', $listing->uuid), ['male' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['visitType', 'visitorName', 'visitorContact']);

    test()->postJson(route('checkin.store', $listing->uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912', 'visitType' => 'Weekend', 'male' => 1,
    ])->assertJsonValidationErrors('visitType');

    expect(Arrival::query()->where('listing_id', $listing->id)->count())->toBe(0);
});

test('the public check-in submit is rate limited per visitor and establishment', function () {
    $listing = qrScanListingFixture();
    $arrPayload = ['visitorName' => 'Jane Doe', 'visitorContact' => '0912', 'visitType' => 'Daytour', 'male' => 1, 'adults' => 1, 'local' => 1];

    for ($intAttempt = 1; $intAttempt <= 10; $intAttempt++) {
        test()->postJson(route('checkin.store', $listing->uuid), $arrPayload)->assertOk();
    }

    test()->postJson(route('checkin.store', $listing->uuid), $arrPayload)->assertStatus(429);
    expect(Arrival::query()->where('listing_id', $listing->id)->count())->toBe(10);
});

test('end to end: the QR on the poster leads to the form, and a submitted check-in shows up in the establishment\'s Arrival Records', function () {
    $listing = qrScanListingFixture(['name' => 'End To End Resort']);
    $owner = $listing->establishmentUser;
    $strCheckinUrl = app(QrCodeService::class)->buildCheckinUrl($listing);

    // The poster encodes the check-in URL; following it opens the form.
    test()->actingAs($owner)->get(route('qrCodes.poster', $listing))->assertOk()->assertSee(preg_replace('#^https?://#', '', $strCheckinUrl));
    test()->get(parse_url($strCheckinUrl, PHP_URL_PATH))->assertOk()->assertSee('establishment-qr-form', false);

    test()->postJson(route('checkin.store', $listing->uuid), [
        'visitorName' => 'Phone Scan Visitor', 'visitorContact' => '0912', 'visitType' => 'Daytour',
        'female' => 1, 'adults' => 1, 'local' => 1,
    ])->assertOk();

    test()->actingAs($owner)->get(route('establishment.arrivals.index'))
        ->assertOk()
        ->assertSee('Phone Scan Visitor');
});
