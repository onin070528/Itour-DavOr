<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Verifies the first-login forced password change (App\Http\Middleware\ForcePasswordChange).
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Municipality;
use App\Models\User;
use Database\Seeders\MunicipalitySeeder;
use Database\Seeders\RbacDemoAccountSeeder;
use Illuminate\Support\Facades\Hash;

const FIRST_LOGIN_NEW_PASSWORD = 'My-own-passw0rd!';

/**
 * An LGU account in a fresh "City of Mati", created with the factory's
 * "password" as its temporary password.
 */
function createFirstLoginLgu(bool $blnMustChangePassword = true): User
{
    $objMati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);

    return User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_name' => 'Mati City Tourism Office',
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $objMati->id,
        'usr_must_change_password' => $blnMustChangePassword,
    ]);
}

// 1. Redirected to the change-password page right after login

test('a user who must change their password is sent to the change-password page after login', function () {
    $objLgu = createFirstLoginLgu();

    $this->post(route('login.store'), ['email' => $objLgu->email, 'password' => 'password'])
        ->assertRedirect(route('password.change'));

    $this->assertAuthenticatedAs($objLgu);
    $this->get(route('password.change'))->assertOk();
});

test('the requirements are a live checklist directly below the New Password field, above Confirm New Password', function () {
    $this->actingAs(createFirstLoginLgu())
        ->get(route('password.change'))
        ->assertOk()
        ->assertSeeInOrder([
            'New Password',
            'data-password-checklist="password"',
            'Password requirements:',
            'data-rule="length"', 'At least 12 characters',
            'data-rule="letter"', 'At least 1 letter',
            'data-rule="number"', 'At least 1 number',
            'data-rule="symbol"', 'At least 1 special character',
            'data-temporary-password-rule',
            'Different from your temporary password',
            'Confirm New Password',
        ], false)
        ->assertSee('aria-describedby="password-requirements"', false)
        ->assertDontSee('Your password must have');
});

test('the seeded secondary PTO and LGU accounts must change their password; the primary PTO does not', function () {
    (new MunicipalitySeeder)->run();
    (new RbacDemoAccountSeeder)->run();
    $strTemporaryPassword = env('SEED_DEMO_PASSWORD');

    foreach (['tourism.admin2@itourdavor.gov.ph', 'tourism.baganga@itourdavor.gov.ph'] as $strEmail) {
        $this->post(route('login.store'), ['email' => $strEmail, 'password' => $strTemporaryPassword])
            ->assertRedirect(route('password.change'));
        $this->post(route('logout'));
    }

    $this->post(route('login.store'), ['email' => 'tourism@itourdavor.gov.ph', 'password' => $strTemporaryPassword])
        ->assertRedirect(route('pto.dashboard'));
});

// 2. Dashboard blocked until the password is changed

test('the dashboard is blocked until the password is changed', function () {
    $this->actingAs(createFirstLoginLgu())
        ->get(route('lgu.dashboard'))
        ->assertRedirect(route('password.change'));
});

// 3. A valid change clears the flag

test('a valid new password is saved hashed and clears the flag', function () {
    $objLgu = createFirstLoginLgu();

    $this->actingAs($objLgu)
        ->put(route('password.change.store'), [
            'password' => FIRST_LOGIN_NEW_PASSWORD,
            'password_confirmation' => FIRST_LOGIN_NEW_PASSWORD,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('lgu.dashboard'));

    $objLgu->refresh();

    expect($objLgu->mustChangePassword())->toBeFalse();
    expect($objLgu->usr_password_changed_at)->not->toBeNull();
    expect($objLgu->getRawOriginal('password'))->not->toBe(FIRST_LOGIN_NEW_PASSWORD);
    expect(Hash::check(FIRST_LOGIN_NEW_PASSWORD, $objLgu->password))->toBeTrue();
    $this->assertDatabaseHas('security_logs', ['user_id' => $objLgu->id, 'event_type' => 'password_changed']);
});

// 4. Invalid passwords are rejected by the existing policy

test('a password that breaks the policy is rejected and the flag stays set', function (string $strPassword, ?string $strConfirmation) {
    $objLgu = createFirstLoginLgu();

    $this->actingAs($objLgu)
        ->put(route('password.change.store'), [
            'password' => $strPassword,
            'password_confirmation' => $strConfirmation ?? $strPassword,
        ])
        ->assertSessionHasErrors('password');

    expect($objLgu->fresh()->mustChangePassword())->toBeTrue();
    expect(Hash::check('password', $objLgu->fresh()->password))->toBeTrue();
})->with([
    'shorter than 12 characters' => ['Sh0rt-pass!', null],
    'no number' => ['No-numbers-here!', null],
    'no special character' => ['NoSymbols12345', null],
    'no letter' => ['1234567890-!@#', null],
    'confirmation does not match' => [FIRST_LOGIN_NEW_PASSWORD, 'Different-passw0rd!'],
]);

test('the new password cannot be the temporary password', function () {
    $objPto = User::factory()->create([
        'role' => UserRole::PtoAdministrator,
        'password' => 'Temporary-passw0rd!',
        'usr_must_change_password' => true,
    ]);

    $this->actingAs($objPto)
        ->put(route('password.change.store'), [
            'password' => 'Temporary-passw0rd!',
            'password_confirmation' => 'Temporary-passw0rd!',
        ])
        ->assertSessionHasErrors('password');

    expect($objPto->fresh()->mustChangePassword())->toBeTrue();
});

// 5. Dashboard reachable after the change; the change page is closed

test('after changing the password the user reaches the dashboard and the change page is closed', function () {
    $objLgu = createFirstLoginLgu();

    $this->post(route('login.store'), ['email' => $objLgu->email, 'password' => 'password']);
    $this->put(route('password.change.store'), [
        'password' => FIRST_LOGIN_NEW_PASSWORD,
        'password_confirmation' => FIRST_LOGIN_NEW_PASSWORD,
    ]);

    $this->get(route('lgu.dashboard'))->assertOk();
    $this->get(route('password.change'))->assertRedirect(route('lgu.dashboard'));
    $this->put(route('password.change.store'), [
        'password' => 'Another-passw0rd!',
        'password_confirmation' => 'Another-passw0rd!',
    ])->assertRedirect(route('lgu.dashboard'));

    expect(Hash::check(FIRST_LOGIN_NEW_PASSWORD, $objLgu->fresh()->password))->toBeTrue();
});

// 6. Accounts without the flag log in normally

test('a user who does not need to change their password logs in normally', function () {
    $objPto = User::factory()->create(['role' => UserRole::PtoAdministrator, 'usr_must_change_password' => false]);

    $this->post(route('login.store'), ['email' => $objPto->email, 'password' => 'password'])
        ->assertRedirect(route('pto.dashboard'));

    $this->get(route('pto.dashboard'))->assertOk();
    $this->get(route('password.change'))->assertRedirect(route('pto.dashboard'));
});

// 7. No bypass through direct URLs

test('every other page redirects to the change-password page, whatever the URL', function (string $strRouteName) {
    $objPto = User::factory()->create(['role' => UserRole::PtoAdministrator, 'usr_must_change_password' => true]);

    $this->actingAs($objPto)->get(route($strRouteName))->assertRedirect(route('password.change'));
})->with(['pto.dashboard', 'pto.users', 'pto.municipalReports.index', 'pto.monthlyReports.index', 'pto.directory.index', 'pto.settings', 'home']);

test('a form post to another page is not processed before the password is changed', function () {
    $objLgu = createFirstLoginLgu();

    $this->actingAs($objLgu)
        ->post(route('lgu.settings.password'), [
            'current_password' => 'password',
            'password' => FIRST_LOGIN_NEW_PASSWORD,
            'password_confirmation' => FIRST_LOGIN_NEW_PASSWORD,
        ])
        ->assertRedirect(route('password.change'));

    expect(Hash::check('password', $objLgu->fresh()->password))->toBeTrue();
    expect($objLgu->fresh()->mustChangePassword())->toBeTrue();
});

test('JSON requests get a 403 instead of a redirect', function () {
    $this->actingAs(createFirstLoginLgu())
        ->getJson(route('lgu.dashboard'))
        ->assertForbidden();
});

test('signing out still works before the password is changed', function () {
    $this->actingAs(createFirstLoginLgu())
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

// 8. Existing auth and RBAC behavior unaffected

test('guests cannot open the change-password page', function () {
    $this->get(route('password.change'))->assertRedirect(route('login'));
    $this->put(route('password.change.store'), [
        'password' => FIRST_LOGIN_NEW_PASSWORD,
        'password_confirmation' => FIRST_LOGIN_NEW_PASSWORD,
    ])->assertRedirect(route('login'));
});

test('a disabled account with a temporary password still cannot sign in', function () {
    $objLgu = createFirstLoginLgu();
    $objLgu->forceFill(['status' => 'Inactive'])->save();

    $this->post(route('login.store'), ['email' => $objLgu->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('after the change an LGU account is still kept out of PTO pages', function () {
    $objLgu = createFirstLoginLgu();

    $this->actingAs($objLgu)->put(route('password.change.store'), [
        'password' => FIRST_LOGIN_NEW_PASSWORD,
        'password_confirmation' => FIRST_LOGIN_NEW_PASSWORD,
    ]);

    $this->get(route('pto.dashboard'))->assertForbidden();
    $this->get(route('lgu.dashboard'))->assertOk();
});
