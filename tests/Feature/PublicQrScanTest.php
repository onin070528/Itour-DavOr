<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — public qr scan.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Arrival;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function qrScanCategoryFixture(string $name, bool $qrEnabled): Category
{
    return Category::query()->firstOrCreate(
        ['cat_name' => $name],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => $qrEnabled]
    );
}

function qrScanListingFixture(array $overrides = []): Listing
{
    $category = qrScanCategoryFixture('Accommodation', true);

    return Listing::query()->create(array_merge([
        'lst_slug' => Str::slug('qr-scan-fixture-'.Str::random(6)),
        'lst_name' => 'Botanika Nature Resort',
        'lst_category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'lst_municipality' => 'City of Mati',
        'lst_barangay' => 'Dahican',
        'lst_status' => 'PUBLISHED',
    ], $overrides));
}

test('scanning a QR-enabled, active establishment shows the registration form', function () {
    $listing = qrScanListingFixture();

    $response = test()->get(route('lgu.establishmentQr', ['establishment' => $listing->lst_uuid]));

    $response->assertOk();
    $response->assertSee('establishment-qr-form', false);
});

test('scanning a Tour Guide record is refused', function () {
    $category = qrScanCategoryFixture('Travel & Tours', true);
    $listing = qrScanListingFixture([
        'lst_name' => 'Dahican Surf Guides', 'cat_id' => $category->cat_id, 'lst_type' => 'Tour Guide',
        'lst_lat' => null, 'lst_lng' => null,
    ]);

    $response = test()->get(route('lgu.establishmentQr', ['establishment' => $listing->lst_uuid]));

    $response->assertOk();
    $response->assertSee('This establishment is not accepting registrations');
    $response->assertDontSee('establishment-qr-form', false);
});

test('scanning an Others-category record with QR scanning off is refused', function () {
    $category = qrScanCategoryFixture('Others', false);
    $listing = qrScanListingFixture(['lst_name' => 'Misc Shop', 'cat_id' => $category->cat_id]);

    $response = test()->get(route('lgu.establishmentQr', ['establishment' => $listing->lst_uuid]));

    $response->assertOk();
    $response->assertSee('This establishment is not accepting registrations');
});

test('scanning a suspended establishment is refused', function () {
    $listing = qrScanListingFixture(['lst_status' => 'Suspended']);

    $response = test()->get(route('lgu.establishmentQr', ['establishment' => $listing->lst_uuid]));

    $response->assertOk();
    $response->assertSee('This establishment is not accepting registrations');
});

test('submitting a check-in for a QR-disabled establishment saves nothing, even if posted directly', function () {
    $listing = qrScanListingFixture(['lst_status' => 'Suspended']);

    $response = test()->post(route('checkin.store', $listing->lst_uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912',
    ]);

    $response->assertStatus(422);
    expect(Arrival::query()->where('lst_id', $listing->lst_id)->count())->toBe(0);
});

test('a legacy listing with no uuid is treated as not QR-enabled and does not crash the PTO directory', function () {
    // uuid is not mass-assignable (set only by Listing::booted()'s creating
    // hook), so a pre-uuid-era row is simulated with a raw update, the same
    // state 16 legacy listings were found in before being backfilled.
    $listing = qrScanListingFixture();
    DB::table('tbl_listings')->where('lst_id', $listing->lst_id)->update(['lst_uuid' => null]);

    expect($listing->fresh()->isQrEnabled())->toBeFalse();

    $pto = User::factory()->create([
        'usr_role' => UserRole::PtoAdministrator,
        'usr_organization_name' => 'Provincial Tourism Office',
        'usr_organization_subtitle' => 'Province of Davao Oriental',
    ]);
    test()->actingAs($pto)->get(route('pto.directory.index'))->assertOk();
});

test('a filled honeypot field is rejected and saves no arrival', function () {
    $listing = qrScanListingFixture();

    $response = test()->postJson(route('checkin.store', $listing->lst_uuid), [
        'visitorName' => 'Jane Doe',
        'visitorContact' => '0912',
        'website' => 'https://spam.example',
        'male' => 1,
    ]);

    $response->assertStatus(422);
    expect(Arrival::query()->where('lst_id', $listing->lst_id)->count())->toBe(0);
});

test('turning a category QR switch off then on keeps old arrivals and re-enables the same QR code', function () {
    $category = qrScanCategoryFixture('Accommodation', true);
    $listing = qrScanListingFixture(['cat_id' => $category->cat_id]);
    $listing->arrivals()->create([
        'arr_source' => 'self_checkin', 'arr_date' => now()->toDateString(), 'arr_visitor_name' => 'Old Guest',
        'arr_visitor_contact' => '0900', 'arr_party_size' => 1, 'arr_status' => 'Recorded',
    ]);

    $category->update(['cat_is_qr_enabled' => false]);
    test()->get(route('lgu.establishmentQr', ['establishment' => $listing->lst_uuid]))
        ->assertSee('This establishment is not accepting registrations');
    expect(Arrival::query()->where('lst_id', $listing->lst_id)->count())->toBe(1);

    $category->update(['cat_is_qr_enabled' => true]);
    test()->get(route('lgu.establishmentQr', ['establishment' => $listing->lst_uuid]))
        ->assertSee('establishment-qr-form', false);
    expect($listing->fresh()->lst_uuid)->toBe($listing->lst_uuid);
    expect(Arrival::query()->where('lst_id', $listing->lst_id)->count())->toBe(1);
});
