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
    $this->mati = Municipality::query()->create(['name' => 'City of Mati', 'code' => 'MATI']);
    $this->baganga = Municipality::query()->create(['name' => 'Baganga', 'code' => 'BAG']);
});

/**
 * @param  array<string, mixed>  $arrOverrides
 */
function createLguAccountFor(Municipality $objMunicipality, array $arrOverrides = []): User
{
    return User::factory()->create(array_merge([
        'role' => UserRole::Lgu,
        'organization_name' => "{$objMunicipality->name} Tourism Office",
        'organization_subtitle' => $objMunicipality->name,
        'municipality_id' => $objMunicipality->id,
        'status' => 'Active',
    ], $arrOverrides));
}

test('a second active LGU account for the same municipality is rejected by the database', function () {
    createLguAccountFor($this->mati);

    expect(fn () => createLguAccountFor($this->mati))->toThrow(QueryException::class);
});

test('an inactive LGU account may share a municipality with the active one', function () {
    createLguAccountFor($this->mati, ['status' => 'Inactive']);
    createLguAccountFor($this->mati);

    expect(User::query()->where('role', UserRole::Lgu)->where('municipality_id', $this->mati->id)->count())->toBe(2);
});

test('reactivating a second LGU account for the same municipality is rejected', function () {
    createLguAccountFor($this->mati);
    $objOldAccount = createLguAccountFor($this->mati, ['status' => 'Inactive']);

    expect(fn () => $objOldAccount->update(['status' => 'Active']))->toThrow(QueryException::class);
});

test('establishment accounts may share a municipality with its LGU account', function () {
    createLguAccountFor($this->mati);

    User::factory()->count(2)->create([
        'role' => UserRole::Establishment,
        'municipality_id' => $this->mati->id,
    ]);

    expect(User::query()->where('municipality_id', $this->mati->id)->count())->toBe(3);
});

test('each municipality may have its own active LGU account', function () {
    createLguAccountFor($this->mati);
    createLguAccountFor($this->baganga);

    expect(User::query()->where('role', UserRole::Lgu)->count())->toBe(2);
});

test('a signed-in LGU account cannot change its own municipality', function () {
    $objLgu = createLguAccountFor($this->mati);

    $this->actingAs($objLgu);

    expect(fn () => $objLgu->update(['municipality_id' => $this->baganga->id]))->toThrow(AuthorizationException::class);
    expect($objLgu->fresh()->municipality_id)->toBe($this->mati->id);
});

test('a signed-in PTO Administrator can reassign an LGU account', function () {
    $objLgu = createLguAccountFor($this->mati);
    $objPto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    $this->actingAs($objPto);
    $objLgu->update(['municipality_id' => $this->baganga->id]);

    expect($objLgu->fresh()->municipality_id)->toBe($this->baganga->id);
});

test('the LGU profile form ignores a forged municipality_id or role in the payload', function () {
    $objLgu = createLguAccountFor($this->mati);

    $this->actingAs($objLgu)
        ->post(route('lgu.settings.profile'), [
            'name' => 'Renamed Officer',
            'email' => $objLgu->email,
            'municipality_id' => $this->baganga->id,
            'role' => UserRole::PtoAdministrator->value,
        ])
        ->assertRedirect();

    $objLgu->refresh();

    expect($objLgu->name)->toBe('Renamed Officer');
    expect($objLgu->municipality_id)->toBe($this->mati->id);
    expect($objLgu->role)->toBe(UserRole::Lgu);
});
