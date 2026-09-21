<?php

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Support\Str;

function makeMunicipality(string $name, string $code): Municipality
{
    return Municipality::query()->create(['name' => $name, 'code' => $code]);
}

function makeLguUser(Municipality $municipality): User
{
    return User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_name' => "{$municipality->name} Tourism Office",
        'organization_subtitle' => $municipality->name,
        'municipality_id' => $municipality->id,
    ]);
}

function makeListing(Municipality $municipality, string $category = 'destinations'): Listing
{
    return Listing::query()->create([
        'slug' => Str::slug($municipality->name.'-'.$category.'-'.Str::random(6)),
        'name' => "{$municipality->name} Test {$category}",
        'category' => $category,
        'municipality' => $municipality->name,
        'municipality_id' => $municipality->id,
        'barangay' => 'Poblacion',
        'status' => 'Active',
    ]);
}

test('PTO can reach the province-wide directory regardless of municipality', function () {
    $mati = makeMunicipality('City of Mati', 'MATI');
    $baganga = makeMunicipality('Baganga', 'BAG');
    $cateel = makeMunicipality('Cateel', 'CAT');
    makeListing($mati);
    makeListing($baganga);
    makeListing($cateel);

    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    test()->actingAs($pto)->get(route('pto.directory.destinations'))->assertOk();
});

test('an LGU can manage a destination inside its own municipality', function () {
    $mati = makeMunicipality('City of Mati', 'MATI');
    $matiDestination = makeListing($mati, 'destinations');
    $matiLgu = makeLguUser($mati);

    test()->actingAs($matiLgu)
        ->patch(route('lgu.directory.destinations.archive', $matiDestination))
        ->assertRedirect();

    expect($matiDestination->fresh()->status)->toBe('Archived');
});

test('an LGU cannot update or archive a destination belonging to another municipality', function () {
    $mati = makeMunicipality('City of Mati', 'MATI');
    $baganga = makeMunicipality('Baganga', 'BAG');
    $baganganDestination = makeListing($baganga, 'destinations');
    $matiLgu = makeLguUser($mati);

    test()->actingAs($matiLgu)->put(route('lgu.directory.destinations.update', $baganganDestination), [
        'name' => 'Hijacked Name',
        'barangay' => 'Nowhere',
    ])->assertForbidden();

    test()->actingAs($matiLgu)
        ->patch(route('lgu.directory.destinations.archive', $baganganDestination))
        ->assertForbidden();

    expect($baganganDestination->fresh()->status)->toBe('Active');
    expect($baganganDestination->fresh()->name)->not->toBe('Hijacked Name');
});

test('an LGU cannot verify an establishment belonging to another municipality', function () {
    $mati = makeMunicipality('City of Mati', 'MATI');
    $baganga = makeMunicipality('Baganga', 'BAG');
    $baganganEstablishment = makeListing($baganga, 'accommodation');
    $baganganEstablishment->update(['status' => 'Pending Review']);
    $matiLgu = makeLguUser($mati);

    test()->actingAs($matiLgu)
        ->patch(route('lgu.directory.establishments.verify', $baganganEstablishment))
        ->assertForbidden();

    expect($baganganEstablishment->fresh()->status)->toBe('Pending Review');
});

test('direct listing id manipulation across municipalities never returns 200', function () {
    $mati = makeMunicipality('City of Mati', 'MATI');
    $baganga = makeMunicipality('Baganga', 'BAG');
    $baganganDestination = makeListing($baganga, 'destinations');
    $matiLgu = makeLguUser($mati);

    $response = test()->actingAs($matiLgu)
        ->patch(route('lgu.directory.destinations.archive', $baganganDestination));

    expect($response->status())->toBe(403);
});

test('an LGU with no assigned municipality cannot reach LGU-scoped pages', function () {
    $unassigned = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => null,
        'municipality_id' => null,
    ]);

    test()->actingAs($unassigned)->get(route('lgu.dashboard'))->assertForbidden();
});
