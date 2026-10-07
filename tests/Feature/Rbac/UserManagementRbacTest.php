<?php

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\SecurityLog;
use App\Models\User;
use App\Support\BusinessHours;
use Illuminate\Support\Str;

function makePtoAdmin(): User
{
    return User::factory()->create(['role' => UserRole::PtoAdministrator]);
}

function makeMunicipalityFixture(string $name, string $code): Municipality
{
    return Municipality::query()->firstOrCreate(['code' => $code], ['name' => $name]);
}

function makeEstablishmentListingFixture(Municipality $municipality, string $name): Listing
{
    return Listing::query()->create([
        'slug' => Str::slug($name.'-'.Str::random(6)),
        'name' => $name,
        'category' => 'accommodation',
        'municipality' => $municipality->name,
        'municipality_id' => $municipality->id,
        'barangay' => 'Poblacion',
        'status' => 'PUBLISHED',
    ]);
}

test('PTO can create an LGU account', function () {
    $cateel = makeMunicipalityFixture('Cateel', 'CAT');
    $pto = makePtoAdmin();

    $response = test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => 'New LGU Officer',
        'email' => 'new.lgu@example.test',
        'role' => UserRole::Lgu->value,
        'municipality_id' => $cateel->id,
    ]);

    $response->assertRedirect();
    $created = User::query()->where('email', 'new.lgu@example.test')->first();
    expect($created)->not->toBeNull();
    expect($created->role)->toBe(UserRole::Lgu);
    expect($created->municipality_id)->not->toBeNull();
    expect($created->usr_must_change_password)->toBeTrue();
});

test('created_by records which account created a new LGU account', function () {
    $cateel = makeMunicipalityFixture('Cateel', 'CAT');
    $pto = makePtoAdmin();

    test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => 'New LGU Officer',
        'email' => 'created-by.lgu@example.test',
        'role' => UserRole::Lgu->value,
        'municipality_id' => $cateel->id,
    ]);

    $created = User::query()->where('email', 'created-by.lgu@example.test')->first();
    expect($created->created_by)->toBe($pto->id);
});

test('created_by records which account activated a new establishment account', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);
    $listing = makeEstablishmentListingFixture($mati, 'Mati Fixture Inn');

    test()->actingAs($lgu)->post(route('lgu.directory.establishments.switchToOnline', $listing), establishmentAccountPayload());

    $created = User::query()->where('email', 'frontdesk@matifixtureinn.test')->first();
    expect($created->created_by)->toBe($lgu->id);
});

test('self-service settings cannot forge created_by', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListingFixture($mati, 'Mati Fixture Inn 4');
    $creator = User::factory()->create(['role' => UserRole::PtoAdministrator]);
    $user = User::factory()->create([
        'role' => UserRole::Establishment,
        'organization_subtitle' => 'Poblacion, City of Mati',
        'municipality_id' => $mati->id,
        'establishment_id' => $listing->id,
        'created_by' => $creator->id,
    ]);

    test()->actingAs($user)->post(route('establishment.settings.profile'), [
        'name' => $user->name,
        'email' => $user->email,
        'created_by' => 999,
    ])->assertRedirect();

    expect($user->fresh()->created_by)->toBe($creator->id);
});

test('creating a new account records an account_created security log entry', function () {
    $cateel = makeMunicipalityFixture('Cateel', 'CAT');
    $pto = makePtoAdmin();

    test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => 'New LGU Officer',
        'email' => 'security-log.lgu@example.test',
        'role' => UserRole::Lgu->value,
        'municipality_id' => $cateel->id,
    ]);

    $created = User::query()->where('email', 'security-log.lgu@example.test')->first();
    $log = SecurityLog::where('event_type', 'account_created')->where('target_user_id', $created->id)->first();
    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($pto->id);
});

test('suspending and reactivating an account records account_suspended/account_reactivated security log entries', function () {
    $pto = makePtoAdmin();
    $target = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    test()->actingAs($pto)->patch(route('pto.users.toggleStatus', $target));
    expect($target->fresh()->status)->toBe('Inactive');
    $suspendLog = SecurityLog::where('event_type', 'account_suspended')->where('target_user_id', $target->id)->first();
    expect($suspendLog)->not->toBeNull();
    expect($suspendLog->user_id)->toBe($pto->id);

    test()->actingAs($pto)->patch(route('pto.users.toggleStatus', $target));
    expect($target->fresh()->status)->toBe('Active');
    expect(SecurityLog::where('event_type', 'account_reactivated')->where('target_user_id', $target->id)->exists())->toBeTrue();
});

test('changing an account\'s role records a role_changed security log entry', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListingFixture($mati, 'Role Change Fixture Inn');
    $pto = makePtoAdmin();
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);

    test()->actingAs($pto)->put(route('pto.users.update', $lgu), [
        'name' => $lgu->name,
        'email' => $lgu->email,
        'role' => UserRole::Establishment->value,
        'municipality_id' => $mati->id,
        'establishment_id' => $listing->id,
    ])->assertSessionHasNoErrors();

    expect($lgu->fresh()->role)->toBe(UserRole::Establishment);
    $log = SecurityLog::where('event_type', 'role_changed')->where('target_user_id', $lgu->id)->first();
    expect($log)->not->toBeNull();
    expect($log->details)->toBe(['from' => 'lgu', 'to' => 'establishment']);
});

test('editing an account without changing its role does not record a role_changed security log entry', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $pto = makePtoAdmin();
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);

    test()->actingAs($pto)->put(route('pto.users.update', $lgu), [
        'name' => 'Renamed Officer',
        'email' => $lgu->email,
        'role' => UserRole::Lgu->value,
        'municipality_id' => $mati->id,
    ])->assertSessionHasNoErrors();

    expect(SecurityLog::where('event_type', 'role_changed')->where('target_user_id', $lgu->id)->exists())->toBeFalse();
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
    $created = User::query()->where('email', 'new-admin@example.test')->first();
    expect($created)->not->toBeNull();
    expect($created->role)->toBe(UserRole::PtoAdministrator);
});

test('PTO cannot promote an existing LGU account to PTO Administrator', function () {
    $municipality = makeMunicipalityFixture('Cateel', 'CAT');
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'Cateel',
        'municipality_id' => $municipality->id,
    ]);
    $pto = makePtoAdmin();

    $response = test()->actingAs($pto)->put(route('pto.users.update', $lgu), [
        'name' => $lgu->name,
        'email' => $lgu->email,
        'role' => UserRole::PtoAdministrator->value,
    ]);

    $response->assertSessionHasErrors('role');
    expect($lgu->fresh()->role)->toBe(UserRole::Lgu);
});

/**
 * The "Activate Online iTOUR account" form: the account holder and sign-in
 * email only. Everything else comes from the establishment record.
 *
 * @return array<string, string>
 */
function establishmentAccountPayload(array $overrides = []): array
{
    return [
        'account_name' => 'Juan Dela Cruz',
        'account_email' => 'frontdesk@matifixtureinn.test',
        ...$overrides,
    ];
}

test('LGU can activate an account for an establishment in its own municipality', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);
    $listing = makeEstablishmentListingFixture($mati, 'Mati Fixture Inn');

    $response = test()->actingAs($lgu)->post(route('lgu.directory.establishments.switchToOnline', $listing), establishmentAccountPayload());

    $response->assertRedirect(route('lgu.directory.establishments.show', $listing))->assertSessionHasNoErrors();
    $created = User::query()->where('email', 'frontdesk@matifixtureinn.test')->first();
    expect($created)->not->toBeNull();
    expect($created->role)->toBe(UserRole::Establishment);
    expect($created->name)->toBe('Juan Dela Cruz');
    expect($created->organization_name)->toBe('Mati Fixture Inn');
    expect($created->municipality_id)->toBe($mati->id);
    expect($created->establishment_id)->toBe($listing->id);
    expect($listing->fresh()->reportingMethod()->isOnline())->toBeTrue();
});

test('LGU cannot activate an account for an establishment in another municipality', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $baganga = makeMunicipalityFixture('Baganga', 'BAG');
    $matiLgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);
    $bagangaListing = makeEstablishmentListingFixture($baganga, 'Baganga Fixture Inn');

    test()->actingAs($matiLgu)
        ->post(route('lgu.directory.establishments.switchToOnline', $bagangaListing), establishmentAccountPayload())
        ->assertForbidden();

    expect(User::query()->where('email', 'frontdesk@matifixtureinn.test')->exists())->toBeFalse();
    expect($bagangaListing->fresh()->reportingMethod()->isOnline())->toBeFalse();
    expect(SecurityLog::query()->where('user_id', $matiLgu->id)->where('event_type', 'access_denied')->exists())->toBeTrue();
});

test('LGU cannot activate an establishment account for a destination', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);
    $destination = Listing::query()->create([
        'slug' => 'mati-fixture-falls',
        'name' => 'Mati Fixture Falls',
        'category' => 'destinations',
        'municipality' => $mati->name,
        'municipality_id' => $mati->id,
        'barangay' => 'Poblacion',
        'status' => 'Active',
    ]);

    test()->actingAs($lgu)
        ->post(route('lgu.directory.establishments.switchToOnline', $destination), establishmentAccountPayload())
        ->assertNotFound();

    expect(User::query()->where('email', 'frontdesk@matifixtureinn.test')->exists())->toBeFalse();
});

test('LGU can edit an establishment account, but never its establishment or municipality', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListingFixture($mati, 'Old Inn Name');
    $otherListing = makeEstablishmentListingFixture($mati, 'Other Inn');
    $establishmentUser = User::factory()->create([
        'role' => UserRole::Establishment,
        'municipality_id' => $mati->id,
        'establishment_id' => $listing->id,
    ]);
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);

    test()->actingAs($lgu)->put(route('lgu.users.update', $establishmentUser), [
        'name' => 'Maria Santos',
        'email' => 'renamed@matifixtureinn.test',
        // Not accepted by the account form — forged anyway.
        'establishment_id' => $otherListing->id,
        'municipality_id' => 999,
        'role' => UserRole::PtoAdministrator->value,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $fresh = $establishmentUser->fresh();
    expect($fresh->name)->toBe('Maria Santos');
    expect($fresh->email)->toBe('renamed@matifixtureinn.test');
    expect($fresh->role)->toBe(UserRole::Establishment);
    expect($fresh->establishment_id)->toBe($listing->id);
    expect($fresh->municipality_id)->toBe($mati->id);
    expect($listing->fresh()->name)->toBe('Old Inn Name');
});

test('LGU cannot reach the PTO-only account-creation route at all', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $matiLgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
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
        'role' => UserRole::Establishment,
        'organization_subtitle' => 'Poblacion, Baganga',
        'municipality_id' => $baganga->id,
        'establishment_id' => $baganganListing->id,
    ]);
    $matiLgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);

    test()->actingAs($matiLgu)
        ->patch(route('lgu.users.toggleStatus', $baganganEstablishmentUser))
        ->assertForbidden();

    expect($baganganEstablishmentUser->fresh()->status)->toBe('Active');
});

test('nobody can deactivate their own account', function () {
    $pto = makePtoAdmin();
    test()->actingAs($pto)->patch(route('pto.users.toggleStatus', $pto))->assertForbidden();
    expect($pto->fresh()->status)->toBe('Active');

    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);
    test()->actingAs($lgu)->patch(route('lgu.users.toggleStatus', $lgu))->assertForbidden();
});

test('self-service settings cannot change role, status, municipality_id, or establishment_id', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListingFixture($mati, 'Mati Fixture Inn 3');
    $user = User::factory()->create([
        'role' => UserRole::Establishment,
        'organization_subtitle' => 'Poblacion, City of Mati',
        'municipality_id' => $mati->id,
        'establishment_id' => $listing->id,
    ]);

    // Attempted privilege escalation via a payload the self-service
    // settings endpoint never reads — a real mass-assignment probe.
    test()->actingAs($user)->post(route('establishment.settings.profile'), [
        'name' => $user->name,
        'email' => $user->email,
        'role' => 'pto_administrator',
        'status' => 'Inactive',
        'municipality_id' => 999,
        'establishment_id' => 999,
    ])->assertRedirect();

    $fresh = $user->fresh();
    expect($fresh->role)->toBe(UserRole::Establishment);
    expect($fresh->status)->toBe('Active');
    expect($fresh->municipality_id)->toBe($mati->id);
    expect($fresh->establishment_id)->toBe($listing->id);
});

test('the LGU Establishment Accounts table lists each account with its establishment', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);
    $listing = makeEstablishmentListingFixture($mati, 'Mati Fixture Inn');
    $listing->update(['barangay' => 'Dahican', 'contact_phone' => '09171234567']);

    test()->actingAs($lgu)->post(route('lgu.directory.establishments.switchToOnline', $listing), establishmentAccountPayload())->assertRedirect();

    test()->actingAs($lgu)->get(route('lgu.users'))
        ->assertOk()
        ->assertSee('Mati Fixture Inn')
        ->assertSee('Accommodation')
        ->assertSee('Online iTOUR')
        ->assertSee('Dahican')
        ->assertSee('Juan Dela Cruz')
        ->assertSee('09171234567')
        ->assertSee(route('lgu.directory.establishments.show', $listing), false);
});

test('business hours format and parse as a single hours string, including open 24 hours', function () {
    expect(BusinessHours::format('mon-fri', '24h', null))->toBe('Mon–Fri, Open 24 hours');
    expect(BusinessHours::parse('Mon–Fri, Open 24 hours'))->toBe(['days' => 'mon-fri', 'opens' => '24h', 'closes' => null]);
    expect(BusinessHours::parse('Mon–Sun, 8:00 AM – 5:00 PM'))->toBe(['days' => 'mon-sun', 'opens' => '08:00', 'closes' => '17:00']);
});
