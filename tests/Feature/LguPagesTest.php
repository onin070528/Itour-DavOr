<?php

use App\Enums\UserRole;
use App\Models\User;

function actingAsLgu(string $municipality): User
{
    return User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_name' => "{$municipality} Tourism Office",
        'organization_subtitle' => $municipality,
    ]);
}

test('every LGU page renders for a municipality with data', function (string $routeName) {
    $user = actingAsLgu('City of Mati');

    test()->actingAs($user)->get(route($routeName))->assertOk();
})->with([
    'lgu.dashboard',
    'lgu.monitoring.arrivals',
    'lgu.monitoring.statistics',
    'lgu.monitoring.destinations',
    'lgu.directory.destinations',
    'lgu.directory.establishments',
    'lgu.feedback.index',
    'lgu.feedback.analytics',
    'lgu.reports',
    'lgu.settings',
]);

test('every LGU page renders for a municipality with no mock data (empty states)', function (string $routeName) {
    $user = actingAsLgu('Boston');

    test()->actingAs($user)->get(route($routeName))->assertOk();
})->with([
    'lgu.dashboard',
    'lgu.monitoring.arrivals',
    'lgu.monitoring.statistics',
    'lgu.monitoring.destinations',
    'lgu.directory.destinations',
    'lgu.directory.establishments',
    'lgu.feedback.index',
    'lgu.feedback.analytics',
    'lgu.reports',
    'lgu.settings',
]);

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
        'role' => UserRole::Lgu,
        'organization_name' => 'Unassigned LGU Office',
        'organization_subtitle' => null,
    ]);

    test()->actingAs($user)->get(route('lgu.dashboard'))->assertForbidden();
});

test('an LGU user cannot access PTO or another role\'s routes', function () {
    $user = actingAsLgu('City of Mati');

    test()->actingAs($user)->get(route('pto.dashboard'))->assertForbidden();
    test()->actingAs($user)->get(route('establishment.dashboard'))->assertForbidden();
});
