<?php

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Support\Str;

function makeEstablishmentListing(string $municipalityName, string $municipalityCode, string $name): Listing
{
    $municipality = Municipality::query()->firstOrCreate(
        ['mun_code' => $municipalityCode],
        ['mun_name' => $municipalityName]
    );

    return Listing::query()->create([
        'lst_slug' => Str::slug($name.'-'.Str::random(6)),
        'lst_name' => $name,
        'lst_category' => 'accommodation',
        'lst_municipality' => $municipality->mun_name,
        'mun_id' => $municipality->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_status' => 'Active',
    ]);
}

function makeEstablishmentUser(Listing $listing): User
{
    return User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'usr_organization_name' => $listing->lst_name,
        'usr_organization_subtitle' => "{$listing->lst_barangay}, {$listing->lst_municipality}",
        'mun_id' => $listing->mun_id,
        'lst_id' => $listing->lst_id,
    ]);
}

test('an establishment user can update only its own listing', function () {
    $own = makeEstablishmentListing('City of Mati', 'MATI', 'My Own Inn');
    $user = makeEstablishmentUser($own);

    test()->actingAs($user)->put(route('establishment.profile.update'), [
        'name' => 'My Own Inn — Renamed',
        'category' => 'accommodation',
        'address' => 'Poblacion',
        'phone' => '0900-000-0000',
        'hours' => '24/7',
    ])->assertRedirect();

    expect($own->fresh()->lst_name)->toBe('My Own Inn — Renamed');
});

test('an establishment user cannot touch another establishment\'s photo via a guessed image id', function () {
    $own = makeEstablishmentListing('City of Mati', 'MATI', 'My Own Inn');
    $other = makeEstablishmentListing('City of Mati', 'MATI', 'A Different Inn');
    $otherImage = ListingImage::query()->create([
        'lst_id' => $other->lst_id,
        'lsi_path' => 'fixture.jpg',
        'lsi_is_primary' => true,
        'lsi_sort_order' => 0,
    ]);

    $user = makeEstablishmentUser($own);

    test()->actingAs($user)
        ->patch(route('establishment.profile.images.primary', $otherImage))
        ->assertForbidden();

    test()->actingAs($user)
        ->delete(route('establishment.profile.images.destroy', $otherImage))
        ->assertForbidden();

    expect($otherImage->fresh())->not->toBeNull();
});

test('an establishment user in one municipality cannot reach another establishment via id, same or different municipality', function () {
    $mati = makeEstablishmentListing('City of Mati', 'MATI', 'Mati Inn');
    $baganga = makeEstablishmentListing('Baganga', 'BAG', 'Baganga Inn');

    $matiUser = makeEstablishmentUser($mati);

    // The establishment routes never take a listing id from the client —
    // "my establishment" always resolves from lst_id on the
    // authenticated user. Renaming via the account's own update endpoint
    // must never affect a different establishment, in-municipality or not.
    test()->actingAs($matiUser)->put(route('establishment.profile.update'), [
        'name' => 'Hijacked',
        'category' => 'accommodation',
        'address' => 'Poblacion',
        'phone' => '0900-000-0000',
        'hours' => '24/7',
    ]);

    expect($baganga->fresh()->lst_name)->toBe('Baganga Inn');
});

test('an establishment account with no linked listing is blocked from profile actions, not crashed', function () {
    $user = User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'usr_organization_name' => 'Unlinked Establishment',
        'usr_organization_subtitle' => 'Nowhere',
        'mun_id' => null,
        'lst_id' => null,
    ]);

    test()->actingAs($user)->put(route('establishment.profile.update'), [
        'name' => 'Anything',
        'category' => 'accommodation',
        'address' => 'Poblacion',
        'phone' => '0900-000-0000',
        'hours' => '24/7',
    ])->assertForbidden();
});
