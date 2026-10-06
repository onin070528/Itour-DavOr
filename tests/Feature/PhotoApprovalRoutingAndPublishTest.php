<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — photo approval routing and publish.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Enums\UserRole;
use App\Models\EstablishmentImage;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('an LGU upload for an establishment in its municipality appears in the PTO queue and not the LGU queue', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $listing = establishmentImageListingFixture(['lst_municipality' => 'City of Mati']);
    $listing->update(['mun_id' => $mati->mun_id]);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    test()->actingAs($lgu)->post(route('lgu.images.store'), [
        'listing_id' => $listing->lst_id,
        'photos' => [UploadedFile::fake()->image('on-behalf.jpg', 1600, 1200)],
        'ownership_declared' => '1',
    ])->assertSessionHasNoErrors();

    $image = EstablishmentImage::query()->where('lst_id', $listing->lst_id)->sole();
    expect($image->img_status->value)->toBe('PENDING');
    expect($image->img_source_role->value)->toBe('LGU');

    test()->actingAs($pto)->get(route('pto.images.index', ['tab' => 'approval']))->assertSee($listing->lst_name);

    // Not a card on the LGU's own approval tab — it's correctly empty
    // (the listing may still legitimately appear elsewhere on that same
    // page, in the "All photos" upload picker).
    test()->actingAs($lgu)->get(route('lgu.images.index', ['tab' => 'approval']))
        ->assertSee('Nothing waiting for approval');
});

test('an establishment upload appears in the LGU queue, never the PTO queue', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $listing = establishmentImageListingFixture(['lst_municipality' => 'City of Mati']);
    $listing->update(['mun_id' => $mati->mun_id]);
    $establishmentUser = establishmentImageUserFixture($listing);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    test()->actingAs($establishmentUser)->post(route('establishment.images.store'), [
        'listing_id' => $listing->lst_id,
        'photos' => [UploadedFile::fake()->image('own.jpg', 1600, 1200)],
        'ownership_declared' => '1',
    ])->assertSessionHasNoErrors();

    test()->actingAs($lgu)->get(route('lgu.images.index', ['tab' => 'approval']))->assertSee($listing->lst_name);

    // Not a card on the PTO's approval tab — it's correctly empty (the
    // listing may still legitimately appear in PTO's own "All photos"
    // upload picker, which lists every establishment province-wide).
    test()->actingAs($pto)->get(route('pto.images.index', ['tab' => 'approval']))
        ->assertSee('Nothing waiting for approval');
});

test('PTO approving publishes the image and it shows on /explore, the detail page, and the landing page, with no cache to clear', function () {
    $listing = establishmentImageListingFixture(['lst_image' => null, 'lst_status' => 'PUBLISHED']);
    $uploader = establishmentImageUserFixture($listing);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    $image = EstablishmentImage::query()->create([
        'lst_id' => $listing->lst_id,
        'img_path' => 'p.jpg', 'img_thumbnail_path' => 'p_thumb.jpg', 'img_alt_text' => $listing->lst_name,
        'img_source_role' => ImageSourceRole::Lgu, 'img_status' => ImageStatus::Pending,
        'img_is_cover' => false, 'img_sort_order' => 1, 'img_hash' => hash('sha256', uniqid()),
        'img_uploaded_by' => $uploader->usr_id, 'img_has_ownership_declared' => true,
    ]);

    // Not public yet.
    test()->get(route('explore'))->assertDontSee(route('establishmentImages.file', [$image, 'full']), false);

    test()->actingAs($pto)->patch(route('pto.images.approveBatch', $listing), [
        'image_ids' => [$image->img_id],
    ])->assertRedirect();

    expect($image->fresh()->img_status->value)->toBe('PUBLISHED');
    expect($image->fresh()->img_is_cover)->toBeTrue(); // Part C: first approved image becomes the cover.

    $coverUrl = route('establishmentImages.file', [$image, 'full']);

    test()->get(route('explore'))->assertSee($coverUrl, false);
    test()->get(route('listings.show', $listing))->assertSee($coverUrl, false);
    test()->get(route('home'))->assertSee($coverUrl, false);
});

test('PTO returning leaves nothing public and the uploader sees the reason', function () {
    $listing = establishmentImageListingFixture();
    $uploader = establishmentImageUserFixture($listing);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    $image = EstablishmentImage::query()->create([
        'lst_id' => $listing->lst_id,
        'img_path' => 'p.jpg', 'img_thumbnail_path' => 'p_thumb.jpg', 'img_alt_text' => $listing->lst_name,
        'img_source_role' => ImageSourceRole::Lgu, 'img_status' => ImageStatus::Pending,
        'img_is_cover' => false, 'img_sort_order' => 1, 'img_hash' => hash('sha256', uniqid()),
        'img_uploaded_by' => $uploader->usr_id, 'img_has_ownership_declared' => true,
    ]);

    test()->actingAs($pto)->patch(route('pto.images.returnBatch', $listing), [
        'image_ids' => [$image->img_id],
        'reason' => 'Too blurry to publish.',
    ])->assertRedirect();

    expect($image->fresh()->img_status->value)->toBe('REJECTED');
    expect($image->fresh()->img_review_note)->toBe('Too blurry to publish.');
    expect($uploader->unreadNotifications()->count())->toBe(1);

    test()->get(route('explore'))->assertDontSee(route('establishmentImages.file', [$image, 'full']), false);
});

test('badges drop to zero after the decision', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $listing = establishmentImageListingFixture(['lst_municipality' => 'City of Mati']);
    $listing->update(['mun_id' => $mati->mun_id]);
    $uploader = establishmentImageUserFixture($listing);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    $image = EstablishmentImage::query()->create([
        'lst_id' => $listing->lst_id,
        'img_path' => 'p.jpg', 'img_thumbnail_path' => 'p_thumb.jpg', 'img_alt_text' => $listing->lst_name,
        'img_source_role' => ImageSourceRole::Establishment, 'img_status' => ImageStatus::Pending,
        'img_is_cover' => false, 'img_sort_order' => 1, 'img_hash' => hash('sha256', uniqid()),
        'img_uploaded_by' => $uploader->usr_id, 'img_has_ownership_declared' => true,
    ]);

    test()->actingAs($lgu)->get(route('lgu.images.index'))->assertSee('>1<', false);

    test()->actingAs($lgu)->patch(route('lgu.images.approveBatch', $listing), [
        'image_ids' => [$image->img_id],
    ])->assertRedirect();

    test()->actingAs($lgu)->get(route('lgu.images.index'))->assertDontSee('>1<', false);
});

test('the PTO queue municipality filter narrows cards to one municipality', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $baganga = Municipality::query()->firstOrCreate(['mun_code' => 'BAG'], ['mun_name' => 'Baganga']);
    $matiListing = establishmentImageListingFixture(['lst_municipality' => 'City of Mati', 'lst_name' => 'Mati Spot']);
    $matiListing->update(['mun_id' => $mati->mun_id]);
    $bagangaListing = establishmentImageListingFixture(['lst_municipality' => 'Baganga', 'lst_name' => 'Baganga Spot']);
    $bagangaListing->update(['mun_id' => $baganga->mun_id]);
    $uploader = establishmentImageUserFixture($matiListing);
    $uploader2 = establishmentImageUserFixture($bagangaListing);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    foreach ([[$matiListing, $uploader], [$bagangaListing, $uploader2]] as [$listing, $user]) {
        EstablishmentImage::query()->create([
            'lst_id' => $listing->lst_id,
            'img_path' => 'p.jpg', 'img_thumbnail_path' => 'p_thumb.jpg', 'img_alt_text' => $listing->lst_name,
            'img_source_role' => ImageSourceRole::Lgu, 'img_status' => ImageStatus::Pending,
            'img_is_cover' => false, 'img_sort_order' => 1, 'img_hash' => hash('sha256', uniqid()),
            'img_uploaded_by' => $user->usr_id, 'img_has_ownership_declared' => true,
        ]);
    }

    $response = test()->actingAs($pto)->get(route('pto.images.index', ['tab' => 'approval', 'municipality' => $mati->mun_id]));

    $response->assertOk();
    $response->assertSee('Mati Spot');
    // Exactly one card — Baganga Spot may still legitimately appear in the
    // "All photos" tab's province-wide establishment picker on this same
    // page, so this checks the card count rather than the name's absence.
    expect(substr_count($response->getContent(), 'Uploaded by'))->toBe(1);
});
