<?php

use App\Models\Arrival;
use App\Models\Category;
use App\Models\Listing;
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
        'slug' => Str::slug('qr-scan-fixture-'.Str::random(6)),
        'name' => 'Botanika Nature Resort',
        'category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'municipality' => 'City of Mati',
        'barangay' => 'Dahican',
        'status' => 'PUBLISHED',
    ], $overrides));
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
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912',
    ]);

    $response->assertStatus(422);
    expect(Arrival::query()->where('listing_id', $listing->id)->count())->toBe(0);
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
