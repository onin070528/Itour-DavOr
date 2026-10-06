<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — establishment image approval queue.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Enums\UserRole;
use App\Models\EstablishmentImage;
use App\Models\Municipality;
use App\Models\User;
use App\Notifications\EstablishmentImageBatchDecided;
use App\Services\EstablishmentImageReviewer;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * A second Pending, Establishment-sourced image for the SAME listing/
 * uploader as $image — used to build a two-photo card.
 */
function secondEstablishmentImageFixture(EstablishmentImage $image): EstablishmentImage
{
    return EstablishmentImage::query()->create([
        'lst_id' => $image->lst_id,
        'img_path' => 'establishment-images/'.$image->lst_id.'/second.jpg',
        'img_thumbnail_path' => 'establishment-images/'.$image->lst_id.'/second_thumb.jpg',
        'img_alt_text' => $image->listing->lst_name,
        'img_source_role' => ImageSourceRole::Establishment,
        'img_status' => ImageStatus::Pending,
        'img_is_cover' => false,
        'img_sort_order' => 2,
        'img_hash' => hash('sha256', 'second-'.uniqid()),
        'img_uploaded_by' => $image->img_uploaded_by,
        'img_has_ownership_declared' => true,
    ]);
}

test('two photos from one establishment appear on one card', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $image = establishmentImageFixture(['listing_overrides' => ['lst_municipality' => 'City of Mati']]);
    $image->listing->update(['mun_id' => $mati->mun_id]);
    $second = secondEstablishmentImageFixture($image);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    $response = test()->actingAs($lgu)->get(route('lgu.images.index', ['tab' => 'approval']));

    $response->assertOk();
    $response->assertSee($image->listing->lst_name);
    // One card heading line, not one per photo.
    expect(substr_count($response->getContent(), 'Uploaded by'))->toBe(1);
    $response->assertSee('2 photos waiting');
});

test('Approve all publishes both photos and sends one notification', function () {
    Notification::fake();
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $image = establishmentImageFixture(['listing_overrides' => ['lst_municipality' => 'City of Mati']]);
    $image->listing->update(['mun_id' => $mati->mun_id]);
    $second = secondEstablishmentImageFixture($image);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    $response = test()->actingAs($lgu)->patch(route('lgu.images.approveBatch', $image->listing), [
        'image_ids' => [$image->img_id, $second->img_id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('toast', '2 photos approved.');
    expect($image->fresh()->img_status->value)->toBe('PUBLISHED');
    expect($second->fresh()->img_status->value)->toBe('PUBLISHED');
    Notification::assertSentTo($image->uploadedBy, EstablishmentImageBatchDecided::class, 1);
});

test('Return all rejects both photos with the same note', function () {
    Notification::fake();
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $image = establishmentImageFixture(['listing_overrides' => ['lst_municipality' => 'City of Mati']]);
    $image->listing->update(['mun_id' => $mati->mun_id]);
    $second = secondEstablishmentImageFixture($image);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    $response = test()->actingAs($lgu)->patch(route('lgu.images.returnBatch', $image->listing), [
        'image_ids' => [$image->img_id, $second->img_id],
        'reason' => 'Blurry photos.',
    ]);

    $response->assertRedirect();
    expect($image->fresh()->img_status->value)->toBe('REJECTED');
    expect($image->fresh()->img_review_note)->toBe('Blurry photos.');
    expect($second->fresh()->img_status->value)->toBe('REJECTED');
    expect($second->fresh()->img_review_note)->toBe('Blurry photos.');
    Notification::assertSentTo($image->uploadedBy, EstablishmentImageBatchDecided::class, 1);
});

test('Return one, then Approve all: one returned, the rest published', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $image = establishmentImageFixture(['listing_overrides' => ['lst_municipality' => 'City of Mati']]);
    $image->listing->update(['mun_id' => $mati->mun_id]);
    $second = secondEstablishmentImageFixture($image);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    test()->actingAs($lgu)->patch(route('lgu.images.returnBatch', $image->listing), [
        'image_ids' => [$image->img_id],
        'reason' => 'Out of focus.',
    ])->assertRedirect();

    // The real page would only list the still-pending id by now — matches
    // what the Approve all form actually submits after a reload.
    $response = test()->actingAs($lgu)->patch(route('lgu.images.approveBatch', $image->listing), [
        'image_ids' => [$second->img_id],
    ]);

    $response->assertSessionHas('toast', '1 photo approved.');
    expect($image->fresh()->img_status->value)->toBe('REJECTED');
    expect($second->fresh()->img_status->value)->toBe('PUBLISHED');
});

test('an LGU cannot act on its own uploads routed to PTO instead', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $listing = establishmentImageListingFixture(['lst_municipality' => 'City of Mati']);
    $listing->update(['mun_id' => $mati->mun_id]);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    $ownUpload = EstablishmentImage::query()->create([
        'lst_id' => $listing->lst_id,
        'img_path' => 'x.jpg', 'img_thumbnail_path' => 'x_thumb.jpg', 'img_alt_text' => $listing->lst_name,
        'img_source_role' => ImageSourceRole::Lgu, 'img_status' => ImageStatus::Pending,
        'img_is_cover' => false, 'img_sort_order' => 1, 'img_hash' => hash('sha256', uniqid()),
        'img_uploaded_by' => $lgu->usr_id, 'img_has_ownership_declared' => true,
    ]);

    // Not visible as a card on the LGU's own Photos page's approval tab —
    // the listing itself may still legitimately appear elsewhere on the
    // same page (e.g. the "All photos" upload picker), so this checks the
    // approval tab renders its empty state rather than checking the
    // listing's name is absent from the whole page.
    test()->actingAs($lgu)->get(route('lgu.images.index', ['tab' => 'approval']))
        ->assertSee('Nothing waiting for approval');

    // And a direct, tampered-in batch request is silently skipped, not acted on.
    $response = test()->actingAs($lgu)->patch(route('lgu.images.approveBatch', $listing), [
        'image_ids' => [$ownUpload->img_id],
    ]);

    $response->assertSessionHas('toast', 'No photos were approved. 1 photo was already decided by someone else and was skipped.');
    expect($ownUpload->fresh()->img_status->value)->toBe('PENDING');
});

test('an LGU cannot act on another municipality\'s queue', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $baganga = Municipality::query()->firstOrCreate(['mun_code' => 'BAG'], ['mun_name' => 'Baganga']);
    $image = establishmentImageFixture(['listing_overrides' => ['lst_municipality' => 'City of Mati']]);
    $image->listing->update(['mun_id' => $mati->mun_id]);
    $otherLgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $baganga->mun_id]);

    test()->actingAs($otherLgu)->patch(route('lgu.images.approveBatch', $image->listing), [
        'image_ids' => [$image->img_id],
    ])->assertForbidden();

    test()->actingAs($otherLgu)->patch(route('lgu.images.returnBatch', $image->listing), [
        'image_ids' => [$image->img_id],
        'reason' => 'Not yours.',
    ])->assertForbidden();

    expect($image->fresh()->img_status->value)->toBe('PENDING');
});

test('an establishment cannot approve anything', function () {
    $image = establishmentImageFixture();
    $uploader = $image->uploadedBy;

    test()->actingAs($uploader)->patch(route('lgu.images.approveBatch', $image->listing), [
        'image_ids' => [$image->img_id],
    ])->assertForbidden();

    test()->actingAs($uploader)->get(route('lgu.images.index'))->assertForbidden();

    expect($image->fresh()->img_status->value)->toBe('PENDING');
});

test('a partially failing batch changes nothing', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $image = establishmentImageFixture(['listing_overrides' => ['lst_municipality' => 'City of Mati']]);
    $image->listing->update(['mun_id' => $mati->mun_id]);
    $second = secondEstablishmentImageFixture($image);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    // Forces the second image's own update to blow up mid-batch — a
    // legitimate, scoped way to prove the whole transaction rolls back,
    // without mocking any of the service's own code.
    EstablishmentImage::saving(function (EstablishmentImage $objImage) use ($second) {
        if ($objImage->img_id === $second->img_id && $objImage->img_status->value === 'PUBLISHED') {
            throw new RuntimeException('Simulated failure.');
        }
    });

    $objReviewer = app(EstablishmentImageReviewer::class);

    try {
        expect(fn () => $objReviewer->approveBatch($lgu, $image->listing, [$image->img_id, $second->img_id]))
            ->toThrow(ValidationException::class);

        expect($image->fresh()->img_status->value)->toBe('PENDING');
        expect($second->fresh()->img_status->value)->toBe('PENDING');
    } finally {
        EstablishmentImage::flushEventListeners();
    }
});

test('a photo already decided elsewhere is skipped with a message', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $image = establishmentImageFixture(['listing_overrides' => ['lst_municipality' => 'City of Mati']]);
    $image->listing->update(['mun_id' => $mati->mun_id]);
    $second = secondEstablishmentImageFixture($image);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    // Someone else already approved the second image before this request
    // is processed.
    $second->update(['img_status' => ImageStatus::Published, 'img_reviewed_at' => now()]);

    $response = test()->actingAs($lgu)->patch(route('lgu.images.approveBatch', $image->listing), [
        'image_ids' => [$image->img_id, $second->img_id],
    ]);

    $response->assertSessionHas('toast', '1 photo approved. 1 photo was already decided by someone else and was skipped.');
    expect($image->fresh()->img_status->value)->toBe('PUBLISHED');
});

test('a replacement approved in a batch swaps atomically with exactly one cover', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $image = establishmentImageFixture(['listing_overrides' => ['lst_municipality' => 'City of Mati'], 'img_status' => ImageStatus::Published, 'img_is_cover' => true]);
    $image->listing->update(['mun_id' => $mati->mun_id]);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    $replacement = EstablishmentImage::query()->create([
        'lst_id' => $image->lst_id,
        'img_path' => 'new.jpg', 'img_thumbnail_path' => 'new_thumb.jpg', 'img_alt_text' => $image->listing->lst_name,
        'img_source_role' => ImageSourceRole::Establishment, 'img_status' => ImageStatus::Pending,
        'img_is_cover' => false, 'img_sort_order' => 99, 'img_hash' => hash('sha256', 'replacement'),
        'img_uploaded_by' => $image->img_uploaded_by, 'img_has_ownership_declared' => true,
        'img_replaces_id' => $image->img_id,
    ]);

    $response = test()->actingAs($lgu)->patch(route('lgu.images.approveBatch', $image->listing), [
        'image_ids' => [$replacement->img_id],
    ]);

    $response->assertRedirect();
    expect($replacement->fresh()->img_status->value)->toBe('PUBLISHED');
    expect($replacement->fresh()->img_is_cover)->toBeTrue();
    expect($image->fresh()->img_status->value)->toBe('ARCHIVED');
    expect($image->fresh()->img_is_cover)->toBeFalse();

    $intPublishedCovers = EstablishmentImage::query()
        ->where('lst_id', $image->lst_id)
        ->where('img_status', 'PUBLISHED')
        ->where('img_is_cover', true)
        ->count();
    expect($intPublishedCovers)->toBe(1);
});

test('the badge counts establishments, not images', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $image = establishmentImageFixture(['listing_overrides' => ['lst_municipality' => 'City of Mati']]);
    $image->listing->update(['mun_id' => $mati->mun_id]);
    secondEstablishmentImageFixture($image);

    $otherListingImage = establishmentImageFixture(['listing_overrides' => ['lst_municipality' => 'City of Mati', 'name' => 'Another Establishment']]);
    $otherListingImage->listing->update(['mun_id' => $mati->mun_id]);

    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    // 3 pending photos across 2 establishments — the badge shows 2. Uses
    // the Photos page itself (not the dashboard) so this test doesn't also
    // depend on LguMockData's own municipality-name wiring.
    $response = test()->actingAs($lgu)->get(route('lgu.images.index'));

    $response->assertOk();
    $response->assertSee('>2<', false);
});
