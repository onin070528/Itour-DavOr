<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — establishment pages.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\ReportingMethod;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;

/**
 * The merged Profile/Photos page resolves the account's listing by FK
 * (establishment_id), not by name-matching — so every establishment test
 * fixture needs a real backing Listing, not just the account fields.
 */
test('every Establishment page renders for an establishment with data', function (string $routeName) {
    $user = actingAsEstablishment('Botanika Nature Resort', 'Brgy. Dahican, City of Mati');

    test()->actingAs($user)->get(route($routeName))->assertOk();
})->with([
    'establishment.dashboard',
    'establishment.profile',
    'establishment.qr',
    'establishment.arrivals.record',
    'establishment.arrivals.index',
    'establishment.feedback.index',
    'establishment.feedback.analytics',
    'establishment.settings',
]);

test('every Establishment page renders for an establishment with no mock data (empty states)', function (string $routeName) {
    $user = actingAsEstablishment('A Brand New Homestay');

    test()->actingAs($user)->get(route($routeName))->assertOk();
})->with([
    'establishment.dashboard',
    'establishment.profile',
    'establishment.qr',
    'establishment.arrivals.record',
    'establishment.arrivals.index',
    'establishment.feedback.index',
    'establishment.feedback.analytics',
    'establishment.settings',
]);

test('the dashboard only shows data scoped to the account\'s own establishment', function () {
    $user = actingAsEstablishment('Badjao Seafront Restaurant');

    $response = test()->actingAs($user)->get(route('establishment.dashboard'));

    $response->assertOk();
    $response->assertSee('Badjao Seafront Restaurant');
    // Recent activity mentioning Botanika Nature Resort must not leak into another establishment's dashboard.
    $response->assertDontSee('Botanika Nature Resort filed 4 new arrivals');
});

test('feedback and arrival records are limited to the account\'s own establishment', function () {
    // Create category for establishments
    $category = Category::query()->firstOrCreate(
        ['cat_name' => 'Accommodation'],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );

    // Establishment 1: Botanika (create listing and seed arrivals/feedback)
    $botanikaListing = Listing::query()->create([
        'lst_slug' => 'botanika-nature-resort',
        'lst_name' => 'Botanika Nature Resort',
        'lst_category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'lst_municipality' => 'City of Mati',
        'lst_barangay' => 'Dahican',
        'lst_status' => 'PUBLISHED',
    ]);

    // Seed arrivals for Botanika (matching ArrivalSeeder data)
    $botanikaListing->arrivals()->createMany([
        [
            'arr_source' => 'staff',
            'arr_date' => '2026-08-22',
            'arr_visitor_name' => 'Kim Soo-jin',
            'arr_gender' => 'Female',
            'arr_classification' => 'Foreign',
            'arr_remarks' => 'Celebrating a birthday',
            'arr_status' => 'Recorded',
            'arr_party_size' => 1,
        ],
        [
            'arr_source' => 'staff',
            'arr_date' => '2026-08-22',
            'arr_visitor_name' => null,
            'arr_gender' => 'Male',
            'arr_classification' => 'Domestic (Other Province)',
            'arr_remarks' => null,
            'arr_status' => 'Recorded',
            'arr_party_size' => 1,
        ],
    ]);

    $botanikaUser = User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'usr_organization_name' => 'Botanika Nature Resort',
        'usr_organization_subtitle' => 'Brgy. Dahican, City of Mati',
        'lst_id' => $botanikaListing->lst_id,
    ]);

    // Establishment 2: Badjao (no seeded data)
    $badjaoListing = Listing::query()->create([
        'lst_slug' => 'badjao-seafront',
        'lst_name' => 'Badjao Seafront Restaurant',
        'lst_category' => 'restaurants',
        'cat_id' => $category->cat_id,
        'lst_municipality' => 'City of Mati',
        'lst_barangay' => 'Dahican',
        'lst_status' => 'PUBLISHED',
    ]);

    $badjaoUser = User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'usr_organization_name' => 'Badjao Seafront Restaurant',
        'usr_organization_subtitle' => 'Brgy. Dahican, City of Mati',
        'lst_id' => $badjaoListing->lst_id,
    ]);

    // Botanika user should see their own feedback and arrivals
    $feedback = test()->actingAs($botanikaUser)->get(route('establishment.feedback.index'));
    $feedback->assertOk();
    $feedback->assertSee('Beautiful sunrise from the room');
    // Feedback for a different establishment must not appear
    $feedback->assertDontSee('Lami kaayo ang kinilaw');

    $arrivals = test()->actingAs($botanikaUser)->get(route('establishment.arrivals.index'));
    $arrivals->assertOk();
    $arrivals->assertSee('Kim Soo-jin');

    // Badjao user should NOT see Botanika's data
    $badjaoFeedback = test()->actingAs($badjaoUser)->get(route('establishment.feedback.index'));
    $badjaoFeedback->assertOk();
    $badjaoFeedback->assertDontSee('Beautiful sunrise from the room');
    $badjaoFeedback->assertDontSee('Kim Soo-jin');

    $badjaoArrivals = test()->actingAs($badjaoUser)->get(route('establishment.arrivals.index'));
    $badjaoArrivals->assertOk();
    $badjaoArrivals->assertDontSee('Kim Soo-jin');
});

test('two establishments with the same display name do not see each other\'s arrivals', function () {
    $category = Category::query()->firstOrCreate(
        ['cat_name' => 'Accommodation'],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );

    $sharedName = 'Paradise Resort';

    $listingA = Listing::query()->create([
        'lst_slug' => 'paradise-resort-a',
        'lst_name' => $sharedName,
        'lst_category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'lst_municipality' => 'City of Mati',
        'lst_barangay' => 'Dahican',
        'lst_status' => 'PUBLISHED',
    ]);
    $listingB = Listing::query()->create([
        'lst_slug' => 'paradise-resort-b',
        'lst_name' => $sharedName,
        'lst_category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'lst_municipality' => 'Baganga',
        'lst_barangay' => 'Poblacion',
        'lst_status' => 'PUBLISHED',
    ]);

    $listingA->forceFill(['lst_reporting_mode' => ReportingMethod::OnlineItour])->save();

    $listingA->arrivals()->create([
        'arr_source' => 'staff', 'arr_date' => '2026-08-22', 'arr_visitor_name' => 'Guest At A',
        'arr_party_male' => 1, 'arr_party_size' => 1, 'arr_status' => 'Recorded',
    ]);
    $listingB->arrivals()->create([
        'arr_source' => 'staff', 'arr_date' => '2026-08-22', 'arr_visitor_name' => 'Guest At B',
        'arr_party_male' => 1, 'arr_party_size' => 1, 'arr_status' => 'Recorded',
    ]);

    $userA = User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'usr_organization_name' => $sharedName,
        'usr_organization_subtitle' => 'Brgy. Dahican, City of Mati',
        'lst_id' => $listingA->lst_id,
    ]);
    $userB = User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'usr_organization_name' => $sharedName,
        'usr_organization_subtitle' => 'Brgy. Poblacion, Baganga',
        'lst_id' => $listingB->lst_id,
    ]);

    $arrivalsA = test()->actingAs($userA)->get(route('establishment.arrivals.index'));
    $arrivalsA->assertOk();
    $arrivalsA->assertSee('Guest At A');
    $arrivalsA->assertDontSee('Guest At B');

    $arrivalsB = test()->actingAs($userB)->get(route('establishment.arrivals.index'));
    $arrivalsB->assertOk();
    $arrivalsB->assertSee('Guest At B');
    $arrivalsB->assertDontSee('Guest At A');

    $qrA = test()->actingAs($userA)->get(route('establishment.qr'));
    $qrA->assertOk();
    $qrA->assertSee(route('lgu.establishmentQr', ['establishment' => $listingA->lst_uuid]), false);
    $qrA->assertDontSee($listingB->lst_uuid);
});

test('an establishment user cannot access PTO or LGU routes', function () {
    $user = actingAsEstablishment('Botanika Nature Resort', 'Brgy. Dahican, City of Mati');

    test()->actingAs($user)->get(route('pto.dashboard'))->assertForbidden();
    test()->actingAs($user)->get(route('lgu.dashboard'))->assertForbidden();
});
