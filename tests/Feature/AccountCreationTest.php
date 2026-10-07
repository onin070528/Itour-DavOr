<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Sub-stage B verification — the rebuilt Add User modal, passphrase, and welcome email.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Mail\WelcomeAccountCreated;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function accountCreationMunicipalityFixture(string $name, string $code): Municipality
{
    return Municipality::query()->firstOrCreate(['mun_code' => $code], ['mun_name' => $name]);
}

function accountCreationPtoFixture(): User
{
    return User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
}

function accountCreationUnassignedEstablishmentFixture(Municipality $municipality, string $name): Listing
{
    $category = Category::query()->firstOrCreate(
        ['cat_name' => 'Accommodation'],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );

    return Listing::query()->create([
        'lst_slug' => Str::slug($name.'-'.Str::random(6)),
        'lst_name' => $name,
        'lst_category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'lst_municipality' => $municipality->mun_name,
        'mun_id' => $municipality->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_status' => 'PUBLISHED',
    ]);
}

test('PTO creating an LGU account generates a passphrase, hashes it, and requires a password change', function () {
    Mail::fake();
    $cateel = accountCreationMunicipalityFixture('Cateel', 'CAT');
    $pto = accountCreationPtoFixture();

    test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => 'New LGU Officer',
        'email' => 'lgu.officer@example.test',
        'role' => UserRole::Lgu->value,
        'municipality_id' => $cateel->mun_id,
    ])->assertRedirect();

    $created = User::query()->where('usr_email', 'lgu.officer@example.test')->first();
    expect($created)->not->toBeNull();
    expect($created->usr_must_change_password)->toBeTrue();
    expect($created->usr_password_changed_at)->toBeNull();
    // The stored value is a bcrypt/argon hash, never the plain passphrase.
    expect($created->usr_password)->not->toContain('-'.date('Y'));
});

test('creating an establishment account requires an establishment belonging to the chosen municipality with no linked account', function () {
    $mati = accountCreationMunicipalityFixture('City of Mati', 'MATI');
    $baganga = accountCreationMunicipalityFixture('Baganga', 'BAG');
    $listingInBaganga = accountCreationUnassignedEstablishmentFixture($baganga, 'Baganga Inn');
    $pto = accountCreationPtoFixture();

    // Wrong municipality for the chosen establishment.
    test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => 'Front Desk',
        'email' => 'frontdesk@baganga-inn.test',
        'role' => UserRole::Establishment->value,
        'municipality_id' => $mati->mun_id,
        'establishment_id' => $listingInBaganga->lst_id,
    ])->assertSessionHasErrors('establishment_id');

    expect(User::query()->where('usr_email', 'frontdesk@baganga-inn.test')->exists())->toBeFalse();
});

test('an establishment already linked to a user cannot be assigned again', function () {
    $mati = accountCreationMunicipalityFixture('City of Mati', 'MATI');
    $listing = accountCreationUnassignedEstablishmentFixture($mati, 'Already Linked Inn');
    User::factory()->create(['usr_role' => UserRole::Establishment, 'lst_id' => $listing->lst_id, 'mun_id' => $mati->mun_id]);
    $pto = accountCreationPtoFixture();

    test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => 'Second Front Desk',
        'email' => 'second@already-linked-inn.test',
        'role' => UserRole::Establishment->value,
        'municipality_id' => $mati->mun_id,
        'establishment_id' => $listing->lst_id,
    ])->assertSessionHasErrors('establishment_id');
});

test('the AJAX establishments endpoint returns only unassigned establishments in the requested municipality', function () {
    $mati = accountCreationMunicipalityFixture('City of Mati', 'MATI');
    $baganga = accountCreationMunicipalityFixture('Baganga', 'BAG');
    $unassigned = accountCreationUnassignedEstablishmentFixture($mati, 'Unassigned Mati Inn');
    $assigned = accountCreationUnassignedEstablishmentFixture($mati, 'Assigned Mati Inn');
    User::factory()->create(['usr_role' => UserRole::Establishment, 'lst_id' => $assigned->lst_id, 'mun_id' => $mati->mun_id]);
    $elsewhere = accountCreationUnassignedEstablishmentFixture($baganga, 'Baganga Inn');
    $pto = accountCreationPtoFixture();

    $response = test()->actingAs($pto)->getJson(route('pto.users.availableEstablishments', ['municipality_id' => $mati->mun_id]));

    $response->assertOk();
    $names = collect($response->json('establishments'))->pluck('name');
    expect($names)->toContain('Unassigned Mati Inn');
    expect($names)->not->toContain('Assigned Mati Inn');
    expect($names)->not->toContain('Baganga Inn');
});

test('a missing required field shows an error for that field and does not create the account', function () {
    $pto = accountCreationPtoFixture();

    test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => '',
        'email' => 'incomplete@example.test',
        'role' => UserRole::Lgu->value,
        // municipality_id omitted — required for the Lgu role.
    ])->assertSessionHasErrors(['name', 'municipality_id']);

    expect(User::query()->where('usr_email', 'incomplete@example.test')->exists())->toBeFalse();
});

test('the welcome email is sent with the passphrase when mail is configured', function () {
    Mail::fake();
    $cateel = accountCreationMunicipalityFixture('Cateel', 'CAT');
    $pto = accountCreationPtoFixture();

    test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => 'Mail Test Officer',
        'email' => 'mail-test@example.test',
        'role' => UserRole::Lgu->value,
        'municipality_id' => $cateel->mun_id,
    ]);

    Mail::assertSent(WelcomeAccountCreated::class, function (WelcomeAccountCreated $mail) {
        return $mail->objUser->usr_email === 'mail-test@example.test' && $mail->strPassphrase !== '';
    });
});

test('the confirmation panel shows the passphrase once and a reload does not show it again', function () {
    $cateel = accountCreationMunicipalityFixture('Cateel', 'CAT');
    $pto = accountCreationPtoFixture();

    test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => 'Once Only Officer',
        'email' => 'once-only@example.test',
        'role' => UserRole::Lgu->value,
        'municipality_id' => $cateel->mun_id,
    ]);

    $firstLoad = test()->actingAs($pto)->get(route('pto.users'));
    $firstLoad->assertOk();
    $firstLoad->assertSee('account-created-modal', false);

    $secondLoad = test()->actingAs($pto)->get(route('pto.users'));
    $secondLoad->assertOk();
    $secondLoad->assertDontSee('account-created-modal', false);
});

test('account creation records an account_created security log entry with no passphrase in it', function () {
    $cateel = accountCreationMunicipalityFixture('Cateel', 'CAT');
    $pto = accountCreationPtoFixture();

    test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => 'Audit Log Officer',
        'email' => 'audit-log@example.test',
        'role' => UserRole::Lgu->value,
        'municipality_id' => $cateel->mun_id,
    ]);

    $created = User::query()->where('usr_email', 'audit-log@example.test')->first();
    $log = SecurityLog::where('sec_event_type', 'account_created')->where('sec_target_user_id', $created->usr_id)->first();

    expect($log)->not->toBeNull();
    expect(json_encode($log->toArray()))->not->toContain('-'.date('Y'));
});

test('LGU cannot reach the PTO-only account-creation route to create a PTO or LGU account', function () {
    $mati = accountCreationMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    test()->actingAs($lgu)->post(route('pto.users.store'), [
        'name' => 'Sneaky',
        'email' => 'sneaky-lgu-attempt@example.test',
        'role' => UserRole::Lgu->value,
        'municipality_id' => $mati->mun_id,
    ])->assertForbidden();

    expect(User::query()->where('usr_email', 'sneaky-lgu-attempt@example.test')->exists())->toBeFalse();
});

test('LGU cannot assign an establishment account to another municipality — municipality always comes from the establishment itself', function () {
    $mati = accountCreationMunicipalityFixture('City of Mati', 'MATI');
    $baganga = accountCreationMunicipalityFixture('Baganga', 'BAG');
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);
    $listing = accountCreationUnassignedEstablishmentFixture($mati, 'Cross-Municipality Inn');
    $otherListing = accountCreationUnassignedEstablishmentFixture($baganga, 'Baganga Inn');

    test()->actingAs($lgu)->post(route('lgu.directory.establishments.switchToOnline', $listing), [
        'account_name' => 'Juan Dela Cruz',
        'account_email' => 'cross-municipality@example.test',
        // The account form has no municipality or establishment field at
        // all — this attempts the closest forgeable equivalent and confirms
        // it's ignored, not merely absent from the form.
        'municipality_id' => $baganga->mun_id,
        'establishment_id' => $otherListing->lst_id,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $created = User::query()->where('usr_email', 'cross-municipality@example.test')->first();
    expect($created->mun_id)->toBe($mati->mun_id);
    expect($created->lst_id)->toBe($listing->lst_id);
    expect($otherListing->fresh()->establishmentUser)->toBeNull();
});

test('LGU activating an establishment account also gets a passphrase and must_change_password', function () {
    Mail::fake();
    $mati = accountCreationMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);
    $listing = accountCreationUnassignedEstablishmentFixture($mati, 'LGU-Registered Inn');

    test()->actingAs($lgu)->post(route('lgu.directory.establishments.switchToOnline', $listing), [
        'account_name' => 'Juan Dela Cruz',
        'account_email' => 'lgu-registered@example.test',
    ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('accountCreated.passphrase');

    $created = User::query()->where('usr_email', 'lgu-registered@example.test')->first();
    expect($created->usr_must_change_password)->toBeTrue();
    Mail::assertSent(WelcomeAccountCreated::class);
});
