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
    'pto.directory.index',
    'pto.hotlines.index',
    'pto.announcements.index',
    'pto.feedback.index',
    'pto.feedback.analytics',
    'pto.images.index',
    'pto.users',
    'pto.settings',
]);

test('the Provincial Reports page hides the redundant role/page breadcrumb', function () {
    $user = User::factory()->create([
        'role' => UserRole::PtoAdministrator,
        'organization_name' => 'Provincial Tourism Office',
        'organization_subtitle' => 'Province of Davao Oriental',
    ]);

    $this->actingAs($user)->get(route('pto.monthlyReports.index'))
        ->assertOk()
        ->assertDontSee('PTO Administrator <span class="mx-1 text-sand-300">/</span>', false);
});

test('other PTO pages still show the role/page breadcrumb', function (string $routeName) {
    $user = User::factory()->create([
        'role' => UserRole::PtoAdministrator,
        'organization_name' => 'Provincial Tourism Office',
        'organization_subtitle' => 'Province of Davao Oriental',
    ]);

    $this->actingAs($user)->get(route($routeName))
        ->assertOk()
        ->assertSee('PTO Administrator <span class="mx-1 text-sand-300">/</span>', false);
})->with([
    'pto.dashboard',
    'pto.directory.index',
]);

test('the old Photo Approvals URL redirects to the Photos page\'s approval tab', function () {
    $user = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    $this->actingAs($user)->get(route('pto.images.queue'))
        ->assertRedirect(route('pto.images.index', ['tab' => 'approval']));
});

test('a non-PTO user gets a 403 on every PTO page', function (string $routeName) {
    $lgu = User::factory()->create(['role' => UserRole::Lgu]);

    $this->actingAs($lgu)->get(route($routeName))->assertForbidden();
})->with([
    'pto.dashboard',
    'pto.municipalReports.index',
    'pto.monthlyReports.index',
    'pto.directory.index',
    'pto.hotlines.index',
    'pto.announcements.index',
    'pto.feedback.index',
    'pto.images.index',
    'pto.images.queue',
    'pto.users',
    'pto.settings',
]);
