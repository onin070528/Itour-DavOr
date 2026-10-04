<?php

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The QR-enabled "Accommodation" category every makeEstablishmentListing()
 * fixture is filed under, so Listing::isQrEnabled() (status Active, not a
 * Tour Guide, category QR-enabled) is true for every establishment fixture
 * by default — matching how real establishments are categorized post
 * Tourism Directory (Stage 2).
 */
function qrEnabledCategoryFixture(): Category
{
    return Category::query()->firstOrCreate(
        ['cat_name' => 'Accommodation'],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );
}

function makeEstablishmentListing(string $municipalityName, string $municipalityCode, string $name, array $overrides = []): Listing
{
    $municipality = Municipality::query()->firstOrCreate(
        ['code' => $municipalityCode],
        ['name' => $municipalityName]
    );

    return Listing::query()->create(array_merge([
        'slug' => Str::slug($name.'-'.Str::random(6)),
        'name' => $name,
        'category' => 'accommodation',
        'cat_id' => qrEnabledCategoryFixture()->cat_id,
        'municipality' => $municipality->name,
        'municipality_id' => $municipality->id,
        'barangay' => 'Poblacion',
        'status' => 'PUBLISHED',
    ], $overrides));
}

function makeEstablishmentUser(Listing $listing): User
{
    return User::factory()->create([
        'role' => UserRole::Establishment,
        'organization_name' => $listing->name,
        'organization_subtitle' => "{$listing->barangay}, {$listing->municipality}",
        'municipality_id' => $listing->municipality_id,
        'establishment_id' => $listing->id,
    ]);
}

test('an establishment user can update only its own listing', function () {
    // DRAFT: establishment self-edits are only allowed while the package
    // is editable (see App\Policies\ListingPolicy, the self-review merge).
    $own = makeEstablishmentListing('City of Mati', 'MATI', 'My Own Inn', ['status' => 'DRAFT']);
    $user = makeEstablishmentUser($own);

    test()->actingAs($user)->put(route('establishment.profile.update'), [
        'name' => 'My Own Inn — Renamed',
        'category' => 'accommodation',
        'address' => 'Poblacion',
        'phone' => '0900-000-0000',
        'hours' => '24/7',
    ])->assertRedirect();

    expect($own->fresh()->name)->toBe('My Own Inn — Renamed');
});

test('an establishment user cannot touch another establishment\'s photo via a guessed image id', function () {
    $own = makeEstablishmentListing('City of Mati', 'MATI', 'My Own Inn');
    $other = makeEstablishmentListing('City of Mati', 'MATI', 'A Different Inn');
    $otherUser = makeEstablishmentUser($other);
    $otherImage = EstablishmentImage::query()->create([
        'listing_id' => $other->id,
        'img_path' => 'fixture.jpg',
        'img_thumbnail_path' => 'fixture_thumb.jpg',
        'img_alt_text' => $other->name,
        'img_source_role' => ImageSourceRole::Establishment,
        'img_status' => ImageStatus::Published,
        'img_is_cover' => true,
        'img_sort_order' => 0,
        'img_hash' => hash('sha256', 'guessed-id-fixture'),
        'img_uploaded_by' => $otherUser->id,
        'img_has_ownership_declared' => true,
    ]);

    $user = makeEstablishmentUser($own);

    test()->actingAs($user)
        ->patch(route('establishment.images.cover', $otherImage))
        ->assertForbidden();

    test()->actingAs($user)
        ->patch(route('establishment.images.remove', $otherImage))
        ->assertForbidden();

    expect($otherImage->fresh()->img_is_cover)->toBeTrue();
});

test('an establishment user in one municipality cannot reach another establishment via id, same or different municipality', function () {
    $mati = makeEstablishmentListing('City of Mati', 'MATI', 'Mati Inn');
    $baganga = makeEstablishmentListing('Baganga', 'BAG', 'Baganga Inn');

    $matiUser = makeEstablishmentUser($mati);

    // The establishment routes never take a listing id from the client —
    // "my establishment" always resolves from establishment_id on the
    // authenticated user. Renaming via the account's own update endpoint
    // must never affect a different establishment, in-municipality or not.
    test()->actingAs($matiUser)->put(route('establishment.profile.update'), [
        'name' => 'Hijacked',
        'category' => 'accommodation',
        'address' => 'Poblacion',
        'phone' => '0900-000-0000',
        'hours' => '24/7',
    ]);

    expect($baganga->fresh()->name)->toBe('Baganga Inn');
});

test('an establishment account with no linked listing is blocked from profile actions, not crashed', function () {
    $user = User::factory()->create([
        'role' => UserRole::Establishment,
        'organization_name' => 'Unlinked Establishment',
        'organization_subtitle' => 'Nowhere',
        'municipality_id' => null,
        'establishment_id' => null,
    ]);

    test()->actingAs($user)->put(route('establishment.profile.update'), [
        'name' => 'Anything',
        'category' => 'accommodation',
        'address' => 'Poblacion',
        'phone' => '0900-000-0000',
        'hours' => '24/7',
    ])->assertForbidden();
});
