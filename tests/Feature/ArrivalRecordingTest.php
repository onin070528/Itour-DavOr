<?php

use App\Enums\ArrivalOriginScope;
use App\Enums\UserRole;
use App\Models\Arrival;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Str;

function arrivalRecordingListingFixture(array $overrides = []): Listing
{
    $category = Category::query()->firstOrCreate(
        ['cat_name' => 'Accommodation'],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );

    return Listing::query()->create(array_merge([
        'slug' => Str::slug('arrival-fixture-'.Str::random(6)),
        'name' => 'Arrival Recording Fixture Resort',
        'category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'municipality' => 'City of Mati',
        'barangay' => 'Dahican',
        'status' => 'PUBLISHED',
    ], $overrides));
}

function arrivalRecordingStaffUser(Listing $listing): User
{
    return User::factory()->create([
        'role' => UserRole::Establishment,
        'organization_name' => $listing->name,
        'organization_subtitle' => 'Brgy. Dahican, City of Mati',
        'establishment_id' => $listing->id,
    ]);
}

// --- Counting rule: the companion grid includes the lead visitor, so total must be >= 1 ---

test('the public self-checkin form rejects a submission with an all-zero headcount', function () {
    $listing = arrivalRecordingListingFixture();

    $response = test()->postJson(route('checkin.store', $listing->uuid), [
        'visitorName' => 'Jane Doe',
        'visitorContact' => '0912',
        'male' => 0, 'female' => 0,
    ]);

    $response->assertStatus(422);
    expect(Arrival::query()->where('listing_id', $listing->id)->count())->toBe(0);
});

test('the staff Record Arrival form rejects a submission with an all-zero headcount', function () {
    $listing = arrivalRecordingListingFixture();
    $user = arrivalRecordingStaffUser($listing);

    $response = test()->actingAs($user)->postJson(route('establishment.arrivals.store'), [
        'date' => now()->toDateString(),
        'visitType' => 'Daytour',
        'male' => 0, 'female' => 0,
    ]);

    $response->assertStatus(422);
    expect(Arrival::query()->where('listing_id', $listing->id)->count())->toBe(0);
});

test('party_size equals the grid sum with no lead-visitor offset, on both recording paths', function () {
    $listing = arrivalRecordingListingFixture();
    $user = arrivalRecordingStaffUser($listing);

    test()->actingAs($user)->post(route('establishment.arrivals.store'), [
        'date' => now()->toDateString(),
        'visitType' => 'Daytour',
        'male' => 2, 'female' => 1, 'local' => 3,
    ])->assertOk();

    $staffArrival = Arrival::query()->where('listing_id', $listing->id)->first();
    expect($staffArrival->party_size)->toBe(3);

    test()->post(route('checkin.store', $listing->uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912',
        'male' => 1, 'female' => 1, 'foreign' => 2,
    ])->assertOk();

    $selfCheckinArrival = Arrival::query()->where('listing_id', $listing->id)->where('source', 'self_checkin')->first();
    expect($selfCheckinArrival->party_size)->toBe(2);
});

// --- Origin field validation ---

test('local_origin_place is rejected unless local_origin_scope is outside_province', function () {
    $listing = arrivalRecordingListingFixture();

    $response = test()->postJson(route('checkin.store', $listing->uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912',
        'male' => 1, 'local' => 1,
        'localOriginScope' => 'within_province',
        'localOriginPlace' => 'Davao del Sur',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('localOriginPlace');
});

test('localOriginScope is rejected when there are no local guests in the party', function () {
    $listing = arrivalRecordingListingFixture();

    $response = test()->postJson(route('checkin.store', $listing->uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912',
        'male' => 1, 'foreign' => 1, 'local' => 0,
        'localOriginScope' => 'within_province',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('localOriginScope');
});

test('foreignCountry is rejected when there are no foreign guests in the party', function () {
    $listing = arrivalRecordingListingFixture();

    $response = test()->postJson(route('checkin.store', $listing->uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912',
        'male' => 1, 'local' => 1, 'foreign' => 0,
        'foreignCountry' => 'Japan',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('foreignCountry');
});

// --- Origin fields are saved on both recording paths ---

test('origin fields are saved on the self-checkin path', function () {
    $listing = arrivalRecordingListingFixture();

    test()->post(route('checkin.store', $listing->uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912',
        'male' => 1, 'local' => 1, 'foreign' => 1,
        'localOriginScope' => 'outside_province',
        'localOriginPlace' => 'Davao del Sur',
        'foreignCountry' => 'Japan',
    ])->assertOk();

    $arrival = Arrival::query()->where('listing_id', $listing->id)->first();
    expect($arrival->local_origin_scope)->toBe(ArrivalOriginScope::OutsideProvince);
    expect($arrival->local_origin_place)->toBe('Davao del Sur');
    expect($arrival->foreign_country)->toBe('Japan');
});

test('origin fields are saved on the staff recording path', function () {
    $listing = arrivalRecordingListingFixture();
    $user = arrivalRecordingStaffUser($listing);

    test()->actingAs($user)->post(route('establishment.arrivals.store'), [
        'date' => now()->toDateString(),
        'visitType' => 'Daytour',
        'male' => 1, 'local' => 1, 'foreign' => 1,
        'localOriginScope' => 'within_province',
        'foreignCountry' => 'South Korea',
    ])->assertOk();

    $arrival = Arrival::query()->where('listing_id', $listing->id)->first();
    expect($arrival->local_origin_scope)->toBe(ArrivalOriginScope::WithinProvince);
    expect($arrival->local_origin_place)->toBeNull();
    expect($arrival->foreign_country)->toBe('South Korea');
});
