<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — authentication.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Municipality;
use App\Models\User;

test('guests can view the login page', function () {
    $this->get('/login')->assertOk();
});

test('guests are redirected to login when visiting a protected dashboard', function () {
    $this->get('/pto')->assertRedirect('/login');
    $this->get('/lgu')->assertRedirect('/login');
    $this->get('/establishment')->assertRedirect('/login');
});

test('each role can sign in and reach their own dashboard', function (UserRole $role, string $path) {
    $municipality = $role === UserRole::Lgu
        ? Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati'])
        : null;

    $user = User::factory()->create([
        'usr_role' => $role,
        'usr_organization_name' => 'Test Organization',
        'usr_organization_subtitle' => $role === UserRole::Lgu ? 'City of Mati' : 'Test Coverage',
        'mun_id' => $municipality?->mun_id,
    ]);

    $this->post('/login', [
        'email' => $user->usr_email,
        'password' => 'password',
    ])->assertRedirect($path);

    $this->get($path)->assertOk();
})->with([
    'PTO Administrator' => [UserRole::PtoAdministrator, '/pto'],
    'LGU' => [UserRole::Lgu, '/lgu'],
    'Establishment' => [UserRole::Establishment, '/establishment'],
]);

test('a role cannot access another role\'s dashboard', function () {
    $municipality = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'usr_organization_subtitle' => 'City of Mati', 'mun_id' => $municipality->mun_id]);

    $this->actingAs($lgu);

    $this->get('/pto')->assertForbidden();
    $this->get('/establishment')->assertForbidden();
    $this->get('/lgu')->assertOk();
});

test('an invalid password is rejected', function () {
    $user = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    $this->post('/login', [
        'email' => $user->usr_email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a user can log out', function () {
    $user = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    $this->actingAs($user);

    $this->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});
