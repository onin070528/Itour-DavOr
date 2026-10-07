<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — establishment image management.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Console\Commands\PurgeArchivedEstablishmentImages;
use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Enums\UserRole;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A published, cover image on a fresh fixture listing — used as the "old"
 * live photo in replace/remove/cover scenarios below.
 */
function publishedCoverImageFixture(Listing $listing, User $uploader): EstablishmentImage
{
    return EstablishmentImage::query()->create([
        'lst_id' => $listing->lst_id,
        'img_path' => 'establishment-images/'.$listing->lst_id.'/cover.jpg',
        'img_thumbnail_path' => 'establishment-images/'.$listing->lst_id.'/cover_thumb.jpg',
        'img_alt_text' => $listing->lst_name,
        'img_source_role' => ImageSourceRole::Establishment,
        'img_status' => ImageStatus::Published,
        'img_is_cover' => true,
        'img_sort_order' => 1,
        'img_hash' => hash('sha256', 'cover-'.uniqid()),
        'img_uploaded_by' => $uploader->usr_id,
        'img_has_ownership_declared' => true,
    ]);
}

test('a second replace request is blocked while one is already pending', function () {
    $listing = establishmentImageListingFixture();
    $uploader = establishmentImageUserFixture($listing);
    $cover = publishedCoverImageFixture($listing, $uploader);

    test()->actingAs($uploader)->post(route('establishment.images.replace', $cover), [
        'photo' => UploadedFile::fake()->image('first-replace.jpg', 1600, 1200),
    ])->assertSessionHasNoErrors();

    $response = test()->actingAs($uploader)->post(route('establishment.images.replace', $cover), [
        'photo' => UploadedFile::fake()->image('second-replace.jpg', 1601, 1200),
    ]);

    $response->assertSessionHasErrors();
    expect(EstablishmentImage::query()->where('img_replaces_id', $cover->img_id)->count())->toBe(1);
});

test('a rejected replacement leaves the old image live and unchanged', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $listing = establishmentImageListingFixture(['lst_municipality' => 'City of Mati']);
    $listing->update(['mun_id' => $mati->mun_id]);
    $uploader = establishmentImageUserFixture($listing);
    $cover = publishedCoverImageFixture($listing, $uploader);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    test()->actingAs($uploader)->post(route('establishment.images.replace', $cover), [
        'photo' => UploadedFile::fake()->image('replacement.jpg', 1600, 1200),
    ])->assertSessionHasNoErrors();

    $replacement = EstablishmentImage::query()->where('img_replaces_id', $cover->img_id)->first();

    test()->actingAs($lgu)->patch(route('lgu.images.return', $replacement), ['reason' => 'Not acceptable.'])
        ->assertRedirect();

    $cover->refresh();
    $replacement->refresh();
    expect($cover->img_status->value)->toBe('PUBLISHED');
    expect($cover->img_is_cover)->toBeTrue();
    expect($replacement->img_status->value)->toBe('REJECTED');
});

test('removing the last published image clears the cover and leaves none published', function () {
    $listing = establishmentImageListingFixture();
    $uploader = establishmentImageUserFixture($listing);
    $cover = publishedCoverImageFixture($listing, $uploader);

    test()->actingAs($uploader)->patch(route('establishment.images.remove', $cover))->assertRedirect();

    $cover->refresh();
    expect($cover->img_status->value)->toBe('ARCHIVED');
    expect($cover->img_is_cover)->toBeFalse();
    expect($listing->establishmentImages()->where('img_status', 'PUBLISHED')->count())->toBe(0);
});

test('removing the cover image promotes the next published image', function () {
    $listing = establishmentImageListingFixture();
    $uploader = establishmentImageUserFixture($listing);
    $cover = publishedCoverImageFixture($listing, $uploader);
    $second = EstablishmentImage::query()->create([
        'lst_id' => $listing->lst_id,
        'img_path' => 'b.jpg', 'img_thumbnail_path' => 'b_thumb.jpg', 'img_alt_text' => $listing->lst_name,
        'img_source_role' => ImageSourceRole::Establishment, 'img_status' => ImageStatus::Published,
        'img_is_cover' => false, 'img_sort_order' => 2, 'img_hash' => hash('sha256', uniqid()),
        'img_uploaded_by' => $uploader->usr_id, 'img_has_ownership_declared' => true,
    ]);

    test()->actingAs($uploader)->patch(route('establishment.images.remove', $cover))->assertRedirect();

    expect($second->fresh()->img_is_cover)->toBeTrue();
});

test('an establishment can set cover, edit credit, and reorder its own photos', function () {
    $listing = establishmentImageListingFixture();
    $uploader = establishmentImageUserFixture($listing);
    $cover = publishedCoverImageFixture($listing, $uploader);
    $second = EstablishmentImage::query()->create([
        'lst_id' => $listing->lst_id,
        'img_path' => 'b.jpg', 'img_thumbnail_path' => 'b_thumb.jpg', 'img_alt_text' => $listing->lst_name,
        'img_source_role' => ImageSourceRole::Establishment, 'img_status' => ImageStatus::Published,
        'img_is_cover' => false, 'img_sort_order' => 2, 'img_hash' => hash('sha256', uniqid()),
        'img_uploaded_by' => $uploader->usr_id, 'img_has_ownership_declared' => true,
    ]);

    test()->actingAs($uploader)->patch(route('establishment.images.cover', $second))->assertRedirect();
    expect($cover->fresh()->img_is_cover)->toBeFalse();
    expect($second->fresh()->img_is_cover)->toBeTrue();

    test()->actingAs($uploader)->patch(route('establishment.images.credit', $cover), ['credit' => 'Photo by Someone'])->assertRedirect();
    expect($cover->fresh()->img_credit)->toBe('Photo by Someone');

    test()->actingAs($uploader)->put(route('establishment.images.reorder', $listing), ['order' => [$second->img_id, $cover->img_id]])->assertRedirect();
    expect($second->fresh()->img_sort_order)->toBe(1);
    expect($cover->fresh()->img_sort_order)->toBe(2);
});

test('an establishment cannot manage, replace, or remove another establishment\'s photos', function () {
    $listing = establishmentImageListingFixture();
    $otherListing = establishmentImageListingFixture(['lst_name' => 'Someone Else Resort']);
    $uploader = establishmentImageUserFixture($listing);
    $otherUploader = establishmentImageUserFixture($otherListing);
    $image = publishedCoverImageFixture($otherListing, $otherUploader);

    test()->actingAs($uploader)->patch(route('establishment.images.remove', $image))->assertForbidden();
    test()->actingAs($uploader)->patch(route('establishment.images.cover', $image))->assertForbidden();
    test()->actingAs($uploader)->patch(route('establishment.images.credit', $image), ['credit' => 'x'])->assertForbidden();
    test()->actingAs($uploader)->post(route('establishment.images.replace', $image), [
        'photo' => UploadedFile::fake()->image('p.jpg', 1600, 1200),
    ])->assertForbidden();
});

test('an LGU from another municipality cannot manage an establishment\'s photos', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $baganga = Municipality::query()->firstOrCreate(['mun_code' => 'BAG'], ['mun_name' => 'Baganga']);
    $listing = establishmentImageListingFixture(['lst_municipality' => 'City of Mati']);
    $listing->update(['mun_id' => $mati->mun_id]);
    $uploader = establishmentImageUserFixture($listing);
    $image = publishedCoverImageFixture($listing, $uploader);
    $otherLgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $baganga->mun_id]);

    test()->actingAs($otherLgu)->patch(route('lgu.images.remove', $image))->assertForbidden();
    test()->actingAs($otherLgu)->patch(route('lgu.images.cover', $image))->assertForbidden();
    test()->actingAs($otherLgu)->get(route('lgu.images.manage', $listing))->assertForbidden();
});

test('an LGU in the same municipality can manage an establishment\'s photos', function () {
    $mati = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);
    $listing = establishmentImageListingFixture(['lst_municipality' => 'City of Mati']);
    $listing->update(['mun_id' => $mati->mun_id]);
    $uploader = establishmentImageUserFixture($listing);
    $image = publishedCoverImageFixture($listing, $uploader);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $mati->mun_id]);

    test()->actingAs($lgu)->get(route('lgu.images.manage', $listing))->assertOk();
    test()->actingAs($lgu)->patch(route('lgu.images.remove', $image))->assertRedirect();
    expect($image->fresh()->img_status->value)->toBe('ARCHIVED');
});

test('a PTO administrator can manage any establishment\'s photos', function () {
    $listing = establishmentImageListingFixture();
    $uploader = establishmentImageUserFixture($listing);
    $image = publishedCoverImageFixture($listing, $uploader);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    test()->actingAs($pto)->get(route('pto.images.manage', $listing))->assertOk();
    test()->actingAs($pto)->patch(route('pto.images.cover', $image))->assertRedirect();
});

test('suspending an establishment hides its published photo from the public file route, and reactivating restores it without re-approval', function () {
    $listing = establishmentImageListingFixture(['lst_status' => 'PUBLISHED']);
    $uploader = establishmentImageUserFixture($listing);
    $image = publishedCoverImageFixture($listing, $uploader);
    Storage::disk('local')->put($image->img_path, 'fake-bytes');

    test()->get(route('establishmentImages.file', [$image, 'full']))->assertOk();

    $listing->update(['lst_status' => 'Suspended']);
    test()->get(route('establishmentImages.file', [$image, 'full']))->assertNotFound();
    expect($image->fresh()->img_status->value)->toBe('PUBLISHED');

    $listing->update(['lst_status' => 'PUBLISHED']);
    test()->get(route('establishmentImages.file', [$image, 'full']))->assertOk();
    expect($image->fresh()->img_status->value)->toBe('PUBLISHED');
    expect($image->fresh()->img_reviewed_at)->toBeNull();
});

test('the purge job clears file paths only for images archived longer than the retention period', function () {
    $listing = establishmentImageListingFixture();
    $uploader = establishmentImageUserFixture($listing);
    $retentionMonths = (int) config('establishment_images.archive_retention_months');

    $overdue = EstablishmentImage::query()->create([
        'lst_id' => $listing->lst_id,
        'img_path' => 'overdue.jpg', 'img_thumbnail_path' => 'overdue_thumb.jpg', 'img_alt_text' => $listing->lst_name,
        'img_source_role' => ImageSourceRole::Establishment, 'img_status' => ImageStatus::Archived,
        'img_is_cover' => false, 'img_sort_order' => 1, 'img_hash' => hash('sha256', uniqid()),
        'img_uploaded_by' => $uploader->usr_id, 'img_has_ownership_declared' => true,
        'img_archived_at' => now()->subMonths($retentionMonths + 1),
    ]);
    $recent = EstablishmentImage::query()->create([
        'lst_id' => $listing->lst_id,
        'img_path' => 'recent.jpg', 'img_thumbnail_path' => 'recent_thumb.jpg', 'img_alt_text' => $listing->lst_name,
        'img_source_role' => ImageSourceRole::Establishment, 'img_status' => ImageStatus::Archived,
        'img_is_cover' => false, 'img_sort_order' => 2, 'img_hash' => hash('sha256', uniqid()),
        'img_uploaded_by' => $uploader->usr_id, 'img_has_ownership_declared' => true,
        'img_archived_at' => now()->subMonths($retentionMonths - 1),
    ]);
    Storage::disk('local')->put($overdue->img_path, 'x');
    Storage::disk('local')->put($recent->img_path, 'x');

    test()->artisan(PurgeArchivedEstablishmentImages::class)->assertExitCode(0);

    expect($overdue->fresh()->img_path)->toBeNull();
    expect($overdue->fresh()->img_thumbnail_path)->toBeNull();
    expect($recent->fresh()->img_path)->toBe('recent.jpg');
    expect(Storage::disk('local')->exists('recent.jpg'))->toBeTrue();
});
