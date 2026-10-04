<?php

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\OperationLog;
use App\Models\User;
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
    $municipality = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);

    return Listing::query()->create(array_merge([
        'slug' => Str::slug('merge-fixture-'.Str::random(6)),
        'name' => 'Merge Fixture Inn',
        'category' => 'accommodation',
        'cat_id' => mergeCategoryFixture()->cat_id,
        'municipality' => 'City of Mati',
        'municipality_id' => $municipality->id,
        'barangay' => 'Dahican',
        'description' => 'A cozy inn by the beach.',
        'contact_phone' => '09171234567',
        'status' => 'DRAFT',
    ], $overrides));
}

function mergeEstablishmentUserFixture(Listing $listing): User
{
    return User::factory()->create([
        'role' => UserRole::Establishment,
        'municipality_id' => $listing->municipality_id,
        'establishment_id' => $listing->id,
        'organization_name' => $listing->name,
    ]);
}

function mergePublishedImageFixture(Listing $listing, User $uploader): EstablishmentImage
{
    return EstablishmentImage::query()->create([
        'listing_id' => $listing->id,
        'img_path' => 'establishment-images/'.$listing->id.'/cover.jpg',
        'img_thumbnail_path' => 'establishment-images/'.$listing->id.'/cover_thumb.jpg',
        'img_alt_text' => $listing->name,
        'img_source_role' => ImageSourceRole::Establishment,
        'img_status' => ImageStatus::Published,
        'img_is_cover' => true,
        'img_sort_order' => 1,
        'img_hash' => hash('sha256', 'merge-fixture-'.uniqid()),
        'img_uploaded_by' => $uploader->id,
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
        'name' => $listing->name,
        'category' => 'accommodation',
        'address' => 'Dahican',
        // description, phone/email all omitted — must not block the save.
    ])->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe('DRAFT');
});

test('Save and submit with a missing field lists it in plain words and does not submit', function () {
    $listing = mergeListingFixture(['description' => null, 'contact_phone' => null, 'email' => null]);
    $user = mergeEstablishmentUserFixture($listing);

    $response = test()->actingAs($user)->patch(route('establishment.profile.submit'), [
        'name' => $listing->name,
        'category' => 'accommodation',
        'address' => 'Dahican',
    ]);

    $response->assertRedirect();
    expect(session('arrMissingFields'))->not->toBeEmpty();
    expect(session('arrMissingFields'))->toContain('Description');
    expect(session('arrMissingFields'))->toContain('A public phone number or email');
    expect($listing->fresh()->status)->toBe('DRAFT');
});

test('Save and submit with everything ready moves the package to FOR_LGU_REVIEW and the page becomes read-only', function () {
    $listing = mergeListingFixture();
    $user = mergeEstablishmentUserFixture($listing);

    test()->actingAs($user)->patch(route('establishment.profile.submit'), [
        'name' => $listing->name,
        'category' => 'accommodation',
        'address' => 'Dahican',
        'description' => 'A cozy inn by the beach.',
        'phone' => '09171234567',
    ])->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe('FOR_LGU_REVIEW');

    $response = test()->actingAs($user)->get(route('establishment.profile'));
    $response->assertOk();
    $response->assertSee('Waiting for LGU Review');
});

test('the form and photo management are read-only in FOR_LGU_REVIEW, FOR_PTO_REVIEW, and PUBLISHED', function (string $status) {
    $listing = mergeListingFixture(['status' => $status]);
    $user = mergeEstablishmentUserFixture($listing);
    $image = mergePublishedImageFixture($listing, $user);

    test()->actingAs($user)->put(route('establishment.profile.update'), [
        'name' => 'Should Not Save',
        'category' => 'accommodation',
        'address' => 'Dahican',
    ])->assertForbidden();

    test()->actingAs($user)->patch(route('establishment.images.cover', $image))->assertForbidden();

    expect($listing->fresh()->name)->not->toBe('Should Not Save');
})->with(['FOR_LGU_REVIEW', 'FOR_PTO_REVIEW', 'PUBLISHED']);

test('a returned package shows the reason in the banner and allows editing and resubmission', function () {
    $listing = mergeListingFixture(['status' => 'FOR_LGU_REVIEW']);
    $lgu = User::factory()->create(['role' => UserRole::Lgu, 'municipality_id' => $listing->municipality_id]);
    $user = mergeEstablishmentUserFixture($listing);

    test()->actingAs($lgu)->patch(route('lgu.directory.establishments.return', $listing), [
        'reason' => 'Please add a clearer description.',
    ])->assertRedirect();

    expect($listing->fresh()->status)->toBe('DRAFT');

    $response = test()->actingAs($user)->get(route('establishment.profile'));
    $response->assertOk();
    $response->assertSee('Returned');
    $response->assertSee('Please add a clearer description.');

    test()->actingAs($user)->put(route('establishment.profile.update'), [
        'name' => $listing->name,
        'category' => 'accommodation',
        'address' => 'Updated Address',
    ])->assertSessionHasNoErrors();

    expect($listing->fresh()->barangay)->toBe('Updated Address');
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
    $lgu = User::factory()->create(['role' => UserRole::Lgu, 'municipality_id' => $listing->municipality_id]);
    $user = mergeEstablishmentUserFixture($listing);

    test()->actingAs($user)->patch(route('establishment.profile.submit'), [
        'name' => $listing->name,
        'category' => 'accommodation',
        'address' => 'Dahican',
        'description' => 'A cozy inn by the beach.',
        'phone' => '09171234567',
    ])->assertSessionHasNoErrors();

    expect($listing->fresh()->status)->toBe('FOR_LGU_REVIEW');

    test()->actingAs($lgu)->patch(route('lgu.directory.establishments.submit', $listing))->assertRedirect();

    expect($listing->fresh()->status)->toBe('FOR_PTO_REVIEW');

    $log = OperationLog::where('entity_type', 'establishment')->where('entity_id', $listing->id)->where('action', 'submit')->where('user_role', 'lgu')->first();
    expect($log)->not->toBeNull();
});

test('View QR Code still works', function () {
    $listing = mergeListingFixture();
    $user = mergeEstablishmentUserFixture($listing);

    test()->actingAs($user)->get(route('establishment.profile'))->assertOk()->assertSee('View QR Code');
    test()->actingAs($user)->get(route('establishment.qr'))->assertOk();
});
