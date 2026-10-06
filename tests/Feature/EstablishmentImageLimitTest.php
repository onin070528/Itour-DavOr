<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — establishment image limit.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Enums\UserRole;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use App\Services\EstablishmentImageUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Creates one EstablishmentImage row directly (bypassing the uploader, and
 * therefore the cap) — used to seed a listing at an exact live-image count
 * without processing real files for every one.
 */
function establishmentImageRowFixture(Listing $listing, User $uploader, array $overrides = []): EstablishmentImage
{
    return EstablishmentImage::query()->create(array_merge([
        'lst_id' => $listing->lst_id,
        'img_path' => 'establishment-images/'.$listing->lst_id.'/'.uniqid().'.jpg',
        'img_thumbnail_path' => 'establishment-images/'.$listing->lst_id.'/'.uniqid().'_thumb.jpg',
        'img_alt_text' => $listing->lst_name,
        'img_source_role' => ImageSourceRole::Establishment,
        'img_status' => ImageStatus::Published,
        'img_is_cover' => false,
        'img_sort_order' => 1,
        'img_hash' => hash('sha256', 'fixture-'.uniqid()),
        'img_uploaded_by' => $uploader->usr_id,
        'img_has_ownership_declared' => true,
    ], $overrides));
}

test('uploading the 5th image succeeds and a 6th is rejected with the plain message', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);

    foreach (range(1, 4) as $i) {
        establishmentImageRowFixture($listing, $user, ['img_hash' => hash('sha256', "seed-{$i}")]);
    }
    expect($listing->fresh()->liveImageCount())->toBe(4);

    test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->lst_id,
        'photos' => [UploadedFile::fake()->image('fifth.jpg', 1600, 1200)],
        'ownership_declared' => '1',
    ])->assertSessionHasNoErrors();
    expect($listing->fresh()->liveImageCount())->toBe(5);

    $response = test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->lst_id,
        'photos' => [UploadedFile::fake()->image('sixth.jpg', 1601, 1200)],
        'ownership_declared' => '1',
    ]);

    $response->assertSessionHasErrors(['photos' => 'You have reached 5 photos. Remove one to add another.']);
    expect($listing->fresh()->liveImageCount())->toBe(5);
});

test('PENDING images count toward the limit; REJECTED and ARCHIVED do not', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);

    establishmentImageRowFixture($listing, $user, ['img_status' => ImageStatus::Pending, 'img_hash' => hash('sha256', 'p1')]);
    establishmentImageRowFixture($listing, $user, ['img_status' => ImageStatus::Rejected, 'img_hash' => hash('sha256', 'r1')]);
    establishmentImageRowFixture($listing, $user, ['img_status' => ImageStatus::Archived, 'img_hash' => hash('sha256', 'a1')]);

    expect($listing->fresh()->liveImageCount())->toBe(1);
    expect($listing->fresh()->getRemainingSlots())->toBe(4);
});

test('a pending Replace at 5 of 5 is allowed and is not counted as a 6th image', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $listing = establishmentImageListingFixture(['lst_municipality' => 'City of Mati']);
    $listing->update(['mun_id' => $mati->mun_id]);
    $user = establishmentImageUserFixture($listing);

    $cover = establishmentImageRowFixture($listing, $user, ['img_is_cover' => true, 'img_hash' => hash('sha256', 'cover')]);
    foreach (range(1, 4) as $i) {
        establishmentImageRowFixture($listing, $user, ['img_hash' => hash('sha256', "seed-{$i}")]);
    }
    expect($listing->fresh()->liveImageCount())->toBe(5);

    $response = test()->actingAs($user)->post(route('establishment.images.replace', $cover), [
        'photo' => UploadedFile::fake()->image('replacement.jpg', 1600, 1200),
    ]);

    $response->assertSessionHasNoErrors();
    expect($listing->fresh()->liveImageCount())->toBe(5);

    // Uploading a brand-new (non-replace) photo is still correctly blocked —
    // the pending replace must not have freed a phantom slot.
    test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->lst_id,
        'photos' => [UploadedFile::fake()->image('extra.jpg', 1602, 1200)],
        'ownership_declared' => '1',
    ])->assertSessionHasErrors('photos');
});

test('selecting more files than the remaining slots is rejected entirely, and nothing is saved', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);

    foreach (range(1, 3) as $i) {
        establishmentImageRowFixture($listing, $user, ['img_hash' => hash('sha256', "seed-{$i}")]);
    }
    expect($listing->fresh()->getRemainingSlots())->toBe(2);

    $response = test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->lst_id,
        'photos' => [
            UploadedFile::fake()->image('a.jpg', 1600, 1200),
            UploadedFile::fake()->image('b.jpg', 1601, 1200),
            UploadedFile::fake()->image('c.jpg', 1602, 1200),
        ],
        'ownership_declared' => '1',
    ]);

    $response->assertSessionHasErrors(['photos' => 'You can add only 2 more photos. Please select fewer.']);
    expect($listing->fresh()->liveImageCount())->toBe(3);
});

test('two uploads racing at 4 of 5 result in exactly 5, never 6, because the cap re-check is locked', function () {
    // True simultaneous requests can't be produced in this single-connection
    // test process; this proves the service's locked, authoritative
    // re-check (not just the pre-check) is what actually blocks the
    // second call — by invoking the uploader directly twice in a row
    // without re-deriving $listing in between, the way two already-in-
    // flight requests would each independently call it.
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);

    foreach (range(1, 4) as $i) {
        establishmentImageRowFixture($listing, $user, ['img_hash' => hash('sha256', "seed-{$i}")]);
    }
    expect($listing->fresh()->liveImageCount())->toBe(4);

    $objUploader = app(EstablishmentImageUploader::class);

    $objUploader->upload($user, $listing, [UploadedFile::fake()->image('race-a.jpg', 1700, 1200)], null);

    expect(fn () => $objUploader->upload($user, $listing, [UploadedFile::fake()->image('race-b.jpg', 1701, 1200)], null))
        ->toThrow(ValidationException::class);

    expect($listing->fresh()->liveImageCount())->toBe(5);
});

test('an establishment that already has 7 images keeps all 7, cannot add more, and the management page shows the notice', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);

    foreach (range(1, 7) as $i) {
        establishmentImageRowFixture($listing, $user, ['img_hash' => hash('sha256', "legacy-{$i}")]);
    }
    expect($listing->fresh()->liveImageCount())->toBe(7);

    $response = test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->lst_id,
        'photos' => [UploadedFile::fake()->image('eighth.jpg', 1600, 1200)],
        'ownership_declared' => '1',
    ]);

    $response->assertSessionHasErrors(['photos' => 'You have reached 5 photos. Remove one to add another.']);
    expect($listing->fresh()->liveImageCount())->toBe(7);

    $pageResponse = test()->actingAs($user)->get(route('establishment.profile'));
    $pageResponse->assertOk();
    $pageResponse->assertSee('This listing has more than 5 photos. You cannot add more until some are removed.');
    $pageResponse->assertSee('7 of 5 photos');
});

test('removing an image frees a slot immediately', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);

    $images = collect(range(1, 5))->map(fn ($i) => establishmentImageRowFixture($listing, $user, ['img_hash' => hash('sha256', "seed-{$i}")]));
    expect($listing->fresh()->getRemainingSlots())->toBe(0);

    test()->actingAs($user)->patch(route('establishment.images.remove', $images->first()))->assertRedirect();

    expect($listing->fresh()->getRemainingSlots())->toBe(1);

    test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->lst_id,
        'photos' => [UploadedFile::fake()->image('new.jpg', 1600, 1200)],
        'ownership_declared' => '1',
    ])->assertSessionHasNoErrors();
});

test('the Add Photo button is disabled at the limit on the Establishment, LGU, and PTO management pages', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);

    // Establishment view: its own linked account.
    $establishmentListing = establishmentImageListingFixture();
    $establishmentUser = establishmentImageUserFixture($establishmentListing);
    foreach (range(1, 5) as $i) {
        establishmentImageRowFixture($establishmentListing, $establishmentUser, ['img_hash' => hash('sha256', "est-{$i}")]);
    }

    // LGU view: only an establishment the LGU is actually eligible to
    // upload for (I1 — no linked account, or reporting_mode PAPER_LGU) is
    // worth testing here; ImagePolicy::uploadFor() is exercised elsewhere.
    $lguListing = establishmentImageListingFixture(['lst_name' => 'Paper Establishment', 'lst_municipality' => 'City of Mati', 'lst_reporting_mode' => 'PAPER_LGU']);
    $lguListing->update(['mun_id' => $mati->mun_id]);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);
    foreach (range(1, 5) as $i) {
        establishmentImageRowFixture($lguListing, $establishmentUser, ['img_hash' => hash('sha256', "lgu-{$i}")]);
    }

    // PTO view: any establishment — PTO may always upload.
    $ptoListing = establishmentImageListingFixture(['lst_name' => 'PTO Managed Establishment']);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    foreach (range(1, 5) as $i) {
        establishmentImageRowFixture($ptoListing, $establishmentUser, ['img_hash' => hash('sha256', "pto-{$i}")]);
    }

    $establishmentResponse = test()->actingAs($establishmentUser)->get(route('establishment.profile'));
    $establishmentResponse->assertOk();
    $establishmentResponse->assertSee('disabled', false);
    $establishmentResponse->assertDontSee('data-modal-open="add-photo-modal"', false);

    $lguResponse = test()->actingAs($lgu)->get(route('lgu.images.manage', $lguListing));
    $lguResponse->assertOk();
    $lguResponse->assertSee('disabled', false);
    $lguResponse->assertDontSee('data-modal-open="add-photo-modal"', false);

    $ptoResponse = test()->actingAs($pto)->get(route('pto.images.manage', $ptoListing));
    $ptoResponse->assertOk();
    $ptoResponse->assertSee('disabled', false);
    $ptoResponse->assertDontSee('data-modal-open="add-photo-modal"', false);
});
