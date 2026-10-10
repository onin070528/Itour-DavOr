<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — user management rbac.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\SecurityLog;
use App\Models\User;
use App\Support\BusinessHours;
use Illuminate\Support\Str;

function makePtoAdmin(): User
{
    return User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
}

function makeMunicipalityFixture(string $name, string $code): Municipality
{
    return Municipality::query()->firstOrCreate(['mun_code' => $code], ['mun_name' => $name]);
}

function makeEstablishmentListingFixture(Municipality $municipality, string $name): Listing
{
    return Listing::query()->create([
        'lst_slug' => Str::slug($name.'-'.Str::random(6)),
        'lst_name' => $name,
        'lst_category' => 'accommodation',
        'lst_municipality' => $municipality->mun_name,
        'mun_id' => $municipality->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_status' => 'PUBLISHED',
    ]);
}

test('PTO can create an LGU account', function () {
    $cateel = makeMunicipalityFixture('Cateel', 'CAT');
    $pto = makePtoAdmin();

    $response = test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => 'New LGU Officer',
        'email' => 'new.lgu@example.test',
        'role' => UserRole::Lgu->value,
        'municipality_id' => $cateel->mun_id,
    ]);

    $response->assertRedirect();
    $created = User::query()->where('usr_email', 'new.lgu@example.test')->first();
    expect($created)->not->toBeNull();
    expect($created->usr_role)->toBe(UserRole::Lgu);
    expect($created->mun_id)->not->toBeNull();
    expect($created->usr_must_change_password)->toBeTrue();
});

test('created_by records which account created a new LGU account', function () {
    $cateel = makeMunicipalityFixture('Cateel', 'CAT');
    $pto = makePtoAdmin();

    test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => 'New LGU Officer',
        'email' => 'created-by.lgu@example.test',
        'role' => UserRole::Lgu->value,
        'municipality_id' => $cateel->mun_id,
    ]);

    $created = User::query()->where('usr_email', 'created-by.lgu@example.test')->first();
    expect($created->usr_created_by)->toBe($pto->usr_id);
});

test('created_by records which account registered a new establishment', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);

    test()->actingAs($lgu)->post(route('lgu.users.store'), establishmentFormPayload());

    $created = User::query()->where('usr_email', 'frontdesk@matifixtureinn.test')->first();
    expect($created->usr_created_by)->toBe($lgu->usr_id);
});

test('self-service settings cannot forge created_by', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListingFixture($mati, 'Mati Fixture Inn 4');
    $creator = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $user = User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'usr_organization_subtitle' => 'Poblacion, City of Mati',
        'mun_id' => $mati->mun_id,
        'lst_id' => $listing->lst_id,
        'usr_created_by' => $creator->usr_id,
    ]);

    test()->actingAs($user)->post(route('establishment.settings.profile'), [
        'name' => $user->usr_name,
        'email' => $user->usr_email,
        'created_by' => 999,
    ])->assertRedirect();

    expect($user->fresh()->usr_created_by)->toBe($creator->usr_id);
});

test('creating a new account records an account_created security log entry', function () {
    $cateel = makeMunicipalityFixture('Cateel', 'CAT');
    $pto = makePtoAdmin();

    test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => 'New LGU Officer',
        'email' => 'security-log.lgu@example.test',
        'role' => UserRole::Lgu->value,
        'municipality_id' => $cateel->mun_id,
    ]);

    $created = User::query()->where('usr_email', 'security-log.lgu@example.test')->first();
    $log = SecurityLog::where('sec_event_type', 'account_created')->where('sec_target_user_id', $created->usr_id)->first();
    expect($log)->not->toBeNull();
    expect($log->usr_id)->toBe($pto->usr_id);
});

test('suspending and reactivating an account records account_suspended/account_reactivated security log entries', function () {
    $pto = makePtoAdmin();
    $target = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    test()->actingAs($pto)->patch(route('pto.users.toggleStatus', $target));
    expect($target->fresh()->usr_status)->toBe('Inactive');
    $suspendLog = SecurityLog::where('sec_event_type', 'account_suspended')->where('sec_target_user_id', $target->usr_id)->first();
    expect($suspendLog)->not->toBeNull();
    expect($suspendLog->usr_id)->toBe($pto->usr_id);

    test()->actingAs($pto)->patch(route('pto.users.toggleStatus', $target));
    expect($target->fresh()->usr_status)->toBe('Active');
    expect(SecurityLog::where('sec_event_type', 'account_reactivated')->where('sec_target_user_id', $target->usr_id)->exists())->toBeTrue();
});

test('changing an account\'s role records a role_changed security log entry', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListingFixture($mati, 'Role Change Fixture Inn');
    $pto = makePtoAdmin();
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);

    test()->actingAs($pto)->put(route('pto.users.update', $lgu), [
        'name' => $lgu->usr_name,
        'email' => $lgu->usr_email,
        'role' => UserRole::Establishment->value,
        'municipality_id' => $mati->mun_id,
        'establishment_id' => $listing->lst_id,
    ])->assertSessionHasNoErrors();

    expect($lgu->fresh()->usr_role)->toBe(UserRole::Establishment);
    $log = SecurityLog::where('sec_event_type', 'role_changed')->where('sec_target_user_id', $lgu->usr_id)->first();
    expect($log)->not->toBeNull();
    expect($log->sec_details)->toBe(['from' => 'lgu', 'to' => 'establishment']);
});

test('editing an account without changing its role does not record a role_changed security log entry', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $pto = makePtoAdmin();
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);

    test()->actingAs($pto)->put(route('pto.users.update', $lgu), [
        'name' => 'Renamed Officer',
        'email' => $lgu->usr_email,
        'role' => UserRole::Lgu->value,
        'municipality_id' => $mati->mun_id,
    ])->assertSessionHasNoErrors();

    expect(SecurityLog::where('sec_event_type', 'role_changed')->where('sec_target_user_id', $lgu->usr_id)->exists())->toBeFalse();
});

test('PTO can create a new PTO account through the Users page', function () {
    // A1 (account-creation/first-login rework): PTO creates PTO Admin
    // accounts too — this reverses the old "PTO Administrator accounts
    // cannot be created from this page" rule for *creation* specifically;
    // promoting an *existing* lower-role account to PTO is still refused
    // (see the next test).
    $pto = makePtoAdmin();

    $response = test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => 'New Admin',
        'email' => 'new-admin@example.test',
        'role' => UserRole::PtoAdministrator->value,
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $created = User::query()->where('usr_email', 'new-admin@example.test')->first();
    expect($created)->not->toBeNull();
    expect($created->usr_role)->toBe(UserRole::PtoAdministrator);
});

test('PTO cannot promote an existing LGU account to PTO Administrator', function () {
    $municipality = makeMunicipalityFixture('Cateel', 'CAT');
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'Cateel',
        'mun_id' => $municipality->mun_id,
    ]);
    $pto = makePtoAdmin();

    $response = test()->actingAs($pto)->put(route('pto.users.update', $lgu), [
        'name' => $lgu->usr_name,
        'email' => $lgu->usr_email,
        'role' => UserRole::PtoAdministrator->value,
    ]);

    $response->assertSessionHasErrors('role');
    expect($lgu->fresh()->usr_role)->toBe(UserRole::Lgu);
});

/**
 * @return array<string, string>
 */
function establishmentFormPayload(array $overrides = []): array
{
    return [
        'name' => 'Mati Fixture Inn',
        'category' => 'accommodation',
        'barangay' => 'Brgy. Dahican',
        'ownerName' => 'Juan Dela Cruz',
        'contactPhone' => '09171234567',
        'email' => 'frontdesk@matifixtureinn.test',
        'hoursDays' => 'mon-sun',
        'hoursOpen' => '08:00',
        'hoursClose' => '17:00',
        'website' => 'https://matifixtureinn.test',
        'description' => 'Beachfront inn.',
        ...$overrides,
    ];
}

test('LGU can register an establishment and its account in its own municipality', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);

    $response = test()->actingAs($lgu)->post(route('lgu.users.store'), establishmentFormPayload());

    $response->assertRedirect()->assertSessionHasNoErrors();
    $created = User::query()->where('usr_email', 'frontdesk@matifixtureinn.test')->first();
    expect($created)->not->toBeNull();
    expect($created->usr_role)->toBe(UserRole::Establishment);
    expect($created->usr_name)->toBe('Juan Dela Cruz');
    expect($created->mun_id)->toBe($mati->mun_id);

    $listing = $created->establishment;
    expect($listing)->not->toBeNull();
    expect($listing->lst_name)->toBe('Mati Fixture Inn');
    expect($listing->lst_category)->toBe('accommodation');
    expect($listing->lst_barangay)->toBe('Brgy. Dahican');
    expect($listing->lst_owner_name)->toBe('Juan Dela Cruz');
    expect($listing->lst_contact_phone)->toBe('09171234567');
    expect($listing->mun_id)->toBe($mati->mun_id);
});

test('LGU-registered establishments are always placed in the LGU\'s own municipality', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $baganga = makeMunicipalityFixture('Baganga', 'BAG');
    $matiLgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);

    test()->actingAs($matiLgu)->post(route('lgu.users.store'), establishmentFormPayload([
        'email' => 'frontdesk@baganganfixtureinn.test',
        'municipality_id' => (string) $baganga->mun_id,
        'municipality' => 'Baganga',
    ]))->assertRedirect();

    $created = User::query()->where('usr_email', 'frontdesk@baganganfixtureinn.test')->first();
    expect($created->mun_id)->toBe($mati->mun_id);
    expect($created->establishment->mun_id)->toBe($mati->mun_id);
});

test('LGU cannot register an establishment under the destinations category', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);

    test()->actingAs($lgu)
        ->post(route('lgu.users.store'), establishmentFormPayload(['category' => 'destinations']))
        ->assertSessionHasErrors('category');

    expect(User::query()->where('usr_email', 'frontdesk@matifixtureinn.test')->exists())->toBeFalse();
});

test('LGU can edit an establishment\'s information and account', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListingFixture($mati, 'Old Inn Name');
    $establishmentUser = User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'mun_id' => $mati->mun_id,
        'lst_id' => $listing->lst_id,
    ]);
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);

    test()->actingAs($lgu)->put(route('lgu.users.update', $establishmentUser), establishmentFormPayload([
        'name' => 'New Inn Name',
        'email' => 'renamed@matifixtureinn.test',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $fresh = $establishmentUser->fresh();
    expect($fresh->usr_email)->toBe('renamed@matifixtureinn.test');
    expect($fresh->usr_organization_name)->toBe('New Inn Name');
    expect($fresh->lst_id)->toBe($listing->lst_id);
    expect($listing->fresh()->lst_name)->toBe('New Inn Name');
    expect($listing->fresh()->lst_hours)->toBe('Mon–Sun, 8:00 AM – 5:00 PM');
});

test('LGU cannot reach the PTO-only account-creation route at all', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $matiLgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);

    // Lgu\UsersController::store() always assigns role Establishment — there
    // is no LGU-reachable endpoint capable of creating an LGU or PTO
    // account at all, so "cannot create at own level or above" holds
    // structurally, not merely by field validation.
    test()->actingAs($matiLgu)->post(route('pto.users.store'), [
        'name' => 'x', 'email' => 'x@example.test', 'role' => 'LGU Tourism Personnel', 'assignment' => 'Cateel',
    ])->assertForbidden();
});

test('LGU cannot manage an establishment user belonging to another municipality', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $baganga = makeMunicipalityFixture('Baganga', 'BAG');
    $baganganListing = makeEstablishmentListingFixture($baganga, 'Baganga Fixture Inn 2');
    $baganganEstablishmentUser = User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'usr_organization_subtitle' => 'Poblacion, Baganga',
        'mun_id' => $baganga->mun_id,
        'lst_id' => $baganganListing->lst_id,
    ]);
    $matiLgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);

    test()->actingAs($matiLgu)
        ->patch(route('lgu.users.toggleStatus', $baganganEstablishmentUser))
        ->assertForbidden();

    expect($baganganEstablishmentUser->fresh()->usr_status)->toBe('Active');
});

test('nobody can deactivate their own account', function () {
    $pto = makePtoAdmin();
    test()->actingAs($pto)->patch(route('pto.users.toggleStatus', $pto))->assertForbidden();
    expect($pto->fresh()->usr_status)->toBe('Active');

    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);
    test()->actingAs($lgu)->patch(route('lgu.users.toggleStatus', $lgu))->assertForbidden();
});

test('self-service settings cannot change role, status, municipality_id, or establishment_id', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListingFixture($mati, 'Mati Fixture Inn 3');
    $user = User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'usr_organization_subtitle' => 'Poblacion, City of Mati',
        'mun_id' => $mati->mun_id,
        'lst_id' => $listing->lst_id,
    ]);

    // Attempted privilege escalation via a payload the self-service
    // settings endpoint never reads — a real mass-assignment probe.
    test()->actingAs($user)->post(route('establishment.settings.profile'), [
        'name' => $user->usr_name,
        'email' => $user->usr_email,
        'role' => 'pto_administrator',
        'status' => 'Inactive',
        'municipality_id' => 999,
        'establishment_id' => 999,
    ])->assertRedirect();

    $fresh = $user->fresh();
    expect($fresh->usr_role)->toBe(UserRole::Establishment);
    expect($fresh->usr_status)->toBe('Active');
    expect($fresh->mun_id)->toBe($mati->mun_id);
    expect($fresh->lst_id)->toBe($listing->lst_id);
});

test('the LGU Users table lists each establishment\'s saved information', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);

    test()->actingAs($lgu)->post(route('lgu.users.store'), establishmentFormPayload())->assertRedirect();

    test()->actingAs($lgu)->get(route('lgu.users'))
        ->assertOk()
        ->assertSee('Mati Fixture Inn')
        ->assertSee('Accommodation')
        ->assertSee('Dahican')
        ->assertSee('Juan Dela Cruz')
        ->assertSee('09171234567');
});

test('business hours dropdowns save as a single hours string, including open 24 hours', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);

    test()->actingAs($lgu)->post(route('lgu.users.store'), establishmentFormPayload([
        'hoursDays' => 'mon-fri',
        'hoursOpen' => '24h',
        'hoursClose' => '',
    ]))->assertSessionHasNoErrors();

    $listing = User::query()->where('usr_email', 'frontdesk@matifixtureinn.test')->first()->establishment;
    expect($listing->lst_hours)->toBe('Mon–Fri, Open 24 hours');
    expect(BusinessHours::parse($listing->lst_hours))->toBe(['days' => 'mon-fri', 'opens' => '24h', 'closes' => null]);
    expect(BusinessHours::parse('Mon–Sun, 8:00 AM – 5:00 PM'))->toBe(['days' => 'mon-sun', 'opens' => '08:00', 'closes' => '17:00']);
});

test('business hours require a closing time unless open 24 hours', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => 'City of Mati',
        'mun_id' => $mati->mun_id,
    ]);

    test()->actingAs($lgu)
        ->post(route('lgu.users.store'), establishmentFormPayload(['hoursClose' => '']))
        ->assertSessionHasErrors('hoursClose');

    test()->actingAs($lgu)
        ->post(route('lgu.users.store'), establishmentFormPayload(['hoursDays' => '']))
        ->assertSessionHasErrors('hoursDays');
});
