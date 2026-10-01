<?php

use App\Enums\UserRole;
use App\Models\User;

test('every PTO page renders for an authenticated PTO administrator', function (string $routeName) {
    $user = User::factory()->create([
        'role' => UserRole::PtoAdministrator,
        'organization_name' => 'Provincial Tourism Office',
        'organization_subtitle' => 'Province of Davao Oriental',
    ]);

    $this->actingAs($user)->get(route($routeName))->assertOk();
})->with([
    'pto.dashboard',
    'pto.municipalReports.index',
    'pto.monthlyReports.index',
    'pto.directory.destinations',
    'pto.directory.establishments',
    'pto.directory.map',
    'pto.feedback.index',
    'pto.feedback.analytics',
    'pto.users',
    'pto.settings',
]);
