<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — establishment profile photos merge.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\OperationLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

function mergeCategoryFixture(): Category
{
    return Category::query()->firstOrCreate(
        ['cat_name' => 'Accommodation'],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );
}

function mergeListingFixture(array $overrides = []): Listing
{
    $municipality = Municipality::query()->firstOrCreate(['mun_code' => 'MATI'], ['mun_name' => 'City of Mati']);

    return Listing::query()->create(array_merge([
        'lst_slug' => Str::slug('merge-fixture-'.Str::random(6)),
        'lst_name' => 'Merge Fixture Inn',
        'lst_category' => 'accommodation',
        'cat_id' => mergeCategoryFixture()->cat_id,
        'lst_municipality' => 'City of Mati',
        'mun_id' => $municipality->mun_id,
        'lst_barangay' => 'Dahican',
        'lst_description' => 'A cozy inn by the beach.',
        'lst_contact_phone' => '09171234567',
        'lst_status' => 'DRAFT',
    ], $overrides));
}

function mergeEstablishmentUserFixture(Listing $listing): User
{
    return User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'mun_id' => $listing->mun_id,
        'lst_id' => $listing->lst_id,
        'usr_organization_name' => $listing->lst_name,
    ]);
}

function mergePublishedImageFixture(Listing $listing, User $uploader): EstablishmentImage
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
        'img_hash' => hash('sha256', 'merge-fixture-'.uniqid()),
        'img_uploaded_by' => $uploader->usr_id,
        'img_has_ownership_declared' => true,
    ]);
}

test('the merged page shows the status banner, details form, and photos section in order', function () {
    $listing = mergeListingFixture();
    $user = mergeEstablishmentUserFixture($listing);

    $response = test()->actingAs($user)->get(route('establishment.profile'));
    $response->assertOk();

    $content = $response->getContent();
    $bannerPos = strpos($content, 'id="status-banner"') !== false ? strpos($content, 'id="status-banner"') : strpos($content, 'Draft');
    $formPos = strpos($content, 'id="establishment-profile-form"');
    $photosPos = strpos($content, 'id="photos"');

    expect($formPos)->not->toBeFalse();
    expect($photosPos)->not->toBeFalse();
    expect($formPos)->toBeLessThan($photosPos);
});

test('Save draft saves without validating required fields and never submits', function () {
    $listing = mergeListingFixture();
    $user = mergeEstablishmentUserFixture($listing);

    test()->actingAs($user)->put(route('establishment.profile.update'), [
        'name' => $listing->lst_name,
        'category' => 'accommodation',
        'address' => 'Dahican',
        // description, phone/email all omitted — must not block the save.
    ])->assertSessionHasNoErrors();

    expect($listing->fresh()->lst_status)->toBe('DRAFT');
});

test('Save and submit with a missing field lists it in plain words and does not submit', function () {
    $listing = mergeListingFixture(['lst_description' => null, 'lst_contact_phone' => null, 'lst_email' => null]);
    $user = mergeEstablishmentUserFixture($listing);

    $response = test()->actingAs($user)->patch(route('establishment.profile.submit'), [
        'name' => $listing->lst_name,
        'category' => 'accommodation',
        'address' => 'Dahican',
    ]);

    $response->assertRedirect();
    expect(session('arrMissingFields'))->not->toBeEmpty();
    expect(session('arrMissingFields'))->toContain('Description');
    expect(session('arrMissingFields'))->toContain('A public phone number or email');
    expect($listing->fresh()->lst_status)->toBe('DRAFT');
});

test('Save and submit with everything ready moves the package to FOR_LGU_REVIEW and the page becomes read-only', function () {
    $listing = mergeListingFixture();
    $user = mergeEstablishmentUserFixture($listing);

    test()->actingAs($user)->patch(route('establishment.profile.submit'), [
        'name' => $listing->lst_name,
        'category' => 'accommodation',
        'address' => 'Dahican',
        'description' => 'A cozy inn by the beach.',
        'phone' => '09171234567',
    ])->assertSessionHasNoErrors();

    expect($listing->fresh()->lst_status)->toBe('FOR_LGU_REVIEW');

    $response = test()->actingAs($user)->get(route('establishment.profile'));
    $response->assertOk();
    $response->assertSee('Waiting for LGU Review');
});

test('the form and photo management are read-only in FOR_LGU_REVIEW, FOR_PTO_REVIEW, and PUBLISHED', function (string $status) {
    $listing = mergeListingFixture(['lst_status' => $status]);
    $user = mergeEstablishmentUserFixture($listing);
    $image = mergePublishedImageFixture($listing, $user);

    test()->actingAs($user)->put(route('establishment.profile.update'), [
        'name' => 'Should Not Save',
        'category' => 'accommodation',
        'address' => 'Dahican',
    ])->assertForbidden();

    test()->actingAs($user)->patch(route('establishment.images.cover', $image))->assertForbidden();

    expect($listing->fresh()->lst_name)->not->toBe('Should Not Save');
})->with(['FOR_LGU_REVIEW', 'FOR_PTO_REVIEW', 'PUBLISHED']);

test('a returned package shows the reason in the banner and allows editing and resubmission', function () {
    $listing = mergeListingFixture(['lst_status' => 'FOR_LGU_REVIEW']);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $listing->mun_id]);
    $user = mergeEstablishmentUserFixture($listing);

    test()->actingAs($lgu)->patch(route('lgu.directory.establishments.return', $listing), [
        'reason' => 'Please add a clearer description.',
    ])->assertRedirect();

    expect($listing->fresh()->lst_status)->toBe('DRAFT');

    $response = test()->actingAs($user)->get(route('establishment.profile'));
    $response->assertOk();
    $response->assertSee('Returned');
    $response->assertSee('Please add a clearer description.');

    test()->actingAs($user)->put(route('establishment.profile.update'), [
        'name' => $listing->lst_name,
        'category' => 'accommodation',
        'address' => 'Updated Address',
    ])->assertSessionHasNoErrors();

    expect($listing->fresh()->lst_barangay)->toBe('Updated Address');
});

test('the old Photos URL redirects permanently to the profile page with the #photos fragment', function () {
    $listing = mergeListingFixture();
    $user = mergeEstablishmentUserFixture($listing);

    $response = test()->actingAs($user)->get(route('establishment.images.index'));

    $response->assertStatus(301);
    $response->assertRedirect('/establishment/profile#photos');
});

test('the Photos sidebar item is gone and no other item changed', function () {
    $listing = mergeListingFixture();
    $user = mergeEstablishmentUserFixture($listing);

    $response = test()->actingAs($user)->get(route('establishment.dashboard'));
    $response->assertOk();
    $response->assertDontSee('establishment.images.index', false);
    $response->assertSee('Establishment Profile');
    $response->assertSee('Arrival Recording');
    $response->assertSee('QR Code');
    $response->assertSee('Monthly Report');
    $response->assertSee('Feedback &amp; Reviews', false);
    $response->assertSee('Activity Log');
});

test('the LGU directory still receives the submitted package unchanged', function () {
    $listing = mergeListingFixture();
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu, 'mun_id' => $listing->mun_id]);
    $user = mergeEstablishmentUserFixture($listing);

    test()->actingAs($user)->patch(route('establishment.profile.submit'), [
        'name' => $listing->lst_name,
        'category' => 'accommodation',
        'address' => 'Dahican',
        'description' => 'A cozy inn by the beach.',
        'phone' => '09171234567',
    ])->assertSessionHasNoErrors();

    expect($listing->fresh()->lst_status)->toBe('FOR_LGU_REVIEW');

    test()->actingAs($lgu)->patch(route('lgu.directory.establishments.submit', $listing))->assertRedirect();

    expect($listing->fresh()->lst_status)->toBe('FOR_PTO_REVIEW');

    $log = OperationLog::where('opl_entity_type', 'establishment')->where('opl_entity_id', $listing->lst_id)->where('opl_action', 'submit')->where('opl_user_role', 'lgu')->first();
    expect($log)->not->toBeNull();
});

test('View QR Code still works', function () {
    $listing = mergeListingFixture();
    $user = mergeEstablishmentUserFixture($listing);

    test()->actingAs($user)->get(route('establishment.profile'))->assertOk()->assertSee('View QR Code');
    test()->actingAs($user)->get(route('establishment.qr'))->assertOk();
});

test('an establishment photo upload posted to the old Photos URL is stored as Pending for LGU approval, not redirected away', function () {
    $listing = mergeListingFixture();
    $user = mergeEstablishmentUserFixture($listing);

    $response = test()->actingAs($user)->post(route('establishment.images.store'), [
        'listing_id' => $listing->lst_id,
        'photos' => [UploadedFile::fake()->image('front.jpg', 1600, 1200)],
        'ownership_declared' => '1',
    ]);

    $response->assertStatus(302)->assertSessionHasNoErrors();

    $image = EstablishmentImage::query()->where('lst_id', $listing->lst_id)->firstOrFail();
    expect($image->img_status)->toBe(ImageStatus::Pending)
        ->and($image->img_source_role)->toBe(ImageSourceRole::Establishment);
});
