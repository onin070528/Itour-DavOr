<?php

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
        ? Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati'])
        : null;

    $user = User::factory()->create([
        'role' => $role,
        'organization_name' => 'Test Organization',
        'organization_subtitle' => $role === UserRole::Lgu ? 'City of Mati' : 'Test Coverage',
        'municipality_id' => $municipality?->id,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect($path);

    $this->get($path)->assertOk();
})->with([
    'PTO Administrator' => [UserRole::PtoAdministrator, '/pto'],
    'LGU' => [UserRole::Lgu, '/lgu'],
    'Establishment' => [UserRole::Establishment, '/establishment'],
]);

test('a role cannot access another role\'s dashboard', function () {
    $municipality = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $lgu = User::factory()->create(['role' => UserRole::Lgu, 'organization_subtitle' => 'City of Mati', 'municipality_id' => $municipality->id]);

    $this->actingAs($lgu);

    $this->get('/pto')->assertForbidden();
    $this->get('/establishment')->assertForbidden();
    $this->get('/lgu')->assertOk();
});

test('an invalid password is rejected', function () {
    $user = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a user can log out', function () {
    $user = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    $this->actingAs($user);

    $this->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});
