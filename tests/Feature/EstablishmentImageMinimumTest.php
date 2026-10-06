<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — establishment image minimum.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Http\UploadedFile;

test('uploading 1 to 5 files succeeds', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);

    test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->lst_id,
        'photos' => [UploadedFile::fake()->image('one.jpg', 1600, 1200)],
        'ownership_declared' => '1',
    ])->assertSessionHasNoErrors();
    expect($listing->fresh()->liveImageCount())->toBe(1);

    test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->lst_id,
        'photos' => [
            UploadedFile::fake()->image('two.jpg', 1601, 1200),
            UploadedFile::fake()->image('three.jpg', 1602, 1200),
            UploadedFile::fake()->image('four.jpg', 1603, 1200),
            UploadedFile::fake()->image('five.jpg', 1604, 1200),
        ],
        'ownership_declared' => '1',
    ])->assertSessionHasNoErrors();
    expect($listing->fresh()->liveImageCount())->toBe(5);
});

test('uploading 0 files is rejected with the plain minimum message', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);

    $response = test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->lst_id,
        'photos' => [],
        'ownership_declared' => '1',
    ]);

    $response->assertSessionHasErrors(['photos' => 'Please select at least 1 photo.']);
    expect($listing->fresh()->liveImageCount())->toBe(0);
});

test('uploading 6 files is still rejected (the maximum is unchanged)', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);

    $response = test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->lst_id,
        'photos' => collect(range(1, 6))->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg", 1600 + $i, 1200))->all(),
        'ownership_declared' => '1',
    ]);

    $response->assertSessionHasErrors();
    expect($listing->fresh()->liveImageCount())->toBe(0);
});

test('removing the last image succeeds, needs no approval, and the category placeholder shows on /explore', function () {
    $listing = establishmentImageListingFixture(['lst_image' => null]);
    $user = establishmentImageUserFixture($listing);
    $image = establishmentImageRowFixture($listing, $user, ['img_is_cover' => true]);

    test()->actingAs($user)->patch(route('establishment.images.remove', $image))->assertRedirect();

    expect($image->fresh()->img_status->value)->toBe('ARCHIVED');
    expect($listing->fresh()->liveImageCount())->toBe(0);

    $exploreResponse = test()->get(route('explore'));
    $exploreResponse->assertOk();
    // No cover image and no legacy `image` column left to fall back to —
    // the category placeholder icon (Accommodation) renders instead.
    $exploreResponse->assertSee('ti-bed', false);
});

test('no Remove button is ever disabled by a minimum, all the way down to zero photos', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);
    $images = collect(range(1, 3))->map(fn ($i) => establishmentImageRowFixture($listing, $user, ['img_hash' => hash('sha256', "seed-{$i}")]));

    foreach ($images as $image) {
        $response = test()->actingAs($user)->get(route('establishment.profile'));
        $response->assertOk();
        // At 1-3 photos the establishment is nowhere near the max-5 cap
        // either, so Remove's own markup should never carry a disabled
        // attribute — it has never been gated by any minimum. (The merged
        // page's Municipality field is always disabled by design — a
        // different, unrelated field — so the check is scoped to Remove's
        // own button markup rather than the whole page.)
        $response->assertDontSee('disabled>Remove<', false);
        $response->assertSee('>Remove<', false);

        test()->actingAs($user)->patch(route('establishment.images.remove', $image))->assertRedirect();
    }

    expect($listing->fresh()->liveImageCount())->toBe(0);

    // The page still renders cleanly with zero live photos, and nothing
    // about Remove was ever gated.
    test()->actingAs($user)->get(route('establishment.profile'))->assertOk();
});

test('the "more photos" suggestion appears below 3 photos and disappears at 3 or more', function () {
    $listing = establishmentImageListingFixture();
    $user = establishmentImageUserFixture($listing);

    establishmentImageRowFixture($listing, $user, ['img_hash' => hash('sha256', 'one')]);
    establishmentImageRowFixture($listing, $user, ['img_hash' => hash('sha256', 'two')]);

    $belowThreeResponse = test()->actingAs($user)->get(route('establishment.profile'));
    $belowThreeResponse->assertOk();
    $belowThreeResponse->assertSee('Listings with 3 or more photos get more views.');

    establishmentImageRowFixture($listing, $user, ['img_hash' => hash('sha256', 'three')]);

    $atThreeResponse = test()->actingAs($user)->get(route('establishment.profile'));
    $atThreeResponse->assertOk();
    $atThreeResponse->assertDontSee('Listings with 3 or more photos get more views.');
});

test('the suggestion never appears on a public page', function () {
    $listing = establishmentImageListingFixture(['lst_status' => 'PUBLISHED']);
    $user = establishmentImageUserFixture($listing);
    establishmentImageRowFixture($listing, $user, ['img_is_cover' => true]);

    $response = test()->get(route('listings.show', $listing));

    $response->assertOk();
    $response->assertDontSee('Listings with 3 or more photos get more views.');
});
