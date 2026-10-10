<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — municipality scoping.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Support\Str;

function makeMunicipality(string $name, string $code): Municipality
{
    return Municipality::query()->create(['mun_name' => $name, 'mun_code' => $code]);
}

function makeLguUser(Municipality $municipality): User
{
    return User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_name' => "{$municipality->mun_name} Tourism Office",
        'usr_organization_subtitle' => $municipality->mun_name,
        'mun_id' => $municipality->mun_id,
    ]);
}

function makeListing(Municipality $municipality, string $category = 'destinations'): Listing
{
    return Listing::query()->create([
        'lst_slug' => Str::slug($municipality->mun_name.'-'.$category.'-'.Str::random(6)),
        'lst_name' => "{$municipality->mun_name} Test {$category}",
        'lst_category' => $category,
        'lst_municipality' => $municipality->mun_name,
        'mun_id' => $municipality->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_status' => 'Active',
    ]);
}

test('PTO can reach the province-wide directory regardless of municipality', function () {
    $mati = makeMunicipality('City of Mati', 'MATI');
    $baganga = makeMunicipality('Baganga', 'BAG');
    $cateel = makeMunicipality('Cateel', 'CAT');
    makeListing($mati);
    makeListing($baganga);
    makeListing($cateel);

    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    test()->actingAs($pto)->get(route('pto.directory.index'))->assertOk();
});

test('an LGU can manage a destination inside its own municipality, but archiving is PTO-only', function () {
    $mati = makeMunicipality('City of Mati', 'MATI');
    $matiDestination = makeListing($mati, 'destinations');
    $matiLgu = makeLguUser($mati);

    test()->actingAs($matiLgu)->put(route('lgu.directory.destinations.update', $matiDestination), [
        'name' => $matiDestination->lst_name,
        'barangay' => $matiDestination->lst_barangay,
        'contactPhone' => '09171234567',
    ])->assertRedirect();

    // Objective 3 (D3): a direct LGU archive request is refused, never silently performed.
    test()->actingAs($matiLgu)
        ->patch(route('lgu.directory.destinations.archive', $matiDestination))
        ->assertForbidden();

    expect($matiDestination->fresh()->lst_contact_phone)->toBe('09171234567');
    expect($matiDestination->fresh()->lst_status)->toBe('Active');
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

    expect($baganganDestination->fresh()->lst_status)->toBe('Active');
    expect($baganganDestination->fresh()->lst_name)->not->toBe('Hijacked Name');
});

test('an LGU cannot submit to PTO an establishment belonging to another municipality', function () {
    $mati = makeMunicipality('City of Mati', 'MATI');
    $baganga = makeMunicipality('Baganga', 'BAG');
    $baganganEstablishment = makeListing($baganga, 'accommodation');
    $baganganEstablishment->update(['lst_status' => 'DRAFT']);
    $matiLgu = makeLguUser($mati);

    test()->actingAs($matiLgu)
        ->patch(route('lgu.directory.establishments.submit', $baganganEstablishment))
        ->assertForbidden();

    expect($baganganEstablishment->fresh()->lst_status)->toBe('DRAFT');
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

test('a denied cross-municipality action is security-logged without request details', function () {
    $mati = makeMunicipality('City of Mati', 'MATI');
    $baganga = makeMunicipality('Baganga', 'BAG');
    $baganganDestination = makeListing($baganga, 'destinations');
    $matiLgu = makeLguUser($mati);

    test()->actingAs($matiLgu)
        ->patch(route('lgu.directory.destinations.archive', $baganganDestination))
        ->assertForbidden();

    $log = SecurityLog::query()
        ->where('usr_id', $matiLgu->usr_id)
        ->where('sec_event_type', 'access_denied')
        ->latest('sec_id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->mun_id)->toBe($mati->mun_id)
        ->and($log->sec_details)->toMatchArray([
            'ability' => 'municipality_scope',
            'target_municipality_id' => $baganga->mun_id,
        ]);
});

test('an LGU with no assigned municipality cannot reach LGU-scoped pages', function () {
    $unassigned = User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => null,
        'mun_id' => null,
    ]);

    test()->actingAs($unassigned)->get(route('lgu.dashboard'))->assertForbidden();
});
