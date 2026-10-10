<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — per-listing feedback QR form and the dashboard bell notifications.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\MonthlyReportStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\EstablishmentImage;
use App\Models\Feedback;
use App\Models\Listing;
use App\Models\MonthlyArrivalReport;
use App\Models\User;
use App\Notifications\SystemNotice;
use App\Support\MonthlyReportReminder;
use App\Support\TourismCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// makeEstablishmentListing()/makeEstablishmentUser() and makeLguUser() come from
// the Rbac test files (shared Pest namespace).

test('each published listing has its own formal feedback form', function () {
    $objListing = makeEstablishmentListing('City of Mati', 'MATI', 'Botanika Resort');
    $objOther = makeEstablishmentListing('City of Mati', 'MATI', 'Other Resort');

    test()->get(route('feedback.form', $objListing->lst_uuid))
        ->assertOk()
        ->assertSee('Visitor Feedback Form')
        ->assertSee('Botanika Resort')
        ->assertDontSee('Other Resort');

    expect($objListing->lst_uuid)->not->toBe($objOther->lst_uuid);
});

test('an unpublished listing refuses feedback', function () {
    $objListing = makeEstablishmentListing('City of Mati', 'MATI', 'Draft Inn', ['lst_status' => 'DRAFT']);

    test()->get(route('feedback.form', $objListing->lst_uuid))->assertOk()->assertSee('not accepting feedback');
    test()->post(route('feedback.submit', $objListing->lst_uuid), ['rating' => 5, 'comment' => 'Lovely place'])->assertNotFound();
    expect(Feedback::query()->count())->toBe(0);
});

test('submitting the form stores the structured answers and notifies the establishment and LGU', function () {
    $objListing = makeEstablishmentListing('City of Mati', 'MATI', 'Botanika Resort');
    $objEstablishment = makeEstablishmentUser($objListing);
    $objLgu = makeLguUser($objListing->municipalityRecord);

    test()->post(route('feedback.submit', $objListing->lst_uuid), [
        'rating' => 4,
        'comment' => 'Friendly staff and clean rooms.',
        'visit_date' => '2026-10-01',
        'visit_purpose' => 'Leisure',
        'visitor_origin' => 'Local',
        'aspects' => ['service' => 5, 'cleanliness' => 4],
        'would_recommend' => '1',
        'email' => 'guest@example.com',
    ])->assertRedirect(route('feedback.form', $objListing->lst_uuid));

    $objFeedback = Feedback::query()->sole();
    expect($objFeedback->lst_id)->toBe($objListing->lst_id);
    expect($objFeedback->fbk_aspect_ratings)->toBe(['service' => 5, 'cleanliness' => 4]);
    expect($objFeedback->fbk_would_recommend)->toBeTrue();
    expect($objFeedback->fbk_visit_purpose)->toBe('Leisure');

    expect($objEstablishment->unreadNotifications)->toHaveCount(1);
    expect($objLgu->unreadNotifications)->toHaveCount(1);
});

test('the establishment QR page shows both the arrival and feedback codes', function () {
    $objListing = makeEstablishmentListing('City of Mati', 'MATI', 'Botanika Resort');
    $objUser = makeEstablishmentUser($objListing);

    test()->actingAs($objUser)->get(route('establishment.qr'))
        ->assertOk()
        ->assertSee('Tourist Arrival QR')
        ->assertSee('Feedback QR')
        ->assertSee(route('feedback.form', $objListing->lst_uuid));
});

test('the bell lists notifications, opening one marks it read, and users cannot open others', function () {
    $objListing = makeEstablishmentListing('City of Mati', 'MATI', 'Botanika Resort');
    $objUser = makeEstablishmentUser($objListing);
    $objOtherUser = makeEstablishmentUser(makeEstablishmentListing('City of Mati', 'MATI', 'Other Resort'));

    $objUser->notify(new SystemNotice('x', 'Hello from the bell', route('establishment.feedback.index')));

    test()->actingAs($objUser)->get(route('establishment.dashboard'))->assertSee('Hello from the bell');

    $strId = $objUser->unreadNotifications()->first()->id;

    test()->actingAs($objOtherUser)->get(route('notifications.open', $strId))->assertNotFound();
    test()->actingAs($objUser)->get(route('notifications.open', $strId))->assertRedirect(route('establishment.feedback.index'));
    expect($objUser->fresh()->unreadNotifications)->toHaveCount(0);
});

test('mark all as read clears the unread count', function () {
    $objUser = makeEstablishmentUser(makeEstablishmentListing('City of Mati', 'MATI', 'Botanika Resort'));
    $objUser->notify(new SystemNotice('x', 'One'));
    $objUser->notify(new SystemNotice('x', 'Two'));

    test()->actingAs($objUser)->post(route('notifications.readAll'))->assertRedirect();

    expect($objUser->fresh()->unreadNotifications)->toHaveCount(0);
});

test('the monthly report workflow notifies LGU, establishment and PTO in turn', function () {
    $objListing = makeEstablishmentListing('Boston', 'BOS', 'Pujada View Inn');
    $objLgu = makeLguUser($objListing->municipalityRecord);
    $objEstablishment = makeEstablishmentUser($objListing);
    $objPto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    test()->actingAs($objEstablishment)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09']);
    expect($objLgu->unreadNotifications()->count())->toBe(1);

    $objReport = MonthlyArrivalReport::query()->sole();
    test()->actingAs($objLgu)->patch(route('lgu.monthlyReports.verify', $objReport));
    expect($objEstablishment->unreadNotifications()->count())->toBe(1);
    expect($objReport->fresh()->mar_status)->toBe(MonthlyReportStatus::Verified);

    test()->actingAs($objLgu)->post(route('lgu.monthlyReports.consolidate'), ['period_month' => '2026-09']);
    expect($objPto->unreadNotifications()->count())->toBe(1);
});

test('the reminder command notifies an establishment once per stage', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00'));
    $objUser = makeEstablishmentUser(makeEstablishmentListing('City of Mati', 'MATI', 'Botanika Resort'));

    test()->artisan('reports:send-reminders')->assertSuccessful();
    test()->artisan('reports:send-reminders')->assertSuccessful();

    expect($objUser->notifications()->count())->toBe(1);

    $this->travelTo(CarbonImmutable::parse('2026-10-15 08:00'));
    test()->artisan('reports:send-reminders')->assertSuccessful();

    expect($objUser->notifications()->count())->toBe(2);
});

function noArrivalRecordsListing(string $strName = 'Corner Eatery'): Listing
{
    $objCategory = Category::query()->firstOrCreate(
        ['cat_name' => 'Food & Dining'],
        ['cat_sort_order' => 2, 'cat_is_active' => true, 'cat_is_qr_enabled' => false],
    );

    return makeEstablishmentListing('City of Mati', 'MATI', $strName, ['cat_id' => $objCategory->cat_id, 'lst_category' => 'food']);
}

test('an establishment whose category does not need arrival records loses those pages but keeps feedback', function () {
    $objListing = noArrivalRecordsListing();
    $objUser = makeEstablishmentUser($objListing);

    test()->actingAs($objUser)->get(route('establishment.dashboard'))
        ->assertOk()
        ->assertDontSee('Record Arrival')
        ->assertDontSee('Monthly Report');

    foreach (['establishment.arrivals.record', 'establishment.arrivals.index', 'establishment.arrivals.monthly'] as $strRoute) {
        test()->actingAs($objUser)->get(route($strRoute))->assertForbidden();
    }
    test()->actingAs($objUser)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])->assertForbidden();

    test()->actingAs($objUser)->get(route('establishment.qr'))
        ->assertOk()
        ->assertSee('Feedback QR')
        ->assertDontSee('Tourist Arrival QR');

    test()->get(route('feedback.form', $objListing->lst_uuid))->assertOk()->assertSee('Visitor Feedback Form');
    test()->get(route('lgu.establishmentQr', $objListing->lst_uuid))->assertSee('not accepting registrations');
});

test('no reminders or Not Submitted rows for an establishment that does not need arrival records', function () {
    $objListing = noArrivalRecordsListing();
    $objUser = makeEstablishmentUser($objListing);
    $objLgu = makeLguUser($objListing->municipalityRecord);

    expect(MonthlyReportReminder::forListing($objListing, CarbonImmutable::parse('2026-10-20')))->toBeNull();

    $this->travelTo(CarbonImmutable::parse('2026-10-15 08:00'));
    test()->artisan('reports:send-reminders')->assertSuccessful();
    expect($objUser->notifications()->count())->toBe(0);

    test()->actingAs($objLgu)->get(route('lgu.monthlyReports.index'))->assertOk()->assertDontSee('Corner Eatery');
});

test('destinations always require arrival records', function () {
    $objListing = makeEstablishmentListing('City of Mati', 'MATI', 'Dahican Beach', ['lst_category' => 'destinations', 'lst_status' => 'Active']);

    expect($objListing->requiresArrivalRecords())->toBeTrue();
});

test('adding a destination from the LGU Add New form also creates its login, like an establishment', function () {
    $objMunicipality = makeMunicipality('City of Mati', 'MATI');
    $objLgu = makeLguUser($objMunicipality);

    test()->actingAs($objLgu)->post(route('lgu.users.store'), [
        'entryType' => 'destination',
        'name' => 'Dahican Beach',
        'barangay' => TourismCatalog::barangaysFor($objLgu->usr_organization_subtitle)[0],
        'ownerName' => 'Maria Santos',
        'contactPhone' => '0917 123 4567',
        'email' => 'maria@example.com',
    ])->assertRedirect()->assertSessionHas('accountCreated');

    $objListing = Listing::query()->where('lst_name', 'Dahican Beach')->sole();
    expect($objListing->lst_category)->toBe('destinations');
    expect($objListing->lst_status)->toBe('Active');
    expect($objListing->mun_id)->toBe($objMunicipality->mun_id);

    $objMaintainer = User::query()->where('usr_email', 'maria@example.com')->sole();
    expect($objMaintainer->lst_id)->toBe($objListing->lst_id);
    expect($objMaintainer->usr_role)->toBe(UserRole::Establishment);

    // listed on the Accounts page with a View button and modal
    test()->actingAs($objLgu)->get(route('lgu.users'))
        ->assertOk()
        ->assertSee('Establishment / Destination')
        ->assertSee('Dahican Beach')
        ->assertSee('user-view-'.$objMaintainer->usr_id);

    // the account works like an establishment's: arrivals, monthly report, no profile menu
    test()->actingAs($objMaintainer)->get(route('establishment.arrivals.monthly'))->assertOk();
    test()->actingAs($objMaintainer)->get(route('establishment.dashboard'))->assertOk()->assertDontSee('Establishment Profile');
    test()->actingAs($objMaintainer)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])->assertRedirect();

    test()->actingAs($objLgu)->get(route('lgu.monthlyReports.index', ['period' => '2026-09']))->assertOk()->assertSee('Dahican Beach');
    expect($objLgu->unreadNotifications()->count())->toBe(1);

    // editing the destination account keeps it a destination
    test()->actingAs($objLgu)->put(route('lgu.users.update', $objMaintainer->usr_id), [
        'name' => 'Dahican Beach Park',
        'barangay' => $objListing->lst_barangay,
        'ownerName' => 'Maria Santos',
        'contactPhone' => '0917 123 4567',
        'email' => 'maria@example.com',
    ])->assertRedirect();
    expect($objListing->fresh()->lst_name)->toBe('Dahican Beach Park');
    expect($objListing->fresh()->lst_category)->toBe('destinations');
});

test('a destination account can upload photos that go to its LGU for approval', function () {
    Storage::fake();
    $objMunicipality = makeMunicipality('City of Mati', 'MATI');
    $objLgu = makeLguUser($objMunicipality);

    test()->actingAs($objLgu)->post(route('lgu.users.store'), [
        'entryType' => 'destination', 'name' => 'Dahican Beach',
        'barangay' => TourismCatalog::barangaysFor($objLgu->usr_organization_subtitle)[0],
        'ownerName' => 'Maria Santos', 'contactPhone' => '0917 123 4567', 'email' => 'maria@example.com',
    ]);
    $objMaintainer = User::query()->where('usr_email', 'maria@example.com')->sole();
    $objListing = Listing::query()->where('lst_name', 'Dahican Beach')->sole();

    test()->actingAs($objMaintainer)->get(route('establishment.photos'))->assertOk()->assertSee('Add Photo');
    test()->actingAs($objMaintainer)->get(route('establishment.dashboard'))->assertSee('Photos');

    test()->actingAs($objMaintainer)->post(route('establishment.images.store'), [
        'listing_id' => $objListing->lst_id,
        'photos' => [UploadedFile::fake()->image('beach.jpg', 1600, 1200)],
        'ownership_declared' => '1',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $objImage = EstablishmentImage::query()->where('lst_id', $objListing->lst_id)->sole();
    expect($objImage->img_status->value)->toBe('PENDING');
    expect($objLgu->can('approve', $objImage))->toBeTrue();
});

test('an establishment account has no destination Photos page', function () {
    $objUser = makeEstablishmentUser(makeEstablishmentListing('City of Mati', 'MATI', 'Botanika Resort'));

    test()->actingAs($objUser)->get(route('establishment.photos'))->assertNotFound();
});

test('the LGU Add New modal asks before closing when clicked outside', function () {
    $objLgu = makeLguUser(makeMunicipality('City of Mati', 'MATI'));

    test()->actingAs($objLgu)->get(route('lgu.users'))
        ->assertOk()
        ->assertSee('data-confirm-outside-close', false);
});

test('the Record Arrival form saves the contact number and remarks and shows every detail field', function () {
    $objListing = makeEstablishmentListing('City of Mati', 'MATI', 'Botanika Resort');
    $objUser = makeEstablishmentUser($objListing);

    test()->actingAs($objUser)->get(route('establishment.arrivals.record'))
        ->assertOk()
        ->assertSee('Contact Number')
        ->assertSee('Remarks')
        ->assertSee('Where are they from?')
        ->assertSee('Home Country');

    test()->actingAs($objUser)->postJson(route('establishment.arrivals.store'), [
        'date' => '2026-10-09', 'visitorName' => 'Juan Dela Cruz', 'visitorContact' => '09171234567',
        'remarks' => 'Birthday group', 'visitType' => 'Daytour', 'male' => 2, 'female' => 1, 'adults' => 3, 'local' => 3,
    ])->assertOk();

    $objArrival = $objListing->arrivals()->sole();
    expect($objArrival->arr_visitor_contact)->toBe('09171234567');
    expect($objArrival->arr_remarks)->toBe('Birthday group');

    test()->actingAs($objUser)->get(route('establishment.arrivals.index'))
        ->assertSee('09171234567')
        ->assertSee('Birthday group')
        ->assertDontSee('Classification');
});

test('the public QR check-in form has the same fields as the Record Arrival form', function () {
    $objListing = makeEstablishmentListing('City of Mati', 'MATI', 'Botanika Resort');

    test()->get(route('lgu.establishmentQr', $objListing->lst_uuid))
        ->assertOk()
        ->assertSee('Visit Type')
        ->assertSee('Date')
        ->assertSee('Contact Number')
        ->assertSee('Remarks')
        ->assertSee('Where are you from?')
        ->assertSee('Home Country');

    test()->postJson(route('checkin.store', $objListing->lst_uuid), [
        'visitorName' => 'Ana Reyes', 'visitorContact' => '09171234567', 'visitType' => 'Overnight',
        'remarks' => 'Honeymoon', 'male' => 1, 'female' => 1, 'adults' => 2, 'local' => 2,
    ])->assertOk();

    $objArrival = $objListing->arrivals()->sole();
    expect($objArrival->arr_visit_type)->toBe('Overnight');
    expect($objArrival->arr_remarks)->toBe('Honeymoon');
});
