<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Verifies the one-active-LGU-per-municipality index and that an LGU account's municipality cannot be changed by anyone but PTO.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->mati = Municipality::query()->create(['mun_name' => 'City of Mati', 'mun_code' => 'MATI']);
    $this->baganga = Municipality::query()->create(['mun_name' => 'Baganga', 'mun_code' => 'BAG']);
});

/**
 * @param  array<string, mixed>  $arrOverrides
 */
function createLguAccountFor(Municipality $objMunicipality, array $arrOverrides = []): User
{
    return User::factory()->create(array_merge([
        'usr_role' => UserRole::Lgu,
        'usr_organization_name' => "{$objMunicipality->mun_name} Tourism Office",
        'usr_organization_subtitle' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
        'usr_status' => 'Active',
    ], $arrOverrides));
}

test('a second active LGU account for the same municipality is rejected by the database', function () {
    createLguAccountFor($this->mati);

    expect(fn () => createLguAccountFor($this->mati))->toThrow(QueryException::class);
});

test('an inactive LGU account may share a municipality with the active one', function () {
    createLguAccountFor($this->mati, ['usr_status' => 'Inactive']);
    createLguAccountFor($this->mati);

    expect(User::query()->where('usr_role', UserRole::Lgu)->where('mun_id', $this->mati->mun_id)->count())->toBe(2);
});

test('reactivating a second LGU account for the same municipality is rejected', function () {
    createLguAccountFor($this->mati);
    $objOldAccount = createLguAccountFor($this->mati, ['usr_status' => 'Inactive']);

    expect(fn () => $objOldAccount->update(['usr_status' => 'Active']))->toThrow(QueryException::class);
});

test('establishment accounts may share a municipality with its LGU account', function () {
    createLguAccountFor($this->mati);

    User::factory()->count(2)->create([
        'usr_role' => UserRole::Establishment,
        'mun_id' => $this->mati->mun_id,
    ]);

    expect(User::query()->where('mun_id', $this->mati->mun_id)->count())->toBe(3);
});

test('each municipality may have its own active LGU account', function () {
    createLguAccountFor($this->mati);
    createLguAccountFor($this->baganga);

    expect(User::query()->where('usr_role', UserRole::Lgu)->count())->toBe(2);
});

test('a signed-in LGU account cannot change its own municipality', function () {
    $objLgu = createLguAccountFor($this->mati);

    $this->actingAs($objLgu);

    expect(fn () => $objLgu->update(['mun_id' => $this->baganga->mun_id]))->toThrow(AuthorizationException::class);
    expect($objLgu->fresh()->mun_id)->toBe($this->mati->mun_id);
});

test('a signed-in PTO Administrator can reassign an LGU account', function () {
    $objLgu = createLguAccountFor($this->mati);
    $objPto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    $this->actingAs($objPto);
    $objLgu->update(['mun_id' => $this->baganga->mun_id]);

    expect($objLgu->fresh()->mun_id)->toBe($this->baganga->mun_id);
});

test('the LGU profile form ignores a forged municipality_id or role in the payload', function () {
    $objLgu = createLguAccountFor($this->mati);

    $this->actingAs($objLgu)
        ->post(route('lgu.settings.profile'), [
            'name' => 'Renamed Officer',
            'email' => $objLgu->usr_email,
            'municipality_id' => $this->baganga->mun_id,
            'role' => UserRole::PtoAdministrator->value,
        ])
        ->assertRedirect();

    $objLgu->refresh();

    expect($objLgu->usr_name)->toBe('Renamed Officer');
    expect($objLgu->mun_id)->toBe($this->mati->mun_id);
    expect($objLgu->usr_role)->toBe(UserRole::Lgu);
});
