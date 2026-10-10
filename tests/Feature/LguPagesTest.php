<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — lgu pages.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use App\Support\BusinessHours;
use App\Support\DashboardNavigation;
use App\Support\TourismCatalog;

function actingAsLgu(string $municipality): User
{
    $record = Municipality::query()->firstOrCreate(
        ['mun_code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $municipality), 0, 4))],
        ['mun_name' => $municipality]
    );

    return User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_name' => "{$municipality} Tourism Office",
        'usr_organization_subtitle' => $municipality,
        'mun_id' => $record->mun_id,
    ]);
}

test('every LGU page renders for a municipality with data', function (string $routeName) {
    $user = actingAsLgu('City of Mati');

    test()->actingAs($user)->get(route($routeName))->assertOk();
})->with([
    'lgu.dashboard',
    'lgu.directory.establishments',
    'lgu.monthlyReports.index',
    'lgu.feedback.index',
    'lgu.feedback.analytics',
    'lgu.settings',
    'lgu.users',
]);

test('every LGU page renders for a municipality with no mock data (empty states)', function (string $routeName) {
    $user = actingAsLgu('Boston');

    test()->actingAs($user)->get(route($routeName))->assertOk();
})->with([
    'lgu.dashboard',
    'lgu.directory.establishments',
    'lgu.monthlyReports.index',
    'lgu.feedback.index',
    'lgu.feedback.analytics',
    'lgu.settings',
    'lgu.users',
]);

test('the old Photo Approvals URL redirects to the Photos page', function () {
    $user = actingAsLgu('City of Mati');

    test()->actingAs($user)->get(route('lgu.images.queue'))
        ->assertRedirect(route('lgu.images.index'));
});

test('the old Destinations URL redirects to the unified Attractions view', function () {
    $user = actingAsLgu('City of Mati');

    test()->actingAs($user)->get(route('lgu.directory.destinations'))
        ->assertRedirect(route('lgu.directory.establishments', ['view' => 'attractions']));
});

test('the LGU sidebar uses the unified Phase 7 navigation', function () {
    $user = actingAsLgu('City of Mati');

    test()->actingAs($user)->get(route('lgu.settings'))
        ->assertOk()
        ->assertSee('Tourism Directory')
        ->assertSee('Establishments')
        ->assertSee('Attractions')
        ->assertSee('Tourist Feedback')
        ->assertSee('Tourism Reports')
        ->assertSee('Manual Entry')
        ->assertSee('Establishment Accounts')
        ->assertSee('Audit Logs')
        ->assertDontSee('>Destinations<', false)
        ->assertDontSee('>Photos<', false);
});

test('the dashboard only shows data scoped to the LGU\'s own municipality', function () {
    $user = actingAsLgu('Cateel');

    $response = test()->actingAs($user)->get(route('lgu.dashboard'));

    $response->assertOk();
    $response->assertSee('Cateel');
    // Dahican Beach belongs to City of Mati, not Cateel — must not leak across municipalities.
    $response->assertDontSee('Dahican Beach');
});

test('an LGU account with no assigned municipality is blocked, not crashed', function () {
    $user = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_name' => 'Unassigned LGU Office',
        'usr_organization_subtitle' => null,
    ]);

    test()->actingAs($user)->get(route('lgu.dashboard'))->assertForbidden();
});

test('an LGU user cannot access PTO or another role\'s routes', function () {
    $user = actingAsLgu('City of Mati');

    test()->actingAs($user)->get(route('pto.dashboard'))->assertForbidden();
    test()->actingAs($user)->get(route('establishment.dashboard'))->assertForbidden();
});

test('the LGU destination form lists only its own municipality barangays and locks the contact office', function () {
    $user = actingAsLgu('City of Mati');

    test()->actingAs($user)->get(route('lgu.directory.destinations'))
        ->assertOk()
        ->assertSee('<option value="Brgy. Dahican">', false)
        ->assertDontSee('<option value="Brgy. Aliwagwag">', false)
        ->assertSee('value="Mati City Tourism Office" readonly', false);
});

test('an LGU cannot save a destination in a barangay of another municipality', function () {
    $user = actingAsLgu('City of Mati');

    test()->actingAs($user)->post(route('lgu.directory.destinations.store'), [
        'name' => 'Wrong Place',
        'barangay' => 'Brgy. Aliwagwag',
    ])->assertSessionHasErrors('barangay');
});

test('the LGU destination contact office is always the municipal tourism office', function () {
    $user = actingAsLgu('Cateel');

    test()->actingAs($user)->post(route('lgu.directory.destinations.store'), [
        'name' => 'Falls',
        'barangay' => 'Brgy. Aliwagwag',
        'contactOffice' => 'Tampered Office',
    ])->assertSessionHasNoErrors();

    expect(Listing::query()->where('lst_name', 'Falls')->value('lst_contact_office'))
        ->toBe('Cateel Tourism Office');
});

test('LGU sidebar lists Management directly below Work', function () {
    $user = actingAsLgu('City of Mati');

    $arrSectionNames = array_keys(DashboardNavigation::sections($user, 'dashboard'));

    expect(array_search('Management', $arrSectionNames))->toBe(array_search('Work', $arrSectionNames) + 1);
});

test('the LGU Users page has a View button that shows the establishment and account details', function () {
    $user = actingAsLgu('City of Mati');
    $establishment = User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'mun_id' => $user->mun_id,
        'usr_name' => 'Minalyn Owner',
        'usr_email' => 'owner@example.test',
    ]);

    $response = test()->actingAs($user)->get(route('lgu.users'));

    $response->assertOk()
        ->assertSee('user-view-'.$establishment->usr_id)
        ->assertSee('owner@example.test')
        ->assertSee('Last Sign-in');
});

test('the LGU establishment form lists every barangay of its own municipality only', function () {
    $user = actingAsLgu('City of Mati');
    $response = test()->actingAs($user)->get(route('lgu.users'))->assertOk();

    foreach (TourismCatalog::barangaysFor('City of Mati') as $barangay) {
        $response->assertSee('value="'.$barangay.'"', false);
    }
    $response->assertDontSee('value="Brgy. Aliwagwag"', false);
});

test('an LGU cannot register an establishment in a barangay outside its municipality', function () {
    $user = actingAsLgu('City of Mati');

    test()->actingAs($user)->post(route('lgu.users.store'), [
        'name' => 'Wrong Place Inn',
        'category' => 'accommodation',
        'barangay' => 'Brgy. Aliwagwag',
        'ownerName' => 'Juan Dela Cruz',
        'contactPhone' => '09171234567',
        'email' => 'wrongplace@example.test',
    ])->assertSessionHasErrors('barangay');
});

test('business hour times start at 6:00 AM', function () {
    $arrTimes = BusinessHours::times();

    expect(array_key_first($arrTimes))->toBe('06:00')
        ->and($arrTimes)->not->toHaveKey('05:30')
        ->and(array_key_last($arrTimes))->toBe('23:30');
});

test('the LGU Destinations directory no longer has an Add Destination button', function () {
    $user = actingAsLgu('City of Mati');

    test()->actingAs($user)->get(route('lgu.directory.destinations'))
        ->assertOk()
        ->assertDontSee('Add Destination');
});

test('the LGU Accounts page offers an Establishment or Destination choice', function () {
    $user = actingAsLgu('City of Mati');

    test()->actingAs($user)->get(route('lgu.users'))
        ->assertOk()
        ->assertSee('name="entryType"', false)
        ->assertSee('value="destination"', false)
        ->assertSee('Accounts');
});

test('a destination added from the Accounts form lands in the Destinations directory with no login account', function () {
    $user = actingAsLgu('City of Mati');

    test()->actingAs($user)->post(route('lgu.directory.destinations.store'), [
        'entryType' => 'destination',
        'name' => 'Test Falls',
        'barangay' => 'Brgy. Dahican',
        'description' => 'A waterfall.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $listing = Listing::query()->where('lst_name', 'Test Falls')->firstOrFail();

    expect($listing->lst_category)->toBe('destinations')
        ->and($listing->mun_id)->toBe($user->mun_id)
        ->and(User::query()->where('lst_id', $listing->lst_id)->exists())->toBeFalse();
});

test('a destination added with the full shared form saves its hours, contact person, email and website', function () {
    $user = actingAsLgu('City of Mati');

    test()->actingAs($user)->post(route('lgu.directory.destinations.store'), [
        'entryType' => 'destination',
        'name' => 'Full Form Falls',
        'barangay' => 'Brgy. Dahican',
        'ownerName' => 'Ranger Ana',
        'contactPhone' => '09171234567',
        'email' => 'falls@example.test',
        'website' => 'https://falls.example.test',
        'hoursDays' => 'mon-sun',
        'hoursOpen' => '24h',
        'hoursClose' => '',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $listing = Listing::query()->where('lst_name', 'Full Form Falls')->firstOrFail();

    expect($listing->lst_category)->toBe('destinations')
        ->and($listing->lst_owner_name)->toBe('Ranger Ana')
        ->and($listing->lst_email)->toBe('falls@example.test')
        ->and($listing->lst_website)->toBe('https://falls.example.test')
        ->and($listing->lst_hours)->toBe('Mon–Sun, Open 24 hours')
        ->and(User::query()->where('lst_id', $listing->lst_id)->exists())->toBeFalse();
});
