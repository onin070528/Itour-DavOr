<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use App\Notifications\EstablishmentListingPublished;
use App\Notifications\EstablishmentListingReturned;
use App\Notifications\EstablishmentListingReturnedToLgu;
use App\Services\ListingPublishWorkflow;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function publishWorkflowListingFixture(array $overrides = []): Listing
{
    $category = Category::query()->firstOrCreate(
        ['cat_name' => 'Accommodation'],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );

    return Listing::query()->create(array_merge([
        'slug' => Str::slug('publish-workflow-fixture-'.Str::random(6)),
        'name' => 'Workflow Test Inn',
        'category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'municipality' => 'City of Mati',
        'barangay' => 'Dahican',
        'status' => 'DRAFT',
    ], $overrides));
}

function publishWorkflowLguFixture(Municipality $objMunicipality): User
{
    return User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => $objMunicipality->name,
        'municipality_id' => $objMunicipality->id,
    ]);
}

test('LGU registering an establishment creates it as DRAFT, not publicly visible', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $lgu = publishWorkflowLguFixture($mati);
    $category = Category::query()->firstOrCreate(
        ['cat_name' => 'Accommodation'],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );

    test()->actingAs($lgu)->post(route('lgu.directory.establishments.store'), [
        'name' => 'New Resort',
        'cat_id' => $category->cat_id,
        'type' => 'Resort',
        'barangay' => 'Dahican',
        'owner_name' => 'Juan Dela Cruz',
        'contact_phone' => '09171234567',
    ])->assertSessionHasNoErrors();

    $listing = Listing::query()->where('name', 'New Resort')->sole();
    expect($listing->status)->toBe('DRAFT');
    expect($listing->isPubliclyVisible())->toBeFalse();
    // Registering the establishment never creates its account.
    expect($listing->establishmentUser)->toBeNull();

    test()->get(route('explore'))->assertDontSee('New Resort');
});

test('LGU submits a DRAFT listing to PTO, moving it to FOR_PTO_REVIEW', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $listing = publishWorkflowListingFixture(['municipality' => 'City of Mati']);
    $listing->update(['municipality_id' => $mati->id]);
    $lgu = publishWorkflowLguFixture($mati);

    test()->actingAs($lgu)->patch(route('lgu.directory.establishments.submit', $listing))->assertRedirect();

    expect($listing->fresh()->status)->toBe('FOR_PTO_REVIEW');
});

test('PTO Publish makes the listing and becomes its cover in one step, visible publicly', function () {
    Notification::fake();
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $listing = publishWorkflowListingFixture(['municipality' => 'City of Mati', 'status' => 'FOR_PTO_REVIEW', 'image' => null]);
    $listing->update(['municipality_id' => $mati->id]);
    $establishmentUser = User::factory()->create(['role' => UserRole::Establishment, 'municipality_id' => $mati->id, 'establishment_id' => $listing->id]);
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    test()->actingAs($pto)->patch(route('pto.directory.publish', $listing))->assertRedirect();

    expect($listing->fresh()->status)->toBe('PUBLISHED');
    test()->get(route('explore'))->assertSee($listing->name);
    Notification::assertSentTo($establishmentUser, EstablishmentListingPublished::class);
});

test('LGU cannot publish directly — only the PTO can (P1)', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $listing = publishWorkflowListingFixture(['municipality' => 'City of Mati', 'status' => 'FOR_PTO_REVIEW']);
    $listing->update(['municipality_id' => $mati->id]);
    $lgu = publishWorkflowLguFixture($mati);

    $objWorkflow = app(ListingPublishWorkflow::class);
    expect($lgu->can('publish', $listing))->toBeFalse();

    // Even calling the service directly doesn't bypass the controller-level
    // gate in practice, but the policy itself is the single source of
    // truth P1 relies on — this proves it refuses an LGU regardless.
    expect($listing->fresh()->status)->toBe('FOR_PTO_REVIEW');
});

test('LGU Return to establishment sends the whole package back with one note', function () {
    Notification::fake();
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $listing = publishWorkflowListingFixture(['municipality' => 'City of Mati', 'status' => 'FOR_PTO_REVIEW']);
    $listing->update(['municipality_id' => $mati->id]);
    $establishmentUser = User::factory()->create(['role' => UserRole::Establishment, 'municipality_id' => $mati->id, 'establishment_id' => $listing->id]);
    $lgu = publishWorkflowLguFixture($mati);

    test()->actingAs($lgu)->patch(route('lgu.directory.establishments.return', $listing), [
        'reason' => 'Please add contact details.',
    ])->assertRedirect();

    expect($listing->fresh()->status)->toBe('DRAFT');
    Notification::assertSentTo(
        $establishmentUser,
        EstablishmentListingReturned::class,
        fn ($notification, $channels) => $notification->toDatabase($establishmentUser)['reason'] === 'Please add contact details.',
    );
});

test('PTO Return to LGU sends it back with one note and notifies the LGU', function () {
    Notification::fake();
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $listing = publishWorkflowListingFixture(['municipality' => 'City of Mati', 'status' => 'FOR_PTO_REVIEW']);
    $listing->update(['municipality_id' => $mati->id]);
    $lgu = publishWorkflowLguFixture($mati);
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    test()->actingAs($pto)->patch(route('pto.directory.returnToLgu', $listing), [
        'reason' => 'Missing barangay clearance.',
    ])->assertRedirect();

    // Returned for Correction, with the remarks kept for the LGU.
    expect($listing->fresh()->status)->toBe('FOR_CORRECTION');
    expect($listing->fresh()->lst_review_remarks)->toBe('Missing barangay clearance.');
    Notification::assertSentTo($lgu, EstablishmentListingReturnedToLgu::class);
});

test('an LGU cannot act on a listing from another municipality', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $baganga = Municipality::query()->firstOrCreate(['code' => 'BAG'], ['name' => 'Baganga']);
    $listing = publishWorkflowListingFixture(['municipality' => 'Baganga', 'status' => 'DRAFT']);
    $listing->update(['municipality_id' => $baganga->id]);
    $otherLgu = publishWorkflowLguFixture($mati);

    test()->actingAs($otherLgu)->patch(route('lgu.directory.establishments.submit', $listing))->assertForbidden();

    expect($listing->fresh()->status)->toBe('DRAFT');
});

test('an establishment cannot publish or submit anything', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $listing = publishWorkflowListingFixture(['municipality' => 'City of Mati', 'status' => 'DRAFT']);
    $listing->update(['municipality_id' => $mati->id]);
    $establishmentUser = User::factory()->create(['role' => UserRole::Establishment, 'municipality_id' => $mati->id, 'establishment_id' => $listing->id]);

    test()->actingAs($establishmentUser)->patch(route('lgu.directory.establishments.submit', $listing))->assertForbidden();
    test()->actingAs($establishmentUser)->patch(route('pto.directory.publish', $listing))->assertForbidden();
});

test('a listing already decided elsewhere is handled gracefully, not silently overwritten', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $listing = publishWorkflowListingFixture(['municipality' => 'City of Mati', 'status' => 'DRAFT']);
    $listing->update(['municipality_id' => $mati->id]);
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    // The listing never reached FOR_PTO_REVIEW — publish() must refuse it.
    $objWorkflow = app(ListingPublishWorkflow::class);

    expect(fn () => $objWorkflow->publish($pto, $listing))->toThrow(ValidationException::class);
    expect($listing->fresh()->status)->toBe('DRAFT');
});

test('an establishment cannot edit its own live listing — only DRAFT/UNPUBLISHED are editable (Profile/Photos merge)', function () {
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $listing = publishWorkflowListingFixture(['municipality' => 'City of Mati', 'status' => 'PUBLISHED']);
    $listing->update(['municipality_id' => $mati->id]);
    $establishmentUser = User::factory()->create(['role' => UserRole::Establishment, 'municipality_id' => $mati->id, 'establishment_id' => $listing->id]);

    test()->actingAs($establishmentUser)->put(route('establishment.profile.update'), [
        'name' => $listing->name,
        'category' => 'accommodation',
        'address' => 'Updated Barangay',
        'phone' => '09170000000',
        'hours' => '9am-5pm',
    ])->assertForbidden();

    expect($listing->fresh()->barangay)->not->toBe('Updated Barangay');
    test()->get(route('explore'))->assertSee($listing->name);
});
