<?php

use App\Enums\UserRole;
use App\Models\User;

function actingAsEstablishment(string $name, string $subtitle = 'Somewhere, Davao Oriental'): User
{
    return User::factory()->create([
        'role' => UserRole::Establishment,
        'organization_name' => $name,
        'organization_subtitle' => $subtitle,
    ]);
}

test('every Establishment page renders for an establishment with data', function (string $routeName) {
    $user = actingAsEstablishment('Botanika Nature Resort', 'Brgy. Dahican, City of Mati');

    test()->actingAs($user)->get(route($routeName))->assertOk();
})->with([
    'establishment.dashboard',
    'establishment.profile',
    'establishment.qr',
    'establishment.arrivals.record',
    'establishment.arrivals.index',
    'establishment.statistics',
    'establishment.feedback.index',
    'establishment.feedback.analytics',
    'establishment.reports',
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
    'establishment.statistics',
    'establishment.feedback.index',
    'establishment.feedback.analytics',
    'establishment.reports',
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
    $user = actingAsEstablishment('Botanika Nature Resort', 'Brgy. Dahican, City of Mati');

    $feedback = test()->actingAs($user)->get(route('establishment.feedback.index'));
    $feedback->assertOk();
    $feedback->assertSee('Beautiful sunrise from the room');
    // Feedback left for a different establishment must not appear here.
    $feedback->assertDontSee('Lami kaayo ang kinilaw');

    $arrivals = test()->actingAs($user)->get(route('establishment.arrivals.index'));
    $arrivals->assertOk();
    $arrivals->assertSee('Kim Soo-jin');
});

test('an establishment user cannot access PTO or LGU routes', function () {
    $user = actingAsEstablishment('Botanika Nature Resort', 'Brgy. Dahican, City of Mati');

    test()->actingAs($user)->get(route('pto.dashboard'))->assertForbidden();
    test()->actingAs($user)->get(route('lgu.dashboard'))->assertForbidden();
});
