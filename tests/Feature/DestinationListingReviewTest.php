<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Phase 5 — destination listing request and PTO review: Request to feature -> Pending PTO Review ->
 *              Approve & Publish / Return for Correction -> resubmit; held changes to Published listings; the notification bell.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use App\Enums\ManagingLevel;
use App\Enums\ReportingMethod;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\OperationLog;
use App\Models\SecurityLog;
use App\Models\User;
use App\Notifications\DestinationListingPublished;
use App\Notifications\DestinationListingSubmittedForReview;
use App\Notifications\EstablishmentListingReturnedToLgu;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

function reviewMunicipality(string $strName, string $strCode): Municipality
{
    return Municipality::query()->firstOrCreate(['mun_code' => $strCode], ['mun_name' => $strName]);
}

function reviewLgu(Municipality $objMunicipality): User
{
    return User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_name' => "{$objMunicipality->mun_name} LGU",
        'usr_organization_subtitle' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
    ]);
}

function reviewPto(): User
{
    return User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
}

function reviewCategory(string $strCategoryName = 'Accommodation'): Category
{
    test()->seed(CategorySeeder::class);

    return Category::query()->where('cat_name', $strCategoryName)->firstOrFail();
}

/**
 * @param  array<string, mixed>  $arrOverrides
 */
function reviewEstablishment(Municipality $objMunicipality, array $arrOverrides = []): Listing
{
    $objCategory = reviewCategory();

    return Listing::query()->create(array_merge([
        'lst_slug' => Str::slug('review-inn-'.Str::random(6)),
        'lst_name' => 'Dahican Beach Resort',
        'lst_category' => $objCategory->legacySlug(),
        'cat_id' => $objCategory->cat_id,
        'lst_type' => 'Resort',
        'lst_municipality' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
        'lst_barangay' => 'Dahican',
        'lst_description' => 'Beachfront resort on Dahican.',
        'lst_contact_phone' => '09171234567',
        'lst_status' => 'DRAFT',
    ], $arrOverrides));
}

/**
 * The LGU edit form payload for $objListing with $arrOverrides applied.
 *
 * @return array<string, mixed>
 */
function reviewEditPayload(Listing $objListing, array $arrOverrides = []): array
{
    return array_merge([
        'name' => $objListing->lst_name,
        'cat_id' => $objListing->cat_id,
        'type' => $objListing->lst_type,
        'barangay' => $objListing->lst_barangay,
        'description' => $objListing->lst_description,
        'contact_phone' => $objListing->lst_contact_phone,
    ], $arrOverrides);
}

// --- Request to feature -> Pending PTO Review (never publishes) ---

test('"Request to feature as tourist destination" moves it to Pending PTO Review without publishing, and notifies the PTO', function () {
    Notification::fake();
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objLgu = reviewLgu($objMati);
    $objPto = reviewPto();
    $objListing = reviewEstablishment($objMati);

    test()->actingAs($objLgu)->get(route('lgu.directory.establishments.show', $objListing))
        ->assertOk()
        ->assertSee('Not Requested')
        ->assertSee('Request to feature as tourist destination');

    test()->actingAs($objLgu)->patch(route('lgu.directory.establishments.submit', $objListing))->assertRedirect();

    $objFresh = $objListing->fresh();
    expect($objFresh->lst_status)->toBe('FOR_PTO_REVIEW');
    expect($objFresh->destinationListingLabel())->toBe('Pending PTO Review');
    expect($objFresh->isPubliclyVisible())->toBeFalse();

    Notification::assertSentTo($objPto, DestinationListingSubmittedForReview::class, function (DestinationListingSubmittedForReview $objNotification) use ($objPto, $objListing) {
        $arrData = $objNotification->toDatabase($objPto);

        return $arrData['message'] === 'New Destination Listing for Review: Dahican Beach Resort submitted by City of Mati LGU'
            && $arrData['url'] === route('pto.destinationReviews.show', $objListing, false);
    });

    $objLog = OperationLog::query()->where('opl_entity_type', 'establishment')->where('opl_entity_id', $objListing->lst_id)->latest('opl_id')->first();
    expect($objLog->opl_action)->toBe('submit');
    expect($objLog->usr_id)->toBe($objLgu->usr_id);

    auth()->logout();
    test()->get(route('listings.show', $objListing))->assertNotFound();
});

test('the LGU can never publish — only the PTO can approve', function () {
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objLgu = reviewLgu($objMati);
    $objListing = reviewEstablishment($objMati, ['lst_status' => 'FOR_PTO_REVIEW']);

    test()->actingAs($objLgu)->patch(route('pto.directory.publish', $objListing))->assertForbidden();
    test()->actingAs($objLgu)->get(route('pto.destinationReviews.show', $objListing))->assertForbidden();

    expect($objListing->fresh()->lst_status)->toBe('FOR_PTO_REVIEW');
});

test('an LGU cannot request, resubmit, or return another municipality\'s listing (403 and a security log)', function () {
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objBaganga = reviewMunicipality('Baganga', 'BAG');
    $objMatiLgu = reviewLgu($objMati);
    $objBagangaListing = reviewEstablishment($objBaganga, ['lst_name' => 'Baganga Inn']);

    test()->actingAs($objMatiLgu)->patch(route('lgu.directory.establishments.submit', $objBagangaListing))->assertForbidden();
    test()->actingAs($objMatiLgu)->patch(route('lgu.directory.establishments.return', $objBagangaListing), ['reason' => 'x'])->assertForbidden();
    test()->actingAs($objMatiLgu)->get(route('lgu.directory.establishments.show', $objBagangaListing))->assertForbidden();

    expect($objBagangaListing->fresh()->lst_status)->toBe('DRAFT');
    expect(SecurityLog::query()->where('usr_id', $objMatiLgu->usr_id)->where('sec_event_type', 'access_denied')->count())->toBeGreaterThanOrEqual(3);
});

// --- PTO review screen, Approve & Publish ---

test('the PTO review screen shows the listing details, and Approve & Publish makes it public and notifies the LGU', function () {
    Notification::fake();
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objLgu = reviewLgu($objMati);
    $objPto = reviewPto();
    $objListing = reviewEstablishment($objMati, ['lst_status' => 'FOR_PTO_REVIEW']);

    test()->actingAs($objPto)->get(route('pto.destinationReviews.index'))
        ->assertOk()
        ->assertSee('Dahican Beach Resort')
        ->assertSee('New destination listing');

    test()->actingAs($objPto)->get(route('pto.destinationReviews.show', $objListing))
        ->assertOk()
        ->assertSee('Dahican Beach Resort')
        ->assertSee('Beachfront resort on Dahican.')
        ->assertSee('City of Mati')
        ->assertSee('Photos')
        ->assertSee(route('pto.directory.publish', $objListing), false)
        ->assertSee(route('pto.directory.returnToLgu', $objListing), false);

    test()->actingAs($objPto)->patch(route('pto.directory.publish', $objListing))->assertRedirect();

    $objFresh = $objListing->fresh();
    expect($objFresh->lst_status)->toBe('PUBLISHED');
    expect($objFresh->isPubliclyVisible())->toBeTrue();
    Notification::assertSentTo($objLgu, DestinationListingPublished::class);
    expect(OperationLog::query()->where('opl_entity_id', $objListing->lst_id)->where('opl_action', 'publish')->value('usr_id'))->toBe($objPto->usr_id);

    auth()->logout();
    test()->get(route('listings.show', $objListing))->assertOk()->assertSee('Dahican Beach Resort');
});

// --- Return for Correction -> resubmit ---

test('Return for Correction requires remarks, notifies the LGU, and the LGU corrects and resubmits to Pending PTO Review', function () {
    Notification::fake();
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objLgu = reviewLgu($objMati);
    $objPto = reviewPto();
    $objListing = reviewEstablishment($objMati, ['lst_status' => 'FOR_PTO_REVIEW']);

    // Remarks are required.
    test()->actingAs($objPto)->patch(route('pto.directory.returnToLgu', $objListing), ['reason' => ''])->assertSessionHasErrors('reason');
    expect($objListing->fresh()->lst_status)->toBe('FOR_PTO_REVIEW');

    test()->actingAs($objPto)->patch(route('pto.directory.returnToLgu', $objListing), ['reason' => 'Please add a clearer description.'])->assertRedirect();

    $objFresh = $objListing->fresh();
    expect($objFresh->lst_status)->toBe(Listing::STATUS_FOR_CORRECTION);
    expect($objFresh->destinationListingLabel())->toBe('Returned for Correction');
    expect($objFresh->lst_review_remarks)->toBe('Please add a clearer description.');
    expect($objFresh->isPubliclyVisible())->toBeFalse();
    Notification::assertSentTo($objLgu, EstablishmentListingReturnedToLgu::class);
    expect(OperationLog::query()->where('opl_entity_id', $objListing->lst_id)->where('opl_action', 'return')->value('opl_reason'))->toBe('Please add a clearer description.');

    test()->actingAs($objLgu)->get(route('lgu.directory.establishments.show', $objListing))
        ->assertOk()
        ->assertSee('Returned for Correction')
        ->assertSee('Please add a clearer description.')
        ->assertSee('Resubmit to PTO');

    // While returned, public content is editable again (applied directly — it is not public).
    test()->actingAs($objLgu)->put(route('lgu.directory.establishments.update', $objListing), reviewEditPayload($objFresh, ['description' => 'A clearer description.']))
        ->assertSessionHasNoErrors();
    expect($objListing->fresh()->lst_description)->toBe('A clearer description.');

    test()->actingAs($objLgu)->patch(route('lgu.directory.establishments.submit', $objListing))->assertRedirect();

    $objResubmitted = $objListing->fresh();
    expect($objResubmitted->lst_status)->toBe('FOR_PTO_REVIEW');
    expect($objResubmitted->lst_review_remarks)->toBeNull();
    Notification::assertSentTo($objPto, DestinationListingSubmittedForReview::class);
});

// --- Held changes to a Published listing ---

test('LGU edits to a Published listing are held for PTO review while the published version stays live', function () {
    Notification::fake();
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objLgu = reviewLgu($objMati);
    $objPto = reviewPto();
    $objListing = reviewEstablishment($objMati, ['lst_status' => 'PUBLISHED']);
    $objFood = reviewCategory('Food & Dining');

    test()->actingAs($objLgu)->put(route('lgu.directory.establishments.update', $objListing), reviewEditPayload($objListing, [
        'name' => 'Dahican Beach Resort & Cafe',
        'cat_id' => $objFood->cat_id,
        'type' => 'Restobar',
        'contact_phone' => '09998887777',
    ]))->assertSessionHasNoErrors();

    $objFresh = $objListing->fresh();
    expect($objFresh->lst_status)->toBe('PUBLISHED');
    expect($objFresh->lst_name)->toBe('Dahican Beach Resort');
    expect($objFresh->lst_category)->toBe('accommodation');
    expect($objFresh->lst_contact_phone)->toBe('09998887777');
    expect($objFresh->hasPendingChanges())->toBeTrue();
    expect($objFresh->destinationListingLabel())->toBe('Published · Changes pending PTO review');
    Notification::assertSentTo($objPto, DestinationListingSubmittedForReview::class, fn ($objNotification) => str_starts_with($objNotification->toDatabase($objPto)['message'], 'Changes to a Published Listing for Review'));

    // The public site keeps the approved content.
    test()->get(route('listings.show', $objListing))->assertOk()->assertSee('Dahican Beach Resort')->assertDontSee('Resort &amp; Cafe', false);

    // The LGU sees its proposed values in the form.
    test()->actingAs($objLgu)->get(route('lgu.directory.establishments.edit', $objListing))->assertOk()->assertSee('Dahican Beach Resort &amp; Cafe', false);

    test()->actingAs($objPto)->get(route('pto.destinationReviews.show', $objListing))
        ->assertOk()
        ->assertSee('Proposed changes')
        ->assertSee('Live now')
        ->assertSee('Dahican Beach Resort &amp; Cafe', false)
        ->assertSee('Food &amp; Dining', false);

    // Returned: still live, the changes stay held with the PTO's remarks.
    test()->actingAs($objPto)->patch(route('pto.directory.returnToLgu', $objListing), ['reason' => 'Keep the original name.'])->assertRedirect();
    $objReturned = $objListing->fresh();
    expect($objReturned->lst_status)->toBe('PUBLISHED');
    expect($objReturned->hasReturnedPendingChanges())->toBeTrue();
    expect($objReturned->destinationListingLabel())->toBe('Published · Changes returned for correction');
    Notification::assertSentTo($objLgu, EstablishmentListingReturnedToLgu::class);

    // Corrected and resubmitted, then approved: the changes go live.
    test()->actingAs($objLgu)->put(route('lgu.directory.establishments.update', $objListing), reviewEditPayload($objListing->fresh(), [
        'cat_id' => $objFood->cat_id,
        'type' => 'Restobar',
    ]))->assertSessionHasNoErrors();
    expect($objListing->fresh()->lst_review_remarks)->toBeNull();
    expect($objListing->fresh()->lst_pending_changes)->toEqual(['cat_id' => $objFood->cat_id, 'lst_type' => 'Restobar']);

    test()->actingAs($objPto)->patch(route('pto.directory.publish', $objListing))->assertRedirect();

    $objApproved = $objListing->fresh();
    expect($objApproved->lst_status)->toBe('PUBLISHED');
    expect($objApproved->lst_name)->toBe('Dahican Beach Resort');
    expect($objApproved->cat_id)->toBe($objFood->cat_id);
    expect($objApproved->lst_category)->toBe('restaurants');
    expect($objApproved->lst_type)->toBe('Restobar');
    expect($objApproved->lst_pending_changes)->toBeNull();
    Notification::assertSentTo($objLgu, DestinationListingPublished::class);
});

test('saving a Published listing with only contact changes, or with the live values back, sends nothing for review', function () {
    Notification::fake();
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objLgu = reviewLgu($objMati);
    $objPto = reviewPto();
    $objListing = reviewEstablishment($objMati, ['lst_status' => 'PUBLISHED']);

    test()->actingAs($objLgu)->put(route('lgu.directory.establishments.update', $objListing), reviewEditPayload($objListing, ['contact_phone' => '09170000000']))->assertSessionHasNoErrors();
    expect($objListing->fresh()->hasPendingChanges())->toBeFalse();
    Notification::assertNothingSentTo($objPto);

    // Propose a change, then put the live value back: the held change is withdrawn.
    test()->actingAs($objLgu)->put(route('lgu.directory.establishments.update', $objListing), reviewEditPayload($objListing, ['name' => 'Temporary Name']));
    expect($objListing->fresh()->hasPendingChanges())->toBeTrue();
    test()->actingAs($objLgu)->put(route('lgu.directory.establishments.update', $objListing), reviewEditPayload($objListing->fresh()));
    expect($objListing->fresh()->lst_pending_changes)->toBeNull();
    expect($objListing->fresh()->lst_name)->toBe('Dahican Beach Resort');
});

test('unpublishing a listing with held changes folds them into the hidden listing for the next full review', function () {
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objPto = reviewPto();
    $objListing = reviewEstablishment($objMati, ['lst_status' => 'PUBLISHED']);
    $objListing->forceFill(['lst_pending_changes' => ['lst_name' => 'New Name']])->save();

    test()->actingAs($objPto)->patch(route('pto.directory.unpublish', $objListing), ['reason' => 'Seasonal closure.'])->assertRedirect();

    $objFresh = $objListing->fresh();
    expect($objFresh->lst_status)->toBe('UNPUBLISHED');
    expect($objFresh->isPubliclyVisible())->toBeFalse();
    expect($objFresh->lst_name)->toBe('New Name');
    expect($objFresh->lst_pending_changes)->toBeNull();
});

// --- The destination listing never touches reporting, the account, or QR ---

test('requesting, returning, and approving a destination listing leaves the reporting method, account, and QR alone', function () {
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objLgu = reviewLgu($objMati);
    $objPto = reviewPto();
    $objListing = reviewEstablishment($objMati);
    $objListing->forceFill(['lst_reporting_mode' => ReportingMethod::OnlineItour])->save();
    $objAccount = User::factory()->create(['usr_role' => UserRole::Establishment, 'mun_id' => $objMati->mun_id, 'lst_id' => $objListing->lst_id]);
    expect($objListing->fresh()->isAcceptingRegistrations())->toBeTrue();

    test()->actingAs($objLgu)->patch(route('lgu.directory.establishments.submit', $objListing));
    expect($objListing->fresh()->isAcceptingRegistrations())->toBeTrue();
    test()->actingAs($objPto)->patch(route('pto.directory.returnToLgu', $objListing), ['reason' => 'More photos, please.']);
    expect($objListing->fresh()->isAcceptingRegistrations())->toBeTrue();

    expect($objListing->fresh()->lst_reporting_mode)->toBe(ReportingMethod::OnlineItour);
    expect($objAccount->fresh()->usr_status)->toBe('Active');
});

// --- Notification bell ---

test('the notification bell lists the user\'s notifications, opens one (marking it read), and marks all read', function () {
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objLgu = reviewLgu($objMati);
    $objPto = reviewPto();
    $objListing = reviewEstablishment($objMati);

    test()->actingAs($objLgu)->patch(route('lgu.directory.establishments.submit', $objListing));
    $objNotification = $objPto->notifications()->sole();

    test()->actingAs($objPto)->get(route('pto.destinationReviews.index'))
        ->assertOk()
        ->assertSee('New Destination Listing for Review: Dahican Beach Resort submitted by City of Mati LGU')
        ->assertSee('1 unread');

    test()->actingAs($objPto)->post(route('notifications.open', $objNotification->id))
        ->assertRedirect(route('pto.destinationReviews.show', $objListing, false));
    expect($objNotification->fresh()->read_at)->not->toBeNull();

    // Another user's notification is not found, and an external link is never followed.
    test()->actingAs($objLgu)->post(route('notifications.open', $objNotification->id))->assertNotFound();
    $objExternal = $objPto->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['message' => 'External', 'url' => 'https://evil.example.com/phish'],
    ]);
    test()->actingAs($objPto)->from(route('pto.destinationReviews.index'))->post(route('notifications.open', $objExternal->id))
        ->assertRedirect(route('pto.destinationReviews.index'));

    test()->actingAs($objPto)->post(route('notifications.readAll'))->assertRedirect();
    expect($objPto->unreadNotifications()->count())->toBe(0);
});

/*
 * Objective 3, Phase 2 — one destination lifecycle (Draft -> Pending Review -> For Correction ->
 * Published -> Archived), PTO-only archive/restore/reinstate, managing level, and the
 * reviewed visitor information / entrance fee.
 */

/**
 * A destination-only record (Tourist Destinations) in $objMunicipality.
 *
 * @param  array<string, mixed>  $arrOverrides
 */
function reviewAttraction(Municipality $objMunicipality, array $arrOverrides = [], ?ManagingLevel $objManagingLevel = null): Listing
{
    $objCategory = reviewCategory('Tourist Destinations');
    $objListing = Listing::query()->make(array_merge([
        'lst_slug' => Str::slug('review-falls-'.Str::random(6)),
        'lst_name' => 'Review Falls',
        'lst_category' => 'destinations',
        'cat_id' => $objCategory->cat_id,
        'lst_municipality' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
        'lst_barangay' => 'Dapnan',
        'lst_description' => 'A multi-tiered waterfall.',
        'lst_status' => 'DRAFT',
    ], $arrOverrides));
    $objListing->lst_managing_level = $objManagingLevel;
    $objListing->save();

    return $objListing;
}

test('records the PTO creates start as Draft, are not public, and record their creator', function () {
    $objCateel = reviewMunicipality('Cateel', 'CAT');
    $objPto = reviewPto();

    test()->actingAs($objPto)->post(route('pto.directory.store'), [
        'name' => 'Aliwagwag Falls',
        'cat_id' => reviewCategory('Tourist Destinations')->cat_id,
        'barangay' => 'Aliwagwag',
        'municipality' => 'Cateel',
        'destination_type' => 'Waterfall',
        'managing_level' => 'pto',
        'visitor_information' => 'Wear water shoes.',
        'entrance_fee' => 'PHP 50',
    ])->assertSessionHasNoErrors();

    test()->actingAs($objPto)->post(route('pto.directory.store'), [
        'name' => 'Cateel River Inn',
        'cat_id' => reviewCategory('Accommodation')->cat_id,
        'type' => 'Resort',
        'barangay' => 'Poblacion',
        'municipality' => 'Cateel',
    ])->assertSessionHasNoErrors();

    $objDestination = Listing::query()->where('lst_name', 'Aliwagwag Falls')->firstOrFail();
    $objEstablishment = Listing::query()->where('lst_name', 'Cateel River Inn')->firstOrFail();

    expect($objDestination->lst_status)->toBe('DRAFT');
    expect($objEstablishment->lst_status)->toBe('DRAFT');
    expect($objDestination->lst_type)->toBe('Waterfall');
    expect($objDestination->lst_visitor_information)->toBe('Wear water shoes.');
    expect($objDestination->lst_entrance_fee)->toBe('PHP 50');
    expect($objDestination->managingLevel())->toBe(ManagingLevel::Pto);
    expect($objEstablishment->lst_managing_level)->toBeNull();
    expect($objDestination->lst_created_by)->toBe($objPto->usr_id);
    expect($objDestination->mun_id)->toBe($objCateel->mun_id);

    // Summary comment: as a member of the public (the PTO itself may preview drafts).
    auth()->logout();
    test()->get(route('listings.show', $objDestination))->assertNotFound();
    test()->get(route('listings.show', $objEstablishment))->assertNotFound();
});

test('the PTO moves a PTO-managed Draft into review and then publishes it; the LGU cannot edit, submit, or manage its photos', function () {
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objLgu = reviewLgu($objMati);
    $objPto = reviewPto();
    $objMuseum = reviewAttraction($objMati, ['lst_name' => 'Subangan Museum'], ManagingLevel::Pto);

    // Summary comment: the LGU of the same municipality may view it, but not change it (403, security-logged).
    test()->actingAs($objLgu)->get(route('lgu.directory.attractions.show', $objMuseum))->assertOk()->assertSee('managed by the Provincial Tourism Office');
    test()->actingAs($objLgu)->get(route('lgu.directory.attractions.edit', $objMuseum))->assertForbidden();
    test()->actingAs($objLgu)->put(route('lgu.directory.attractions.update', $objMuseum), ['name' => 'Renamed', 'barangay' => 'Central'])->assertForbidden();
    test()->actingAs($objLgu)->patch(route('lgu.directory.attractions.submit', $objMuseum))->assertForbidden();
    test()->actingAs($objLgu)->put(route('lgu.images.reorder', $objMuseum), ['order' => [1]])->assertForbidden();
    test()->actingAs($objLgu)->post(route('lgu.images.store'), ['listing_id' => $objMuseum->lst_id])->assertForbidden();
    // Summary comment: the two photo actions that call ImagePolicy directly are security-logged too (Phase 3).
    $colPhotoDenials = SecurityLog::query()->where('usr_id', $objLgu->usr_id)->where('sec_event_type', 'access_denied')->get()->pluck('sec_details.ability');
    expect($colPhotoDenials)->toContain('manageListing')->toContain('uploadFor');
    expect($objMuseum->fresh()->lst_name)->toBe('Subangan Museum');
    expect($objMuseum->fresh()->lst_status)->toBe('DRAFT');
    expect(SecurityLog::query()->where('usr_id', $objLgu->usr_id)->where('sec_event_type', 'access_denied')->count())->toBeGreaterThanOrEqual(3);

    // Summary comment: the PTO submits it (never publishes directly), then approves it.
    test()->actingAs($objPto)->patch(route('pto.directory.submit', $objMuseum))->assertRedirect();
    expect($objMuseum->fresh()->lst_status)->toBe('FOR_PTO_REVIEW');
    auth()->logout();
    test()->get(route('listings.show', $objMuseum))->assertNotFound();

    test()->actingAs($objPto)->patch(route('pto.directory.publish', $objMuseum))->assertRedirect();
    expect($objMuseum->fresh()->lst_status)->toBe('Active');
    auth()->logout();
    test()->get(route('listings.show', $objMuseum))->assertOk();
});

test('the PTO cannot submit an LGU-managed destination — its LGU does', function () {
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objFalls = reviewAttraction($objMati);

    test()->actingAs(reviewPto())->patch(route('pto.directory.submit', $objFalls))->assertForbidden();
    expect($objFalls->fresh()->lst_status)->toBe('DRAFT');

    test()->actingAs(reviewLgu($objMati))->patch(route('lgu.directory.attractions.submit', $objFalls))->assertRedirect();
    expect($objFalls->fresh()->lst_status)->toBe('FOR_PTO_REVIEW');
});

test('a suspended destination is reinstated only to Draft and must pass review again before it is public', function () {
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objLgu = reviewLgu($objMati);
    $objPto = reviewPto();
    $objFalls = reviewAttraction($objMati, ['lst_status' => 'Active']);

    test()->actingAs($objPto)->put(route('pto.directory.updateStatus', $objFalls), ['status' => 'Suspended', 'reason' => 'Trail repairs.'])->assertSessionHasNoErrors();
    expect($objFalls->fresh()->lst_status)->toBe('Suspended');
    auth()->logout();
    test()->get(route('listings.show', $objFalls))->assertNotFound();

    // Summary comment: no direct way back to Published.
    test()->actingAs($objPto)->put(route('pto.directory.updateStatus', $objFalls), ['status' => 'Active', 'reason' => 'Reopened.'])->assertSessionHasErrors('status');
    expect($objFalls->fresh()->lst_status)->toBe('Suspended');

    test()->actingAs($objPto)->put(route('pto.directory.updateStatus', $objFalls), ['status' => 'DRAFT', 'reason' => 'Repairs done.'])->assertSessionHasNoErrors();
    expect($objFalls->fresh()->lst_status)->toBe('DRAFT');
    auth()->logout();
    test()->get(route('listings.show', $objFalls))->assertNotFound();

    test()->actingAs($objLgu)->patch(route('lgu.directory.attractions.submit', $objFalls))->assertRedirect();
    test()->actingAs($objPto)->patch(route('pto.directory.publish', $objFalls))->assertRedirect();
    expect($objFalls->fresh()->lst_status)->toBe('Active');

    $objLog = OperationLog::query()->where('opl_entity_type', 'destination')->where('opl_entity_id', $objFalls->lst_id)->where('opl_reason', 'Repairs done.')->first();
    expect($objLog?->opl_new_values)->toBe(['lst_status' => 'DRAFT']);
});

test('only the PTO archives; an archived destination can only be restored to Draft, folding any held changes', function () {
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objPto = reviewPto();
    $objFalls = reviewAttraction($objMati, ['lst_status' => 'Active']);
    $objFalls->forceFill(['lst_pending_changes' => ['lst_name' => 'Review Falls Eco-Park']])->save();

    test()->actingAs(reviewLgu($objMati))->patch(route('lgu.directory.destinations.archive', $objFalls))->assertForbidden();
    expect($objFalls->fresh()->lst_status)->toBe('Active');

    test()->actingAs($objPto)->put(route('pto.directory.updateStatus', $objFalls), ['status' => 'Archived', 'reason' => 'Closed permanently.'])->assertSessionHasNoErrors();
    $objArchived = $objFalls->fresh();
    expect($objArchived->lst_status)->toBe('Archived');
    expect($objArchived->lst_name)->toBe('Review Falls Eco-Park');
    expect($objArchived->lst_pending_changes)->toBeNull();

    test()->actingAs($objPto)->put(route('pto.directory.updateStatus', $objFalls), ['status' => 'Suspended', 'reason' => 'x'])->assertSessionHasErrors('status');

    test()->actingAs($objPto)->put(route('pto.directory.updateStatus', $objFalls), ['status' => 'DRAFT', 'reason' => 'Reopening.'])->assertSessionHasNoErrors();
    expect($objFalls->fresh()->lst_status)->toBe('DRAFT');
});

test('visitor information and entrance fee edits to a Published destination are held for PTO review', function () {
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objLgu = reviewLgu($objMati);
    $objFalls = reviewAttraction($objMati, ['lst_status' => 'Active', 'lst_visitor_information' => 'Bring water.', 'lst_entrance_fee' => 'Free'], ManagingLevel::Lgu);

    test()->actingAs($objLgu)->put(route('lgu.directory.attractions.update', $objFalls), [
        'name' => 'Review Falls',
        'barangay' => 'Dapnan',
        'description' => 'A multi-tiered waterfall.',
        'visitor_information' => 'Bring water and a guide.',
        'entrance_fee' => 'PHP 30',
        'contact_phone' => '09170000000',
    ])->assertSessionHasNoErrors();

    $objHeld = $objFalls->fresh();
    expect($objHeld->lst_visitor_information)->toBe('Bring water.');
    expect($objHeld->lst_entrance_fee)->toBe('Free');
    expect($objHeld->lst_pending_changes)->toBe(['lst_visitor_information' => 'Bring water and a guide.', 'lst_entrance_fee' => 'PHP 30']);
    expect($objHeld->lst_contact_phone)->toBe('09170000000');
    expect($objHeld->lst_updated_by)->toBe($objLgu->usr_id);

    test()->actingAs(reviewPto())->get(route('pto.destinationReviews.show', $objFalls))->assertOk()->assertSee('Visitor information')->assertSee('PHP 30');
    test()->actingAs(reviewPto())->patch(route('pto.directory.publish', $objFalls))->assertRedirect();

    $objApproved = $objFalls->fresh();
    expect($objApproved->lst_visitor_information)->toBe('Bring water and a guide.');
    expect($objApproved->lst_entrance_fee)->toBe('PHP 30');
    expect($objApproved->lst_pending_changes)->toBeNull();
});

test('a new LGU attraction is LGU-managed, records its creator, and only accepts a configured destination type', function () {
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objLgu = reviewLgu($objMati);

    test()->actingAs($objLgu)->post(route('lgu.directory.attractions.store'), ['name' => 'Bay Islet', 'barangay' => 'Badas', 'type' => 'Island'])
        ->assertSessionHasErrors(['type' => 'Choose a destination type from the list.']);

    test()->actingAs($objLgu)->post(route('lgu.directory.attractions.store'), ['name' => 'Dahican Beach', 'barangay' => 'Dahican', 'type' => 'Beach'])
        ->assertSessionHasNoErrors();

    $objBeach = Listing::query()->where('lst_name', 'Dahican Beach')->firstOrFail();
    expect($objBeach->lst_type)->toBe('Beach');
    expect($objBeach->lst_managing_level)->toBe(ManagingLevel::Lgu);
    expect($objBeach->lst_created_by)->toBe($objLgu->usr_id);
    expect(Listing::query()->where('lst_name', 'Bay Islet')->exists())->toBeFalse();
});

test('the PTO review screen shows a destination-only record\'s workflow history, and returned destinations are listed', function () {
    $objMati = reviewMunicipality('City of Mati', 'MATI');
    $objLgu = reviewLgu($objMati);
    $objPto = reviewPto();
    $objFalls = reviewAttraction($objMati, ['lst_name' => 'History Falls']);

    test()->actingAs($objLgu)->patch(route('lgu.directory.attractions.submit', $objFalls))->assertRedirect();

    test()->actingAs($objPto)->get(route('pto.destinationReviews.show', $objFalls))
        ->assertOk()
        ->assertSee('Last submitted by '.$objLgu->usr_name);

    test()->actingAs($objPto)->patch(route('pto.directory.returnToLgu', $objFalls), ['reason' => 'Add the barangay hall landmark.'])->assertRedirect();

    test()->actingAs($objPto)->get(route('pto.destinationReviews.index'))
        ->assertOk()
        ->assertSee('History Falls')
        ->assertSee('Add the barangay hall landmark.');
});
