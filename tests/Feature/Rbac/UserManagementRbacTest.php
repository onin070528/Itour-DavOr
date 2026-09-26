<?php

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Municipality;
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
        'status' => 'Active',
    ]);
}

test('PTO can create an LGU account', function () {
    makeMunicipalityFixture('Cateel', 'CAT');
    $pto = makePtoAdmin();

    $response = test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => 'New LGU Officer',
        'email' => 'new.lgu@example.test',
        'role' => 'LGU Tourism Personnel',
        'assignment' => 'Cateel',
    ]);

    $response->assertRedirect();
    $created = User::query()->where('email', 'new.lgu@example.test')->first();
    expect($created)->not->toBeNull();
    expect($created->role)->toBe(UserRole::Lgu);
    expect($created->municipality_id)->not->toBeNull();
});

test('PTO cannot create a new PTO account through the Users page', function () {
    $pto = makePtoAdmin();

    $response = test()->actingAs($pto)->post(route('pto.users.store'), [
        'name' => 'Sneaky New Admin',
        'email' => 'sneaky@example.test',
        'role' => 'PTO Administrator',
        'assignment' => 'Province of Davao Oriental',
    ]);

    $response->assertSessionHasErrors('role');
    expect(User::query()->where('email', 'sneaky@example.test')->exists())->toBeFalse();
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
        'role' => 'PTO Administrator',
        'assignment' => 'Province of Davao Oriental',
    ]);

    $response->assertSessionHasErrors('role');
    expect($lgu->fresh()->role)->toBe(UserRole::Lgu);
});

/**
 * @return array<string, string>
 */
function establishmentFormPayload(array $overrides = []): array
{
    return [
        'name' => 'Mati Fixture Inn',
        'category' => 'accommodation',
        'barangay' => 'Dahican',
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
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);

    $response = test()->actingAs($lgu)->post(route('lgu.users.store'), establishmentFormPayload());

    $response->assertRedirect()->assertSessionHasNoErrors();
    $created = User::query()->where('email', 'frontdesk@matifixtureinn.test')->first();
    expect($created)->not->toBeNull();
    expect($created->role)->toBe(UserRole::Establishment);
    expect($created->name)->toBe('Juan Dela Cruz');
    expect($created->municipality_id)->toBe($mati->id);

    $listing = $created->establishment;
    expect($listing)->not->toBeNull();
    expect($listing->name)->toBe('Mati Fixture Inn');
    expect($listing->category)->toBe('accommodation');
    expect($listing->barangay)->toBe('Dahican');
    expect($listing->owner_name)->toBe('Juan Dela Cruz');
    expect($listing->contact_phone)->toBe('09171234567');
    expect($listing->municipality_id)->toBe($mati->id);
});

test('LGU-registered establishments are always placed in the LGU\'s own municipality', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $baganga = makeMunicipalityFixture('Baganga', 'BAG');
    $matiLgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);

    test()->actingAs($matiLgu)->post(route('lgu.users.store'), establishmentFormPayload([
        'email' => 'frontdesk@baganganfixtureinn.test',
        'municipality_id' => (string) $baganga->id,
        'municipality' => 'Baganga',
    ]))->assertRedirect();

    $created = User::query()->where('email', 'frontdesk@baganganfixtureinn.test')->first();
    expect($created->municipality_id)->toBe($mati->id);
    expect($created->establishment->municipality_id)->toBe($mati->id);
});

test('LGU cannot register an establishment under the destinations category', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);

    test()->actingAs($lgu)
        ->post(route('lgu.users.store'), establishmentFormPayload(['category' => 'destinations']))
        ->assertSessionHasErrors('category');

    expect(User::query()->where('email', 'frontdesk@matifixtureinn.test')->exists())->toBeFalse();
});

test('LGU can edit an establishment\'s information and account', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListingFixture($mati, 'Old Inn Name');
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

    test()->actingAs($lgu)->put(route('lgu.users.update', $establishmentUser), establishmentFormPayload([
        'name' => 'New Inn Name',
        'email' => 'renamed@matifixtureinn.test',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $fresh = $establishmentUser->fresh();
    expect($fresh->email)->toBe('renamed@matifixtureinn.test');
    expect($fresh->organization_name)->toBe('New Inn Name');
    expect($fresh->establishment_id)->toBe($listing->id);
    expect($listing->fresh()->name)->toBe('New Inn Name');
    expect($listing->fresh()->hours)->toBe('Mon–Sun, 8:00 AM – 5:00 PM');
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

test('the LGU Users table lists each establishment\'s saved information', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
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
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);

    test()->actingAs($lgu)->post(route('lgu.users.store'), establishmentFormPayload([
        'hoursDays' => 'mon-fri',
        'hoursOpen' => '24h',
        'hoursClose' => '',
    ]))->assertSessionHasNoErrors();

    $listing = User::query()->where('email', 'frontdesk@matifixtureinn.test')->first()->establishment;
    expect($listing->hours)->toBe('Mon–Fri, Open 24 hours');
    expect(BusinessHours::parse($listing->hours))->toBe(['days' => 'mon-fri', 'opens' => '24h', 'closes' => null]);
    expect(BusinessHours::parse('Mon–Sun, 8:00 AM – 5:00 PM'))->toBe(['days' => 'mon-sun', 'opens' => '08:00', 'closes' => '17:00']);
});

test('business hours require a closing time unless open 24 hours', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);

    test()->actingAs($lgu)
        ->post(route('lgu.users.store'), establishmentFormPayload(['hoursClose' => '']))
        ->assertSessionHasErrors('hoursClose');

    test()->actingAs($lgu)
        ->post(route('lgu.users.store'), establishmentFormPayload(['hoursDays' => '']))
        ->assertSessionHasErrors('hoursDays');
});
