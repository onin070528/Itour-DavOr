<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — establishment image approval.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Enums\UserRole;
use App\Models\EstablishmentImage;
use App\Models\Municipality;
use App\Models\User;

function establishmentImageFixture(array $overrides = []): EstablishmentImage
{
    $listing = establishmentImageListingFixture($overrides['listing_overrides'] ?? []);
    unset($overrides['listing_overrides']);
    $uploader = establishmentImageUserFixture($listing);

    return EstablishmentImage::query()->create(array_merge([
        'lst_id' => $listing->lst_id,
        'img_path' => 'establishment-images/'.$listing->lst_id.'/fixture.jpg',
        'img_thumbnail_path' => 'establishment-images/'.$listing->lst_id.'/fixture_thumb.jpg',
        'img_alt_text' => $listing->lst_name,
        'img_source_role' => ImageSourceRole::Establishment,
        'img_status' => ImageStatus::Pending,
        'img_is_cover' => false,
        'img_sort_order' => 1,
        'img_hash' => hash('sha256', 'fixture-'.uniqid()),
        'img_uploaded_by' => $uploader->usr_id,
        'img_has_ownership_declared' => true,
    ], $overrides));
}

test('an LGU can approve an establishment-sourced photo in its own municipality', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $image = establishmentImageFixture(['listing_overrides' => ['lst_municipality' => 'City of Mati']]);
    $image->listing->update(['mun_id' => $mati->mun_id]);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    $response = test()->actingAs($lgu)->patch(route('lgu.images.approve', $image));

    $response->assertRedirect();
    $image->refresh();
    expect($image->img_status->value)->toBe('PUBLISHED');
    expect($image->img_reviewed_by)->toBe($lgu->usr_id);
    expect($image->img_reviewed_at)->not->toBeNull();
});

test('an LGU cannot approve its own upload — it always routes to PTO instead', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $listing = establishmentImageListingFixture(['lst_municipality' => 'City of Mati']);
    $listing->update(['mun_id' => $mati->mun_id]);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    $image = EstablishmentImage::query()->create([
        'lst_id' => $listing->lst_id,
        'img_path' => 'x.jpg', 'img_thumbnail_path' => 'x_thumb.jpg', 'img_alt_text' => $listing->lst_name,
        'img_source_role' => ImageSourceRole::Lgu, 'img_status' => ImageStatus::Pending,
        'img_is_cover' => false, 'img_sort_order' => 1, 'img_hash' => hash('sha256', uniqid()),
        'img_uploaded_by' => $lgu->usr_id, 'img_has_ownership_declared' => true,
    ]);

    // Not even visible as a card in the LGU's own Photos page's approval
    // tab — the listing may still legitimately appear elsewhere on the
    // same page (the "All photos" upload picker), so this checks the
    // approval tab's empty state renders instead.
    test()->actingAs($lgu)->get(route('lgu.images.index', ['tab' => 'approval']))
        ->assertSee('Nothing waiting for approval');

    // And the approve action itself is forbidden, even called directly.
    test()->actingAs($lgu)->patch(route('lgu.images.approve', $image))->assertForbidden();

    expect($image->fresh()->img_status->value)->toBe('PENDING');
});

test('an LGU cannot approve or see a photo from another municipality', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $baganga = Municipality::query()->firstOrCreate(['mun_code' => 'BAG'], ['mun_name' => 'Baganga']);
    $image = establishmentImageFixture(['listing_overrides' => ['lst_municipality' => 'City of Mati']]);
    $image->listing->update(['mun_id' => $mati->mun_id]);
    $otherLgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $baganga->mun_id]);

    test()->actingAs($otherLgu)->get(route('lgu.images.index', ['tab' => 'approval']))->assertDontSee($image->listing->lst_name);
    test()->actingAs($otherLgu)->patch(route('lgu.images.approve', $image))->assertForbidden();
});

test('an establishment cannot publish anything by itself', function () {
    $image = establishmentImageFixture();
    $uploader = $image->uploadedBy;

    // Wrong role entirely for the LGU-only approval routes — 403, not a
    // login redirect, since the establishment account IS authenticated.
    test()->actingAs($uploader)->patch(route('lgu.images.approve', $image))->assertForbidden();
    test()->actingAs($uploader)->get(route('lgu.images.index'))->assertForbidden();

    expect($image->fresh()->img_status->value)->toBe('PENDING');
});

test('returning a photo requires a reason and notifies the uploader', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $image = establishmentImageFixture(['listing_overrides' => ['lst_municipality' => 'City of Mati']]);
    $image->listing->update(['mun_id' => $mati->mun_id]);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    test()->actingAs($lgu)->patch(route('lgu.images.return', $image), [])->assertSessionHasErrors('reason');

    test()->actingAs($lgu)->patch(route('lgu.images.return', $image), ['reason' => 'Photo is blurry.'])->assertRedirect();

    $image->refresh();
    expect($image->img_status->value)->toBe('REJECTED');
    expect($image->img_review_note)->toBe('Photo is blurry.');
    expect($image->uploadedBy->unreadNotifications()->count())->toBe(1);
});

test('a PTO administrator can approve an LGU-sourced photo from any municipality', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $listing = establishmentImageListingFixture(['lst_municipality' => 'City of Mati']);
    $listing->update(['mun_id' => $mati->mun_id]);
    $lguUploader = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    $image = EstablishmentImage::query()->create([
        'lst_id' => $listing->lst_id,
        'img_path' => 'x.jpg', 'img_thumbnail_path' => 'x_thumb.jpg', 'img_alt_text' => $listing->lst_name,
        'img_source_role' => ImageSourceRole::Lgu, 'img_status' => ImageStatus::Pending,
        'img_is_cover' => false, 'img_sort_order' => 1, 'img_hash' => hash('sha256', uniqid()),
        'img_uploaded_by' => $lguUploader->usr_id, 'img_has_ownership_declared' => true,
    ]);

    test()->actingAs($pto)->get(route('pto.images.index', ['tab' => 'approval']))->assertSee($listing->lst_name);
    test()->actingAs($pto)->patch(route('pto.images.approve', $image))->assertRedirect();

    expect($image->fresh()->img_status->value)->toBe('PUBLISHED');
});

test('approving a Replace request publishes the new image and archives the old one in one transaction', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $listing = establishmentImageListingFixture(['lst_municipality' => 'City of Mati']);
    $listing->update(['mun_id' => $mati->mun_id]);
    $uploader = establishmentImageUserFixture($listing);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    $oldImage = EstablishmentImage::query()->create([
        'lst_id' => $listing->lst_id,
        'img_path' => 'old.jpg', 'img_thumbnail_path' => 'old_thumb.jpg', 'img_alt_text' => $listing->lst_name,
        'img_source_role' => ImageSourceRole::Establishment, 'img_status' => ImageStatus::Published,
        'img_is_cover' => true, 'img_sort_order' => 1, 'img_hash' => hash('sha256', 'old'),
        'img_uploaded_by' => $uploader->usr_id, 'img_has_ownership_declared' => true,
    ]);
    $replacement = EstablishmentImage::query()->create([
        'lst_id' => $listing->lst_id,
        'img_path' => 'new.jpg', 'img_thumbnail_path' => 'new_thumb.jpg', 'img_alt_text' => $listing->lst_name,
        'img_source_role' => ImageSourceRole::Establishment, 'img_status' => ImageStatus::Pending,
        'img_is_cover' => false, 'img_sort_order' => 99, 'img_hash' => hash('sha256', 'new'),
        'img_uploaded_by' => $uploader->usr_id, 'img_has_ownership_declared' => true,
        'img_replaces_id' => $oldImage->img_id,
    ]);

    test()->actingAs($lgu)->patch(route('lgu.images.approve', $replacement))->assertRedirect();

    $replacement->refresh();
    $oldImage->refresh();
    expect($replacement->img_status->value)->toBe('PUBLISHED');
    expect($replacement->img_is_cover)->toBeTrue();
    expect($replacement->img_sort_order)->toBe(1);
    expect($oldImage->img_status->value)->toBe('ARCHIVED');
    expect($oldImage->img_is_cover)->toBeFalse();
    expect($oldImage->img_archived_at)->not->toBeNull();
});
