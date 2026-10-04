<?php

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Str;

function listingDetailFixture(array $overrides = []): Listing
{
    $category = Category::query()->firstOrCreate(
        ['cat_name' => 'Accommodation'],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );

    return Listing::query()->create(array_merge([
        'slug' => Str::slug('detail-page-fixture-'.Str::random(6)),
        'name' => 'Detail Page Resort',
        'category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'municipality' => 'City of Mati',
        'barangay' => 'Dahican',
        'status' => 'PUBLISHED',
        'image' => 'resort.jpg',
    ], $overrides));
}

test('the detail page shows the published cover image and a gallery of published photos only', function () {
    $listing = listingDetailFixture();
    $uploader = User::factory()->create(['role' => UserRole::Establishment, 'establishment_id' => $listing->id]);

    $cover = EstablishmentImage::query()->create([
        'listing_id' => $listing->id,
        'img_path' => 'cover.jpg', 'img_thumbnail_path' => 'cover_thumb.jpg', 'img_alt_text' => 'Pool view',
        'img_credit' => 'Photo by Jane', 'img_source_role' => ImageSourceRole::Establishment,
        'img_status' => ImageStatus::Published, 'img_is_cover' => true, 'img_sort_order' => 1,
        'img_hash' => hash('sha256', uniqid()), 'img_uploaded_by' => $uploader->id, 'img_has_ownership_declared' => true,
    ]);
    EstablishmentImage::query()->create([
        'listing_id' => $listing->id,
        'img_path' => 'pending.jpg', 'img_thumbnail_path' => 'pending_thumb.jpg', 'img_alt_text' => 'Lobby',
        'img_source_role' => ImageSourceRole::Establishment, 'img_status' => ImageStatus::Pending,
        'img_is_cover' => false, 'img_sort_order' => 2, 'img_hash' => hash('sha256', uniqid()),
        'img_uploaded_by' => $uploader->id, 'img_has_ownership_declared' => true,
    ]);

    $response = $this->get(route('listings.show', $listing));

    $response->assertOk();
    $response->assertSee($listing->name);
    $response->assertSee(route('establishmentImages.file', [$cover, 'full']), false);
    $response->assertSee('Photo by Jane');
    $response->assertDontSee('Lobby');
});

test('a listing with no establishment images falls back to its legacy image, never a dead link', function () {
    $listing = listingDetailFixture();

    $response = $this->get(route('listings.show', $listing));

    $response->assertOk();
    $response->assertSee(asset('storage/itour-images/resort.jpg'), false);
});

test('a listing with no image at all shows a category placeholder icon, not a third-party photo', function () {
    $listing = listingDetailFixture(['image' => null]);

    $response = $this->get(route('listings.show', $listing));

    $response->assertOk();
    $response->assertSee('ti-bed', false);
});

test('a suspended listing has no public detail page', function () {
    $listing = listingDetailFixture(['status' => 'Suspended']);

    $this->get(route('listings.show', $listing))->assertNotFound();
});

test('the Explore hub links each listing to its real detail page, not a dead link', function () {
    $listing = listingDetailFixture();

    $response = $this->get(route('explore'));

    $response->assertOk();
    $response->assertSee(route('listings.show', $listing), false);
    $response->assertDontSee('href="#"', false);
});
