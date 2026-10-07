<?php

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
        'slug' => 'botanika-nature-resort',
        'name' => 'Botanika Nature Resort',
        'category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'municipality' => 'City of Mati',
        'barangay' => 'Dahican',
        'status' => 'PUBLISHED',
    ]);

    // Seed arrivals for Botanika (matching ArrivalSeeder data)
    $botanikaListing->arrivals()->createMany([
        [
            'source' => 'staff',
            'date' => '2026-08-22',
            'visitor_name' => 'Kim Soo-jin',
            'gender' => 'Female',
            'classification' => 'Foreign',
            'remarks' => 'Celebrating a birthday',
            'status' => 'Recorded',
            'party_size' => 1,
        ],
        [
            'source' => 'staff',
            'date' => '2026-08-22',
            'visitor_name' => null,
            'gender' => 'Male',
            'classification' => 'Domestic (Other Province)',
            'remarks' => null,
            'status' => 'Recorded',
            'party_size' => 1,
        ],
    ]);

    $botanikaUser = User::factory()->create([
        'role' => UserRole::Establishment,
        'organization_name' => 'Botanika Nature Resort',
        'organization_subtitle' => 'Brgy. Dahican, City of Mati',
        'establishment_id' => $botanikaListing->id,
    ]);

    // Establishment 2: Badjao (no seeded data)
    $badjaoListing = Listing::query()->create([
        'slug' => 'badjao-seafront',
        'name' => 'Badjao Seafront Restaurant',
        'category' => 'restaurants',
        'cat_id' => $category->cat_id,
        'municipality' => 'City of Mati',
        'barangay' => 'Dahican',
        'status' => 'PUBLISHED',
    ]);

    $badjaoUser = User::factory()->create([
        'role' => UserRole::Establishment,
        'organization_name' => 'Badjao Seafront Restaurant',
        'organization_subtitle' => 'Brgy. Dahican, City of Mati',
        'establishment_id' => $badjaoListing->id,
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
        'slug' => 'paradise-resort-a',
        'name' => $sharedName,
        'category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'municipality' => 'City of Mati',
        'barangay' => 'Dahican',
        'status' => 'PUBLISHED',
    ]);
    $listingB = Listing::query()->create([
        'slug' => 'paradise-resort-b',
        'name' => $sharedName,
        'category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'municipality' => 'Baganga',
        'barangay' => 'Poblacion',
        'status' => 'PUBLISHED',
    ]);

    $listingA->forceFill(['reporting_mode' => ReportingMethod::OnlineItour])->save();

    $listingA->arrivals()->create([
        'source' => 'staff', 'date' => '2026-08-22', 'visitor_name' => 'Guest At A',
        'party_male' => 1, 'party_size' => 1, 'status' => 'Recorded',
    ]);
    $listingB->arrivals()->create([
        'source' => 'staff', 'date' => '2026-08-22', 'visitor_name' => 'Guest At B',
        'party_male' => 1, 'party_size' => 1, 'status' => 'Recorded',
    ]);

    $userA = User::factory()->create([
        'role' => UserRole::Establishment,
        'organization_name' => $sharedName,
        'organization_subtitle' => 'Brgy. Dahican, City of Mati',
        'establishment_id' => $listingA->id,
    ]);
    $userB = User::factory()->create([
        'role' => UserRole::Establishment,
        'organization_name' => $sharedName,
        'organization_subtitle' => 'Brgy. Poblacion, Baganga',
        'establishment_id' => $listingB->id,
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
    $qrA->assertSee(route('lgu.establishmentQr', ['establishment' => $listingA->uuid]), false);
    $qrA->assertDontSee($listingB->uuid);
});

test('an establishment user cannot access PTO or LGU routes', function () {
    $user = actingAsEstablishment('Botanika Nature Resort', 'Brgy. Dahican, City of Mati');

    test()->actingAs($user)->get(route('pto.dashboard'))->assertForbidden();
    test()->actingAs($user)->get(route('lgu.dashboard'))->assertForbidden();
});
