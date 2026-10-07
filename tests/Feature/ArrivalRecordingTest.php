<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — arrival recording.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\ArrivalOriginScope;
use App\Enums\ReportingMethod;
use App\Enums\UserRole;
use App\Models\Arrival;
use App\Models\Category;
use App\Models\Listing;
use App\Models\MonthlyArrivalReport;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Support\Str;

function arrivalRecordingListingFixture(array $overrides = []): Listing
{
    $category = Category::query()->firstOrCreate(
        ['cat_name' => 'Accommodation'],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );

    return Listing::query()->create(array_merge([
        'lst_slug' => Str::slug('arrival-fixture-'.Str::random(6)),
        'lst_name' => 'Arrival Recording Fixture Resort',
        'lst_category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'lst_municipality' => 'City of Mati',
        'lst_barangay' => 'Dahican',
        'lst_status' => 'PUBLISHED',
    ], $overrides));
}

/**
 * The establishment's account. An establishment with an active account
 * reports through Online iTOUR (Lgu\EstablishmentAdoptionController).
 */
function arrivalRecordingStaffUser(Listing $listing): User
{
    $listing->forceFill(['lst_reporting_mode' => ReportingMethod::OnlineItour])->save();

    return User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'usr_organization_name' => $listing->lst_name,
        'usr_organization_subtitle' => 'Brgy. Dahican, City of Mati',
        'lst_id' => $listing->lst_id,
    ]);
}

/**
 * A listing the public QR form accepts — Listing::isAcceptingRegistrations()
 * requires Online iTOUR reporting and a linked, active establishment account.
 */
function arrivalRecordingCheckinListingFixture(): Listing
{
    $listing = arrivalRecordingListingFixture();
    arrivalRecordingStaffUser($listing);

    return $listing;
}

// --- Counting rule: the companion grid includes the lead visitor, so total must be >= 1 ---

test('the public self-checkin form rejects a submission with an all-zero headcount', function () {
    $listing = arrivalRecordingCheckinListingFixture();

    $response = test()->postJson(route('checkin.store', $listing->lst_uuid), [
        'visitorName' => 'Jane Doe',
        'visitorContact' => '0912',
        'visitType' => 'Daytour',
        'male' => 0, 'female' => 0,
    ]);

    $response->assertStatus(422);
    expect(Arrival::query()->where('lst_id', $listing->lst_id)->count())->toBe(0);
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
    expect(Arrival::query()->where('lst_id', $listing->lst_id)->count())->toBe(0);
});

test('party_size equals the grid sum with no lead-visitor offset, on both recording paths', function () {
    $listing = arrivalRecordingListingFixture();
    $user = arrivalRecordingStaffUser($listing);

    test()->actingAs($user)->post(route('establishment.arrivals.store'), [
        'date' => now()->toDateString(),
        'visitType' => 'Daytour',
        'male' => 2, 'female' => 1, 'adults' => 3, 'local' => 3,
    ])->assertOk();

    $staffArrival = Arrival::query()->where('lst_id', $listing->lst_id)->first();
    expect($staffArrival->arr_party_size)->toBe(3);

    test()->post(route('checkin.store', $listing->lst_uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912', 'visitType' => 'Daytour',
        'male' => 1, 'female' => 1, 'adults' => 2, 'foreign' => 2,
    ])->assertOk();

    $selfCheckinArrival = Arrival::query()->where('lst_id', $listing->lst_id)->where('arr_source', 'self_checkin')->first();
    expect($selfCheckinArrival->arr_party_size)->toBe(2);
});

// --- Origin field validation ---

test('a within-province origin place must be a Davao Oriental municipality, not a province', function () {
    $listing = arrivalRecordingCheckinListingFixture();

    $response = test()->postJson(route('checkin.store', $listing->lst_uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912', 'visitType' => 'Daytour',
        'male' => 1, 'adults' => 1, 'local' => 1,
        'localOriginScope' => 'within_province',
        'localOriginPlace' => 'Davao del Sur',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('localOriginPlace');
});

test('localOriginScope is rejected when there are no local guests in the party', function () {
    $listing = arrivalRecordingCheckinListingFixture();

    $response = test()->postJson(route('checkin.store', $listing->lst_uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912', 'visitType' => 'Daytour',
        'male' => 1, 'adults' => 1, 'foreign' => 1, 'local' => 0,
        'localOriginScope' => 'within_province',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('localOriginScope');
});

test('foreignCountry is rejected when there are no foreign guests in the party', function () {
    $listing = arrivalRecordingCheckinListingFixture();

    $response = test()->postJson(route('checkin.store', $listing->lst_uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912', 'visitType' => 'Daytour',
        'male' => 1, 'adults' => 1, 'local' => 1, 'foreign' => 0,
        'foreignCountry' => 'Japan',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('foreignCountry');
});

// --- Origin fields are saved on both recording paths ---

test('origin fields are saved on the self-checkin path', function () {
    $listing = arrivalRecordingCheckinListingFixture();

    test()->post(route('checkin.store', $listing->lst_uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912', 'visitType' => 'Daytour',
        'male' => 1, 'female' => 1, 'adults' => 2, 'local' => 1, 'foreign' => 1,
        'localOriginScope' => 'outside_province',
        'localOriginPlace' => 'Davao del Sur',
        'foreignCountry' => 'Japan',
    ])->assertOk();

    $arrival = Arrival::query()->where('lst_id', $listing->lst_id)->first();
    expect($arrival->arr_local_origin_scope)->toBe(ArrivalOriginScope::OutsideProvince);
    expect($arrival->arr_local_origin_place)->toBe('Davao del Sur');
    expect($arrival->arr_foreign_country)->toBe('Japan');
});

test('origin fields are saved on the staff recording path', function () {
    $listing = arrivalRecordingListingFixture();
    $user = arrivalRecordingStaffUser($listing);

    test()->actingAs($user)->post(route('establishment.arrivals.store'), [
        'date' => now()->toDateString(),
        'visitType' => 'Daytour',
        'male' => 1, 'female' => 1, 'adults' => 2, 'local' => 1, 'foreign' => 1,
        'localOriginScope' => 'within_province',
        'foreignCountry' => 'South Korea',
    ])->assertOk();

    $arrival = Arrival::query()->where('lst_id', $listing->lst_id)->first();
    expect($arrival->arr_local_origin_scope)->toBe(ArrivalOriginScope::WithinProvince);
    expect($arrival->arr_local_origin_place)->toBeNull();
    expect($arrival->arr_foreign_country)->toBe('South Korea');
});

// --- Encoder: every arrival keeps who encoded it (CLAUDE.md 2.4.4) ---

test('the staff recording path stores the encoding user, while self-checkin leaves it null', function () {
    $listing = arrivalRecordingListingFixture();
    $user = arrivalRecordingStaffUser($listing);

    test()->actingAs($user)->post(route('establishment.arrivals.store'), [
        'date' => now()->toDateString(), 'visitType' => 'Daytour', 'male' => 1, 'adults' => 1, 'local' => 1,
    ])->assertOk();

    test()->post(route('checkin.store', $listing->lst_uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912', 'visitType' => 'Daytour', 'male' => 1, 'adults' => 1, 'local' => 1,
    ])->assertOk();

    $staffArrival = Arrival::query()->where('lst_id', $listing->lst_id)->where('arr_source', 'staff')->sole();
    $selfCheckinArrival = Arrival::query()->where('lst_id', $listing->lst_id)->where('arr_source', 'self_checkin')->sole();

    expect($staffArrival->recorded_by)->toBe($user->usr_id);
    expect($staffArrival->recorder->is($user))->toBeTrue();
    expect($selfCheckinArrival->recorded_by)->toBeNull();
});

test('an establishment staff user cannot record an arrival for another establishment through forged ids', function () {
    $ownListing = arrivalRecordingListingFixture(['lst_name' => 'Own Resort']);
    $otherListing = arrivalRecordingListingFixture(['lst_name' => 'Other Resort']);
    $owner = arrivalRecordingStaffUser($ownListing);

    test()->actingAs($owner)->postJson(route('establishment.arrivals.store'), [
        'date' => now()->toDateString(),
        'visitType' => 'Daytour',
        'male' => 1,
        'adults' => 1,
        'local' => 1,
        'listing_id' => $otherListing->lst_id,
        'municipality_id' => $otherListing->mun_id,
        'establishment_id' => $otherListing->lst_id,
    ])->assertOk();

    $arrival = Arrival::query()->sole();

    expect($arrival->lst_id)->toBe($ownListing->lst_id)
        ->and($arrival->lst_id)->not->toBe($otherListing->lst_id)
        ->and($arrival->recorded_by)->toBe($owner->usr_id);
});

// --- Within Davao Oriental: municipality / city (Phase 3) ---

test('a within-province municipality is saved on both recording paths', function () {
    Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $listing = arrivalRecordingListingFixture();
    $user = arrivalRecordingStaffUser($listing);

    test()->actingAs($user)->post(route('establishment.arrivals.store'), [
        'date' => now()->toDateString(), 'visitType' => 'Daytour', 'male' => 1, 'adults' => 1, 'local' => 1,
        'localOriginScope' => 'within_province', 'localOriginPlace' => 'City of Mati',
    ])->assertOk();

    test()->post(route('checkin.store', $listing->lst_uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912', 'visitType' => 'Overnight',
        'female' => 1, 'adults' => 1, 'local' => 1,
        'localOriginScope' => 'within_province', 'localOriginPlace' => 'City of Mati',
    ])->assertOk();

    expect(Arrival::query()->where('lst_id', $listing->lst_id)->pluck('arr_local_origin_place')->all())
        ->toBe(['City of Mati', 'City of Mati']);
});

test('a within-province scope with no municipality is still accepted', function () {
    $listing = arrivalRecordingCheckinListingFixture();

    test()->postJson(route('checkin.store', $listing->lst_uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912', 'visitType' => 'Daytour',
        'male' => 1, 'adults' => 1, 'local' => 1, 'localOriginScope' => 'within_province',
    ])->assertOk();

    expect(Arrival::query()->where('lst_id', $listing->lst_id)->sole()->arr_local_origin_place)->toBeNull();
});

test('the Top Origin Provinces breakdown never lists a within-province municipality', function () {
    $report = new MonthlyArrivalReport;
    $report->setRelation('arrivals', collect([
        new Arrival(['arr_party_local' => 3, 'arr_local_origin_scope' => 'outside_province', 'arr_local_origin_place' => 'Davao del Sur']),
        new Arrival(['arr_party_local' => 5, 'arr_local_origin_scope' => 'within_province', 'arr_local_origin_place' => 'City of Mati']),
    ]));

    $arrBreakdown = $report->originBreakdown();

    expect($arrBreakdown['topOriginPlaces']->all())->toBe(['Davao del Sur' => 3]);
    expect($arrBreakdown['withinProvince'])->toBe(5);
    expect($arrBreakdown['outsideProvince'])->toBe(3);
});

test('a headcount above the per-field maximum is rejected on both paths', function () {
    $listing = arrivalRecordingListingFixture();
    $user = arrivalRecordingStaffUser($listing);

    test()->actingAs($user)->postJson(route('establishment.arrivals.store'), [
        'date' => now()->toDateString(), 'visitType' => 'Daytour', 'male' => 1000,
    ])->assertJsonValidationErrors('male');

    test()->postJson(route('checkin.store', $listing->lst_uuid), [
        'visitorName' => 'Jane Doe', 'visitorContact' => '0912', 'visitType' => 'Daytour', 'male' => 1000,
    ])->assertJsonValidationErrors('male');

    expect(Arrival::query()->where('lst_id', $listing->lst_id)->count())->toBe(0);
});
