<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — monthly arrival reports.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\MonthlyReportStatus;
use App\Enums\ReportSubmissionSource;
use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\MonthlyArrivalReport;
use App\Models\MunicipalReport;
use App\Models\OperationLog;
use App\Models\User;
use App\Support\TourismAnalytics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// makeEstablishmentListing(), makeEstablishmentUser() come from
// tests/Feature/Rbac/EstablishmentScopingTest.php; makeMunicipality(),
// makeLguUser() come from tests/Feature/Rbac/MunicipalityScopingTest.php —
// Pest merges every test file's top-level functions into one global
// namespace, so they're reused here rather than redefined.

test('an establishment can digitally submit a month, aggregating its recorded arrivals', function () {
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Botanika Resort');
    $user = makeEstablishmentUser($listing);

    $listing->arrivals()->create([
        'arr_source' => 'staff', 'arr_date' => '2026-09-05', 'arr_visit_type' => 'Daytour',
        'arr_party_male' => 2, 'arr_party_female' => 1, 'arr_party_adults' => 3, 'arr_party_children' => 0,
        'arr_party_seniors' => 0, 'arr_party_local' => 3, 'arr_party_foreign' => 0, 'arr_party_size' => 3, 'arr_status' => 'Recorded',
    ]);
    $listing->arrivals()->create([
        'arr_source' => 'self_checkin', 'arr_date' => '2026-09-20', 'arr_visit_type' => 'Overnight',
        'arr_party_male' => 1, 'arr_party_female' => 1, 'arr_party_adults' => 1, 'arr_party_children' => 1,
        'arr_party_seniors' => 0, 'arr_party_local' => 1, 'arr_party_foreign' => 1, 'arr_party_size' => 2, 'arr_status' => 'Recorded',
    ]);

    test()->actingAs($user)
        ->post(route('establishment.arrivals.monthly.draft'), ['period_month' => '2026-09'])
        ->assertRedirect();

    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();

    expect($report->mar_submission_source)->toBe(ReportSubmissionSource::Digital);
    expect($report->mar_status)->toBe(MonthlyReportStatus::Draft);
    expect($report->mar_party_male)->toBe(3);
    expect($report->mar_party_female)->toBe(2);
    expect($report->mar_total_visitors)->toBe(5);
    expect($report->mar_submitted_by)->toBeNull();
    expect($report->mar_submitted_at)->toBeNull();
    expect($listing->arrivals()->whereNull('mar_id')->count())->toBe(0);
    expect($listing->arrivals()->where('mar_id', $report->mar_id)->count())->toBe(2);

    test()->actingAs($user)
        ->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])
        ->assertRedirect(route('establishment.arrivals.monthly', ['year' => 2026, 'tab' => 'records']));

    $report->refresh();
    expect($report->mar_status)->toBe(MonthlyReportStatus::Submitted);
    expect($report->mar_submitted_by)->toBe($user->usr_id);
    expect($report->mar_submitted_at)->not->toBeNull();
    expect($report->mar_total_visitors)->toBe(5);
    expect(OperationLog::where('opl_entity_type', 'monthly_arrival_report')->where('opl_entity_id', $report->mar_id)->where('opl_action', 'submit')->value('lst_id'))->toBe($listing->lst_id);
});

test('a month with no recorded arrivals still submits as a zero-arrival report', function () {
    $listing = makeEstablishmentListing('Baganga', 'BAG', 'Coastal Lodge');
    $user = makeEstablishmentUser($listing);

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.draft'), ['period_month' => '2026-09'])->assertRedirect();
    test()->actingAs($user)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])->assertRedirect();

    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();
    expect($report->mar_total_visitors)->toBe(0);
    expect($report->mar_status)->toBe(MonthlyReportStatus::Submitted);
});

test('an establishment cannot submit the same month twice', function () {
    $listing = makeEstablishmentListing('Lupon', 'LUP', 'ABC Resort');
    $user = makeEstablishmentUser($listing);

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.draft'), ['period_month' => '2026-09'])->assertRedirect();
    test()->actingAs($user)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])->assertRedirect();
    $submittedAt = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole()->mar_submitted_at;

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])->assertRedirect();
    test()->actingAs($user)->post(route('establishment.arrivals.monthly.draft'), ['period_month' => '2026-09'])->assertRedirect();

    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();
    expect($report->mar_status)->toBe(MonthlyReportStatus::Submitted);
    expect($report->mar_submitted_at->equalTo($submittedAt))->toBeTrue();
});

test('LGU can encode a manual/paper report for an establishment in its own municipality', function () {
    $listing = makeEstablishmentListing('Governor Generoso', 'GGE', 'XYZ Hotel');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);

    test()->actingAs($lgu)->post(route('lgu.monthlyReports.manualEntry.store', $listing), [
        'period_month' => '2026-09',
        'party_male' => 100, 'party_female' => 130, 'party_adults' => 200,
        'party_children' => 20, 'party_seniors' => 10, 'party_local' => 220, 'party_foreign' => 10,
    ])->assertRedirect();

    // Saved as a Draft first — nothing reaches the review queue yet.
    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();
    expect($report->mar_submission_source)->toBe(ReportSubmissionSource::ManualPaper);
    expect($report->mar_status)->toBe(MonthlyReportStatus::Draft);
    expect($report->mar_total_visitors)->toBe(230);
    expect($report->mar_submitted_by)->toBeNull();
    expect($report->mar_submitted_at)->toBeNull();

    // Submitting puts it into the same review queue as a digital report,
    // recording the encoding LGU user and time.
    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.submit', $report))->assertRedirect();
    $report->refresh();
    expect($report->mar_status)->toBe(MonthlyReportStatus::Submitted);
    expect($report->mar_submitted_by)->toBe($lgu->usr_id);
    expect($report->mar_submitted_at)->not->toBeNull();
    expect($listing->arrivals()->count())->toBe(0);
});

test('LGU cannot encode a manual/paper report for an establishment outside its municipality', function () {
    $listing = makeEstablishmentListing('Cateel', 'CAT', 'Dahican Inn');
    $otherMunicipality = makeMunicipality('City of Mati', 'MATI2');
    $lgu = makeLguUser($otherMunicipality);

    test()->actingAs($lgu)->post(route('lgu.monthlyReports.manualEntry.store', $listing), [
        'period_month' => '2026-09',
        'party_male' => 1, 'party_female' => 1, 'party_adults' => 1,
        'party_children' => 0, 'party_seniors' => 0, 'party_local' => 2, 'party_foreign' => 0,
    ])->assertForbidden();
});

test('LGU can verify a For Review report exactly once', function () {
    $listing = makeEstablishmentListing('Boston', 'BOS', 'Pujada View Inn');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);
    $establishmentUser = makeEstablishmentUser($listing);

    $report = MonthlyArrivalReport::query()->create([
        'lst_id' => $listing->lst_id, 'mun_id' => $municipality->mun_id,
        'mar_period_month' => '2026-09-01', 'mar_submission_source' => ReportSubmissionSource::Digital,
        'mar_status' => MonthlyReportStatus::ForReview, 'mar_total_visitors' => 10,
        'mar_submitted_by' => $establishmentUser->usr_id, 'mar_submitted_at' => now(),
    ]);

    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.verify', $report))->assertRedirect();
    expect($report->fresh()->mar_status)->toBe(MonthlyReportStatus::Verified);
    expect($report->fresh()->mar_verified_by)->toBe($lgu->usr_id);

    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.verify', $report))->assertForbidden();
});

test('LGU index page shows Not Submitted for establishments with no report that period, without persisting a row', function () {
    $listing = makeEstablishmentListing('Tarragona', 'TAR', 'Sunrise Villas');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);

    $response = test()->actingAs($lgu)->get(route('lgu.monthlyReports.index', ['period' => '2026-09']));

    $response->assertOk();
    $response->assertSee('Not Submitted');
    expect(MonthlyArrivalReport::query()->count())->toBe(0);
});

test('LGU consolidate only counts Verified reports and computes the total automatically', function () {
    $listingA = makeEstablishmentListing('Manay', 'MAN', 'ABC Resort');
    $municipality = $listingA->municipalityRecord;
    $listingB = DB::table('tbl_listings')->insertGetId([
        'lst_slug' => 'xyz-hotel-'.Str::random(6), 'lst_name' => 'XYZ Hotel', 'lst_category' => 'accommodation', 'cat_id' => qrEnabledCategoryFixture()->cat_id,
        'lst_municipality' => $municipality->mun_name, 'mun_id' => $municipality->mun_id,
        'lst_barangay' => 'Poblacion', 'lst_status' => 'PUBLISHED', 'lst_created_at' => now(), 'lst_updated_at' => now(),
    ]);
    $lgu = makeLguUser($municipality);
    $establishmentUser = makeEstablishmentUser($listingA);

    $verified = MonthlyArrivalReport::query()->create([
        'lst_id' => $listingA->lst_id, 'mun_id' => $municipality->mun_id,
        'mar_period_month' => '2026-09-01', 'mar_submission_source' => ReportSubmissionSource::Digital,
        'mar_status' => MonthlyReportStatus::Verified, 'mar_total_visitors' => 150,
        'mar_submitted_by' => $establishmentUser->usr_id, 'mar_submitted_at' => now(),
        'mar_verified_by' => $lgu->usr_id, 'mar_verified_at' => now(),
    ]);
    $forReview = MonthlyArrivalReport::query()->create([
        'lst_id' => $listingB, 'mun_id' => $municipality->mun_id,
        'mar_period_month' => '2026-09-01', 'mar_submission_source' => ReportSubmissionSource::ManualPaper,
        'mar_status' => MonthlyReportStatus::ForReview, 'mar_total_visitors' => 230,
        'mar_submitted_by' => $lgu->usr_id, 'mar_submitted_at' => now(),
    ]);

    // A report still awaiting review blocks Submit to PTO.
    test()->actingAs($lgu)->post(route('lgu.monthlyReports.consolidate'), ['period_month' => '2026-09'])
        ->assertRedirect()
        ->assertSessionHas('toast_tone', 'danger');
    expect(MunicipalReport::query()->count())->toBe(0);

    // Once it is decided (here: still an unsubmitted establishment draft,
    // i.e. Not Submitted), only the Verified report counts.
    $forReview->update(['mar_status' => MonthlyReportStatus::Draft, 'mar_submission_source' => ReportSubmissionSource::Digital]);

    test()->actingAs($lgu)->post(route('lgu.monthlyReports.consolidate'), ['period_month' => '2026-09'])
        ->assertRedirect(route('lgu.monthlyReports.municipal.show', '2026-09'));

    $municipalReport = MunicipalReport::query()->where('mun_id', $municipality->mun_id)->sole();
    expect($municipalReport->mrp_total_arrivals)->toBe(150);
    expect($municipalReport->mrp_status)->toBe(MunicipalReport::STATUS_SUBMITTED);
    expect($verified->fresh()->mrp_id)->toBe($municipalReport->mrp_id);
    expect($forReview->fresh()->mrp_id)->toBeNull();
});

test('LGU cannot consolidate a month with no verified reports', function () {
    $listing = makeEstablishmentListing('San Isidro', 'SAN', 'Palm Grove Inn');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);

    test()->actingAs($lgu)
        ->post(route('lgu.monthlyReports.consolidate'), ['period_month' => '2026-09'])
        ->assertRedirect();

    expect(MunicipalReport::query()->count())->toBe(0);
});

test('non-LGU, non-PTO users cannot reach monthly report routes', function () {
    $listing = makeEstablishmentListing('Caraga', 'CAR', 'Riverside Resort');
    $user = makeEstablishmentUser($listing);

    test()->actingAs($user)->get(route('lgu.monthlyReports.index'))->assertForbidden();
});

test('PTO sees every municipality unrestricted', function () {
    $listing = makeEstablishmentListing('Banaybanay', 'BAN', 'Seaview Hotel');
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    MonthlyArrivalReport::query()->create([
        'lst_id' => $listing->lst_id, 'mun_id' => $listing->mun_id,
        'mar_period_month' => '2026-09-01', 'mar_submission_source' => ReportSubmissionSource::Digital,
        'mar_status' => MonthlyReportStatus::Verified, 'mar_total_visitors' => 5,
        'mar_submitted_by' => $pto->usr_id, 'mar_submitted_at' => now(),
    ]);

    expect(MonthlyArrivalReport::query()->visibleTo($pto)->count())->toBe(1);
});

test('PTO municipal report show page renders the consolidated-from drill-down table', function () {
    $listing = makeEstablishmentListing('Tarragona', 'TRG', 'Sunrise Villas');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);
    $establishmentUser = makeEstablishmentUser($listing);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    $monthlyReport = MonthlyArrivalReport::query()->create([
        'lst_id' => $listing->lst_id, 'mun_id' => $municipality->mun_id,
        'mar_period_month' => '2026-09-01', 'mar_submission_source' => ReportSubmissionSource::Digital,
        'mar_status' => MonthlyReportStatus::Verified, 'mar_total_visitors' => 42,
        'mar_submitted_by' => $establishmentUser->usr_id, 'mar_submitted_at' => now(),
        'mar_verified_by' => $lgu->usr_id, 'mar_verified_at' => now(),
    ]);

    $municipalReport = MunicipalReport::query()->create([
        'mrp_municipality' => $municipality->mun_name, 'mun_id' => $municipality->mun_id,
        'mrp_submitted_by' => $lgu->usr_id, 'mrp_period_start' => '2026-09-01', 'mrp_period_end' => '2026-09-30',
        'mrp_total_arrivals' => 42, 'mrp_status' => MunicipalReport::STATUS_SUBMITTED,
    ]);
    $monthlyReport->update(['mrp_id' => $municipalReport->mrp_id]);

    $response = test()->actingAs($pto)->get(route('pto.municipalReports.show', $municipalReport));

    $response->assertOk();
    $response->assertSee('Breakdown by Establishment');
    $response->assertSee($listing->lst_name);
    $response->assertSee('42');
});

test('LGU Tourism Reports page shows the workflow steps, KPI cards, and sorts Not Submitted rows first', function () {
    $verified = makeEstablishmentListing('Boston', 'BOS2', 'ABC Resort');
    $municipality = $verified->municipalityRecord;
    $notSubmitted = Listing::query()->create([
        'lst_slug' => 'xyz-hotel-'.Str::random(6), 'lst_name' => 'XYZ Hotel', 'lst_category' => 'accommodation', 'cat_id' => qrEnabledCategoryFixture()->cat_id,
        'lst_municipality' => $municipality->mun_name, 'mun_id' => $municipality->mun_id,
        'lst_barangay' => 'Poblacion', 'lst_status' => 'PUBLISHED',
    ]);
    $lgu = makeLguUser($municipality);
    $establishmentUser = makeEstablishmentUser($verified);

    MonthlyArrivalReport::query()->create([
        'lst_id' => $verified->lst_id, 'mun_id' => $municipality->mun_id,
        'mar_period_month' => '2026-09-01', 'mar_submission_source' => ReportSubmissionSource::Digital,
        'mar_status' => MonthlyReportStatus::Verified, 'mar_total_visitors' => 10,
        'mar_submitted_by' => $establishmentUser->usr_id, 'mar_submitted_at' => now(),
        'mar_verified_by' => $lgu->usr_id, 'mar_verified_at' => now(),
    ]);

    $response = test()->actingAs($lgu)->get(route('lgu.monthlyReports.index', ['period' => '2026-09']));

    $response->assertOk();
    $response->assertSee('Collect reports');
    $response->assertSee('Review & verify');
    $response->assertSee('Consolidate');
    $response->assertSee('Submit to PTO');
    $response->assertSee('Total Establishments');
    $response->assertSee('Manual Entry');
    $response->assertSee('Establishment Reports');
    $response->assertSee('Municipal Reports');

    $content = $response->getContent();
    $notSubmittedPos = strpos($content, $notSubmitted->lst_name);
    $verifiedPos = strpos($content, $verified->lst_name);
    expect($notSubmittedPos)->not->toBeFalse();
    expect($verifiedPos)->not->toBeFalse();
    expect($notSubmittedPos)->toBeLessThan($verifiedPos);
});

test('PTO Provincial Reports is one workspace with Overview, Monthly Records, and Statistics tabs', function () {
    $listing = makeEstablishmentListing('Caraga', 'CAR2', 'Riverside Resort');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    $monthlyReport = makeVerifiedMonthlyReport($listing, $lgu, 70);
    $municipalReport = MunicipalReport::query()->create([
        'mrp_municipality' => $municipality->mun_name, 'mun_id' => $municipality->mun_id,
        'mrp_submitted_by' => $lgu->usr_id, 'mrp_period_start' => '2026-09-01', 'mrp_period_end' => '2026-09-30',
        'mrp_total_arrivals' => 70, 'mrp_status' => MunicipalReport::STATUS_SUBMITTED,
    ]);
    $monthlyReport->update(['mrp_id' => $municipalReport->mrp_id]);

    $response = test()->actingAs($pto)->get(route('pto.monthlyReports.index', ['year' => 2026]));

    $response->assertOk()
        ->assertSee('Reporting Year')
        ->assertSee('data-tab-target="records"', false)
        ->assertSee('data-tab-target="statistics"', false)
        ->assertSee('2026 Provincial Reporting Overview')
        ->assertSee('Waiting for Your Review')
        ->assertSee('Monthly Records — 2026')
        ->assertSee('For PTO Review')
        // Month modal: every LGU, the PTO's existing actions, establishments.
        ->assertSee('data-modal-open="province-month-9"', false)
        ->assertSee('Verify Report')
        ->assertSee('Return for Correction')
        ->assertSee(route('pto.municipalReports.approve', $municipalReport), false)
        ->assertSee('Report Preview')
        ->assertSee('Riverside Resort')
        ->assertDontSee('Encode Paper Report')
        ->assertDontSee('Monthly Consolidation');

    // Not yet PTO-verified: no official statistics.
    $response->assertSee('No PTO-verified LGU reports for 2026 yet');

    // Verifying through the same LGU Submissions route makes it official.
    test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $municipalReport))->assertRedirect();

    test()->actingAs($pto)->get(route('pto.monthlyReports.index', ['year' => 2026, 'tab' => 'statistics']))
        ->assertOk()
        ->assertSee('data-tab-panel="statistics" class=""', false)
        ->assertSee('LGU Reporting Coverage — 2026')
        ->assertSee('Visitor Classifications — 2026')
        ->assertSee('No comparison available');

    test()->actingAs($pto)->get(route('pto.monthlyReports.show', $monthlyReport))->assertOk()->assertDontSee('Verify');
});

test('provincial official totals count PTO-verified LGU reports only', function () {
    $listingA = makeEstablishmentListing('Boston', 'BOS6', 'Lima Resort');
    $listingB = makeEstablishmentListing('Cateel', 'CAT6', 'Mike Inn');
    $lguA = makeLguUser($listingA->municipalityRecord);
    $lguB = makeLguUser($listingB->municipalityRecord);

    foreach ([[$listingA, $lguA, 500, MunicipalReport::STATUS_APPROVED], [$listingB, $lguB, 900, MunicipalReport::STATUS_SUBMITTED]] as [$listing, $lgu, $total, $status]) {
        MunicipalReport::query()->create([
            'mrp_municipality' => $listing->lst_municipality, 'mun_id' => $listing->mun_id,
            'mrp_submitted_by' => $lgu->usr_id, 'mrp_period_start' => '2026-08-01', 'mrp_period_end' => '2026-08-31',
            'mrp_total_arrivals' => $total, 'mrp_status' => $status,
        ]);
    }

    $records = TourismAnalytics::provincialMonthlyRecords(2026)->keyBy('month');
    expect($records[8]['total'])->toBe(500);
    expect($records[8]['reportCount'])->toBe(1);
    expect($records[7]['hasData'])->toBeFalse();
});

test('establishment users cannot reach PTO monthly report routes', function () {
    $listing = makeEstablishmentListing('Lupon', 'LUP2', 'ABC Resort');
    $user = makeEstablishmentUser($listing);

    test()->actingAs($user)->get(route('pto.monthlyReports.index'))->assertForbidden();
});

test('LGU can correct a For Review report and the reason is required', function () {
    $listing = makeEstablishmentListing('Manay', 'MAN2', 'ABC Resort');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);

    $report = MonthlyArrivalReport::query()->create([
        'lst_id' => $listing->lst_id, 'mun_id' => $municipality->mun_id,
        'mar_period_month' => '2026-09-01', 'mar_submission_source' => ReportSubmissionSource::ManualPaper,
        'mar_status' => MonthlyReportStatus::ForReview, 'mar_party_male' => 10, 'mar_party_female' => 5, 'mar_total_visitors' => 15,
        'mar_submitted_by' => $lgu->usr_id, 'mar_submitted_at' => now(),
    ]);

    test()->actingAs($lgu)->put(route('lgu.monthlyReports.update', $report), [
        'party_male' => 20, 'party_female' => 5, 'party_adults' => 0, 'party_children' => 0,
        'party_seniors' => 0, 'party_local' => 0, 'party_foreign' => 0,
    ])->assertSessionHasErrors('reason');

    test()->actingAs($lgu)->put(route('lgu.monthlyReports.update', $report), [
        'party_male' => 20, 'party_female' => 5, 'party_adults' => 0, 'party_children' => 0,
        'party_seniors' => 0, 'party_local' => 0, 'party_foreign' => 0,
        'reason' => 'Miscounted male visitors on the paper report.',
    ])->assertRedirect();

    expect($report->fresh()->mar_total_visitors)->toBe(25);

    $log = OperationLog::where('opl_entity_type', 'monthly_arrival_report')->where('opl_entity_id', $report->mar_id)->where('opl_action', 'update')->first();
    expect($log)->not->toBeNull();
    expect($log->opl_reason)->toBe('Miscounted male visitors on the paper report.');
    expect($log->opl_old_values['mar_party_male'])->toBe(10);
    expect($log->opl_new_values['mar_party_male'])->toBe(20);
});

test('correcting a Verified report reverts it to For Review', function () {
    $listing = makeEstablishmentListing('Baganga', 'BAG2', 'ABC Resort');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);
    $establishmentUser = makeEstablishmentUser($listing);

    $report = MonthlyArrivalReport::query()->create([
        'lst_id' => $listing->lst_id, 'mun_id' => $municipality->mun_id,
        'mar_period_month' => '2026-09-01', 'mar_submission_source' => ReportSubmissionSource::Digital,
        'mar_status' => MonthlyReportStatus::Verified, 'mar_total_visitors' => 15,
        'mar_submitted_by' => $establishmentUser->usr_id, 'mar_submitted_at' => now(),
        'mar_verified_by' => $lgu->usr_id, 'mar_verified_at' => now(),
    ]);

    test()->actingAs($lgu)->put(route('lgu.monthlyReports.update', $report), [
        'party_male' => 1, 'party_female' => 1, 'party_adults' => 0, 'party_children' => 0,
        'party_seniors' => 0, 'party_local' => 0, 'party_foreign' => 0,
        'reason' => 'Correcting after PTO returned the consolidated report.',
    ])->assertRedirect();

    $fresh = $report->fresh();
    expect($fresh->mar_status)->toBe(MonthlyReportStatus::ForReview);
    expect($fresh->mar_verified_by)->toBeNull();
    expect($fresh->mar_verified_at)->toBeNull();
});

test('a report cannot be corrected while part of a still-pending or already-approved municipal report', function () {
    $listing = makeEstablishmentListing('Cateel', 'CAT2', 'ABC Resort');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);
    $establishmentUser = makeEstablishmentUser($listing);

    $municipalReport = MunicipalReport::query()->create([
        'mrp_municipality' => $municipality->mun_name, 'mun_id' => $municipality->mun_id,
        'mrp_submitted_by' => $lgu->usr_id, 'mrp_period_start' => '2026-09-01', 'mrp_period_end' => '2026-09-30',
        'mrp_total_arrivals' => 15, 'mrp_status' => MunicipalReport::STATUS_SUBMITTED,
    ]);
    $report = MonthlyArrivalReport::query()->create([
        'lst_id' => $listing->lst_id, 'mun_id' => $municipality->mun_id,
        'mar_period_month' => '2026-09-01', 'mar_submission_source' => ReportSubmissionSource::Digital,
        'mar_status' => MonthlyReportStatus::Verified, 'mar_total_visitors' => 15,
        'mar_submitted_by' => $establishmentUser->usr_id, 'mar_submitted_at' => now(),
        'mar_verified_by' => $lgu->usr_id, 'mar_verified_at' => now(),
        'mrp_id' => $municipalReport->mrp_id,
    ]);

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.edit', $report))->assertForbidden();
    test()->actingAs($lgu)->put(route('lgu.monthlyReports.update', $report), ['reason' => 'x'])->assertForbidden();

    $municipalReport->update(['mrp_status' => MunicipalReport::STATUS_RETURNED]);
    test()->actingAs($lgu)->get(route('lgu.monthlyReports.edit', $report->fresh()))->assertOk();

    $municipalReport->update(['mrp_status' => MunicipalReport::STATUS_APPROVED]);
    test()->actingAs($lgu)->get(route('lgu.monthlyReports.edit', $report->fresh()))->assertForbidden();
});

test('verification history shows on both the LGU and PTO report detail pages', function () {
    $listing = makeEstablishmentListing('San Isidro', 'SAN2', 'ABC Resort');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    test()->actingAs($lgu)->post(route('lgu.monthlyReports.manualEntry.store', $listing), [
        'period_month' => '2026-09',
        'party_male' => 5, 'party_female' => 5, 'party_adults' => 10,
        'party_children' => 0, 'party_seniors' => 0, 'party_local' => 10, 'party_foreign' => 0,
    ]);
    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();

    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.submit', $report));
    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.review', $report));
    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.verify', $report));

    $lguResponse = test()->actingAs($lgu)->get(route('lgu.monthlyReports.show', $report));
    $lguResponse->assertSee('Verification History');
    $lguResponse->assertSee('Create');
    $lguResponse->assertSee('Validate');

    $ptoResponse = test()->actingAs($pto)->get(route('pto.monthlyReports.show', $report));
    $ptoResponse->assertSee('Verification History');
});

test('the Logged Via column on both report detail pages correctly labels staff vs self-checkin arrivals', function () {
    $listing = makeEstablishmentListing('Caraga', 'CAR2', 'Underlying Arrivals Resort');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $user = makeEstablishmentUser($listing);

    $listing->arrivals()->create([
        'arr_source' => 'staff', 'arr_date' => '2026-09-05', 'arr_visit_type' => 'Daytour',
        'arr_party_male' => 1, 'arr_party_size' => 1, 'arr_status' => 'Recorded',
    ]);
    $listing->arrivals()->create([
        'arr_source' => 'self_checkin', 'arr_date' => '2026-09-06', 'arr_visit_type' => 'Daytour',
        'arr_party_male' => 1, 'arr_party_size' => 1, 'arr_status' => 'Recorded',
    ]);

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.draft'), ['period_month' => '2026-09'])->assertRedirect();
    test()->actingAs($user)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])->assertRedirect();
    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();

    $lguResponse = test()->actingAs($lgu)->get(route('lgu.monthlyReports.show', $report));
    $lguResponse->assertSee('Front Desk');
    $lguResponse->assertSee('QR Self Check-in');

    $ptoResponse = test()->actingAs($pto)->get(route('pto.monthlyReports.show', $report));
    $ptoResponse->assertSee('Front Desk');
    $ptoResponse->assertSee('QR Self Check-in');
});

test('the monthly report detail pages show the within/outside-province and top-country breakdown', function () {
    $listing = makeEstablishmentListing('Boston', 'BOS3', 'Origin Breakdown Resort');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $user = makeEstablishmentUser($listing);

    $listing->arrivals()->create([
        'arr_source' => 'staff', 'arr_date' => '2026-09-05', 'arr_visit_type' => 'Daytour',
        'arr_party_local' => 2, 'arr_party_size' => 2, 'arr_status' => 'Recorded',
        'arr_local_origin_scope' => 'within_province',
    ]);
    $listing->arrivals()->create([
        'arr_source' => 'self_checkin', 'arr_date' => '2026-09-06', 'arr_visit_type' => 'Daytour',
        'arr_party_local' => 3, 'arr_party_size' => 3, 'arr_status' => 'Recorded',
        'arr_local_origin_scope' => 'outside_province', 'arr_local_origin_place' => 'Davao del Sur',
    ]);
    $listing->arrivals()->create([
        'arr_source' => 'self_checkin', 'arr_date' => '2026-09-07', 'arr_visit_type' => 'Daytour',
        'arr_party_foreign' => 1, 'arr_party_size' => 1, 'arr_status' => 'Recorded',
        'arr_foreign_country' => 'Japan',
    ]);

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.draft'), ['period_month' => '2026-09'])->assertRedirect();
    test()->actingAs($user)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])->assertRedirect();
    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();

    $breakdown = $report->fresh('arrivals')->originBreakdown();
    expect($breakdown['withinProvince'])->toBe(2);
    expect($breakdown['outsideProvince'])->toBe(3);
    expect($breakdown['topOriginPlaces']->get('Davao del Sur'))->toBe(3);
    expect($breakdown['topForeignCountries']->get('Japan'))->toBe(1);

    $lguResponse = test()->actingAs($lgu)->get(route('lgu.monthlyReports.show', $report));
    $lguResponse->assertSee('Local Guest Origin');
    $lguResponse->assertSee('Davao del Sur');
    $lguResponse->assertSee('Japan');

    $ptoResponse = test()->actingAs($pto)->get(route('pto.monthlyReports.show', $report));
    $ptoResponse->assertSee('Local Guest Origin');
    $ptoResponse->assertSee('Davao del Sur');
    $ptoResponse->assertSee('Japan');
});

// --- Draft -> Submit -> For Correction workflow (CLAUDE.md 2.5) ---

test('submitting without a saved draft creates no report', function () {
    $listing = makeEstablishmentListing('Mati', 'MAT3', 'No Draft Resort');
    $user = makeEstablishmentUser($listing);

    test()->actingAs($user)
        ->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])
        ->assertRedirect()
        ->assertSessionHas('toast_tone', 'danger');

    expect(MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->count())->toBe(0);
});

test('an establishment draft is visible only to its owner — LGU and PTO see it as Not Submitted', function () {
    $listing = makeEstablishmentListing('Lupon', 'LUP3', 'Private Draft Resort');
    $municipality = $listing->municipalityRecord;
    $user = makeEstablishmentUser($listing);
    $lgu = makeLguUser($municipality);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.draft'), ['period_month' => '2026-09']);
    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();

    test()->actingAs($user)->get(route('establishment.arrivals.monthly.show', $report))
        ->assertOk()
        ->assertSee('Submit to LGU');

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.show', $report))->assertForbidden();
    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.verify', $report))->assertForbidden();
    test()->actingAs($lgu)->get(route('lgu.monthlyReports.index', ['period' => '2026-09']))
        ->assertOk()
        ->assertSee('Not Submitted')
        ->assertSee('Draft in progress');

    test()->actingAs($pto)->get(route('pto.monthlyReports.show', $report))->assertForbidden();
    test()->actingAs($pto)->get(route('pto.monthlyReports.index', ['year' => 2026]))
        ->assertOk()
        ->assertDontSee('Private Draft Resort');
});

test('a draft cannot be submitted when arrivals were recorded after it was saved', function () {
    $listing = makeEstablishmentListing('Banaybanay', 'BAN3', 'Stale Draft Resort');
    $user = makeEstablishmentUser($listing);

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.draft'), ['period_month' => '2026-09']);
    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();

    $listing->arrivals()->create([
        'arr_source' => 'staff', 'arr_date' => '2026-09-10', 'arr_visit_type' => 'Daytour',
        'arr_party_male' => 1, 'arr_party_size' => 1, 'arr_status' => 'Recorded',
    ]);

    test()->actingAs($user)
        ->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])
        ->assertRedirect(route('establishment.arrivals.monthly.show', $report))
        ->assertSessionHas('toast_tone', 'danger');
    expect($report->fresh()->mar_status)->toBe(MonthlyReportStatus::Draft);

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.draft'), ['period_month' => '2026-09']);
    expect($report->fresh()->mar_total_visitors)->toBe(1);

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09']);
    expect($report->fresh()->mar_status)->toBe(MonthlyReportStatus::Submitted);
});

test('LGU can return a submitted report for correction with required remarks, and the establishment resubmits it', function () {
    $listing = makeEstablishmentListing('Caraga', 'CAR3', 'Returned Report Resort');
    $municipality = $listing->municipalityRecord;
    $user = makeEstablishmentUser($listing);
    $lgu = makeLguUser($municipality);

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.draft'), ['period_month' => '2026-09']);
    test()->actingAs($user)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09']);
    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();

    // A just-Submitted report must be opened (Review) before a decision.
    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.return', $report), ['remarks' => 'x'])->assertForbidden();
    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.review', $report))->assertRedirect(route('lgu.monthlyReports.show', $report));

    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.return', $report), ['remarks' => ''])
        ->assertSessionHasErrors('remarks');
    expect($report->fresh()->mar_status)->toBe(MonthlyReportStatus::ForReview);

    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.return', $report), ['remarks' => 'Missing the Sept 12 tour group.'])
        ->assertRedirect();

    $report->refresh();
    expect($report->mar_status)->toBe(MonthlyReportStatus::ForCorrection);
    expect($report->mar_remarks)->toBe('Missing the Sept 12 tour group.');

    $log = OperationLog::where('opl_entity_type', 'monthly_arrival_report')->where('opl_entity_id', $report->mar_id)->where('opl_action', 'return')->sole();
    expect($log->usr_id)->toBe($lgu->usr_id);
    expect($log->opl_reason)->toBe('Missing the Sept 12 tour group.');
    expect($log->lst_id)->toBe($listing->lst_id);

    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.verify', $report))->assertForbidden();
    test()->actingAs($lgu)->get(route('lgu.monthlyReports.edit', $report))->assertForbidden();

    test()->actingAs($user)->get(route('establishment.arrivals.monthly'))->assertSee('Missing the Sept 12 tour group.');

    $listing->arrivals()->create([
        'arr_source' => 'staff', 'arr_date' => '2026-09-12', 'arr_visit_type' => 'Daytour',
        'arr_party_male' => 4, 'arr_party_female' => 4, 'arr_party_size' => 8, 'arr_status' => 'Recorded',
    ]);

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.draft'), ['period_month' => '2026-09']);
    expect($report->fresh()->mar_status)->toBe(MonthlyReportStatus::ForCorrection);
    expect($report->fresh()->mar_total_visitors)->toBe(8);

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09']);
    expect($report->fresh()->mar_status)->toBe(MonthlyReportStatus::Submitted);

    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.review', $report));
    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.verify', $report))->assertRedirect();
    expect($report->fresh()->mar_status)->toBe(MonthlyReportStatus::Verified);
});

test('a Manual/Paper report cannot be returned for correction, and another municipality cannot act on it', function () {
    $listing = makeEstablishmentListing('Manay', 'MAN3', 'Paper Only Inn');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);
    $otherLgu = makeLguUser(makeMunicipality('Tarragona', 'TAR3'));

    $report = MonthlyArrivalReport::query()->create([
        'lst_id' => $listing->lst_id, 'mun_id' => $municipality->mun_id,
        'mar_period_month' => '2026-09-01', 'mar_submission_source' => ReportSubmissionSource::ManualPaper,
        'mar_status' => MonthlyReportStatus::ForReview, 'mar_total_visitors' => 10,
        'mar_submitted_by' => $lgu->usr_id, 'mar_submitted_at' => now(),
    ]);

    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.return', $report), ['remarks' => 'x'])->assertForbidden();
    test()->actingAs($otherLgu)->patch(route('lgu.monthlyReports.verify', $report))->assertForbidden();
    test()->actingAs($otherLgu)->patch(route('lgu.monthlyReports.submit', $report))->assertForbidden();
    expect($report->fresh()->mar_status)->toBe(MonthlyReportStatus::ForReview);
});

test('a paper report enters the same Review -> Verify path as a digital one, and is corrected directly by the LGU', function () {
    $listing = makeEstablishmentListing('Baganga', 'BAG3', 'Paper Path Lodge');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);

    $figures = ['party_adults' => 10, 'party_children' => 0, 'party_seniors' => 0, 'party_local' => 10, 'party_foreign' => 0];

    test()->actingAs($lgu)->post(route('lgu.monthlyReports.manualEntry.store', $listing), [
        'period_month' => '2026-09', 'party_male' => 5, 'party_female' => 5, ...$figures,
    ])->assertRedirect();
    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();

    expect($report->mar_status)->toBe(MonthlyReportStatus::Draft);
    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.submit', $report))->assertRedirect();
    expect($report->fresh()->mar_status)->toBe(MonthlyReportStatus::Submitted);
    test()->actingAs($lgu)->get(route('lgu.monthlyReports.manualEntry', ['listing' => $listing, 'period' => '2026-09']))->assertStatus(422);
    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.verify', $report))->assertForbidden();

    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.review', $report))->assertRedirect();
    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.return', $report), ['remarks' => 'x'])->assertForbidden();

    test()->actingAs($lgu)->put(route('lgu.monthlyReports.update', $report), [
        'party_male' => 6, 'party_female' => 5, 'party_adults' => 11, 'party_children' => 0,
        'party_seniors' => 0, 'party_local' => 11, 'party_foreign' => 0,
        'reason' => 'Mis-encoded male count from the paper form.',
    ])->assertRedirect();
    expect($report->fresh()->mar_total_visitors)->toBe(11);

    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.verify', $report))->assertRedirect();
    expect($report->fresh()->mar_status)->toBe(MonthlyReportStatus::Verified);
});

// --- LGU Monthly Reports: Establishment Reports | Municipal Reports ---

/**
 * A Verified, column-balanced establishment report for September 2026.
 */
function makeVerifiedMonthlyReport(Listing $listing, User $lgu, int $total): MonthlyArrivalReport
{
    return MonthlyArrivalReport::query()->create([
        'lst_id' => $listing->lst_id, 'mun_id' => $listing->mun_id,
        'mar_period_month' => '2026-09-01', 'mar_submission_source' => ReportSubmissionSource::ManualPaper,
        'mar_status' => MonthlyReportStatus::Verified,
        'mar_party_male' => $total, 'mar_party_female' => 0, 'mar_party_adults' => $total, 'mar_party_children' => 0,
        'mar_party_seniors' => 0, 'mar_party_local' => $total, 'mar_party_foreign' => 0, 'mar_total_visitors' => $total,
        'mar_submitted_by' => $lgu->usr_id, 'mar_submitted_at' => now(), 'mar_verified_by' => $lgu->usr_id, 'mar_verified_at' => now(),
    ]);
}

test('Review moves a Submitted report to For Review and is logged; it cannot be verified before that', function () {
    $listing = makeEstablishmentListing('Mati', 'MAT4', 'Review Step Resort');
    $lgu = makeLguUser($listing->municipalityRecord);
    $otherLgu = makeLguUser(makeMunicipality('Cateel', 'CAT4'));
    $user = makeEstablishmentUser($listing);

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.draft'), ['period_month' => '2026-09']);
    test()->actingAs($user)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09']);
    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.index', ['period' => '2026-09']))->assertSee('Submitted');
    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.verify', $report))->assertForbidden();
    test()->actingAs($otherLgu)->patch(route('lgu.monthlyReports.review', $report))->assertForbidden();

    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.review', $report))->assertRedirect(route('lgu.monthlyReports.show', $report));
    expect($report->fresh()->mar_status)->toBe(MonthlyReportStatus::ForReview);
    expect(OperationLog::where('opl_entity_type', 'monthly_arrival_report')->where('opl_entity_id', $report->mar_id)->where('opl_action', 'update')->exists())->toBeTrue();

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.show', $report))
        ->assertOk()
        ->assertSee('Verify Report')
        ->assertSee('Return for Correction')
        ->assertSee('Report Preview');

    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.review', $report))->assertForbidden();
});

test('the establishment report A4 preview renders for its own LGU only', function () {
    $listing = makeEstablishmentListing('Lupon', 'LUP4', 'A4 Preview Resort');
    $lgu = makeLguUser($listing->municipalityRecord);
    $otherLgu = makeLguUser(makeMunicipality('Boston', 'BOS4'));
    $report = makeVerifiedMonthlyReport($listing, $lgu, 42);

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.show', [$report, 'view' => 'a4']))
        ->assertOk()
        ->assertSee(route('lgu.monthlyReports.preview', $report), false);

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.preview', $report))
        ->assertOk()
        ->assertSee('Monthly Tourist Arrival Report')
        ->assertSee('A4 Preview Resort')
        ->assertSee('Manual / Paper')
        ->assertSee(sprintf('MAR-%06d', $report->mar_id));

    test()->actingAs($otherLgu)->get(route('lgu.monthlyReports.preview', $report))->assertForbidden();
});

test('the municipal report is generated from Verified reports only, and a missing report stays Not Submitted', function () {
    $verifiedA = makeEstablishmentListing('Manay', 'MAN4', 'Alpha Resort');
    $municipality = $verifiedA->municipalityRecord;
    $verifiedB = makeEstablishmentListing('Manay', 'MAN4', 'Bravo Inn');
    makeEstablishmentListing('Manay', 'MAN4', 'Charlie Lodge');
    $lgu = makeLguUser($municipality);

    makeVerifiedMonthlyReport($verifiedA, $lgu, 1250);
    makeVerifiedMonthlyReport($verifiedB, $lgu, 980);

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.municipal', ['year' => 2026, 'section' => 'records']))
        ->assertOk()
        ->assertSee('September 2026')
        ->assertSee('Ready for Submission')
        ->assertSee('2,230');

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.municipal.show', '2026-09'))
        ->assertOk()
        ->assertSee('Municipal Total (verified reports only)')
        ->assertSee('2,230')
        ->assertSee('No — Not Submitted')
        ->assertSee('Submit to PTO');

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.municipal.preview', '2026-09'))
        ->assertOk()
        ->assertSee('LGU Consolidated Tourism Report')
        ->assertSee('Not yet submitted')
        ->assertSee('2 of 3 establishments reported (0 digital, 2 paper)')
        ->assertSee('Charlie Lodge');

    expect(MunicipalReport::query()->count())->toBe(0);
});

test('Submit to PTO saves the municipal report, PTO receives it, and the LGU can no longer resubmit it', function () {
    $listing = makeEstablishmentListing('Tarragona', 'TAR4', 'Delta Resort');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    makeVerifiedMonthlyReport($listing, $lgu, 300);

    test()->actingAs($lgu)->post(route('lgu.monthlyReports.consolidate'), ['period_month' => '2026-09'])
        ->assertRedirect(route('lgu.monthlyReports.municipal.show', '2026-09'));

    $municipalReport = MunicipalReport::query()->where('mun_id', $municipality->mun_id)->sole();
    expect($municipalReport->mrp_status)->toBe(MunicipalReport::STATUS_SUBMITTED);
    expect($municipalReport->mrp_total_arrivals)->toBe(300);

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.municipal.show', '2026-09'))
        ->assertOk()
        ->assertSee('Submitted to PTO')
        ->assertDontSee('Resubmit to PTO');
    test()->actingAs($lgu)->get(route('lgu.monthlyReports.municipal.preview', '2026-09'))
        ->assertOk()
        ->assertSee(sprintf('MRP-%06d', $municipalReport->mrp_id));

    test()->actingAs($pto)->get(route('pto.municipalReports.index', ['year' => 2026, 'month' => 9]))
        ->assertOk()
        ->assertSee($municipality->mun_name)
        ->assertSee('For Review');

    test()->actingAs($lgu)->post(route('lgu.monthlyReports.consolidate'), ['period_month' => '2026-09'])->assertForbidden();
});

test('a municipal report PTO returns shows its remarks and can be resubmitted', function () {
    $listing = makeEstablishmentListing('Caraga', 'CAR4', 'Echo Resort');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    makeVerifiedMonthlyReport($listing, $lgu, 50);

    test()->actingAs($lgu)->post(route('lgu.monthlyReports.consolidate'), ['period_month' => '2026-09']);
    $municipalReport = MunicipalReport::query()->where('mun_id', $municipality->mun_id)->sole();

    test()->actingAs($pto)->patch(route('pto.municipalReports.return', $municipalReport), ['remarks' => 'Echo Resort total looks low.'])->assertRedirect();

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.municipal.show', '2026-09'))
        ->assertOk()
        ->assertSee('Returned by PTO')
        ->assertSee('Echo Resort total looks low.')
        ->assertSee('Resubmit to PTO');

    test()->actingAs($lgu)->post(route('lgu.monthlyReports.consolidate'), ['period_month' => '2026-09'])->assertRedirect();
    expect($municipalReport->fresh()->mrp_status)->toBe(MunicipalReport::STATUS_SUBMITTED);
});

test('LGU municipal report pages are LGU-only', function () {
    $listing = makeEstablishmentListing('Baganga', 'BAG4', 'Foxtrot Inn');
    $user = makeEstablishmentUser($listing);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    test()->actingAs($user)->get(route('lgu.monthlyReports.municipal'))->assertForbidden();
    test()->actingAs($pto)->get(route('lgu.monthlyReports.municipal.show', '2026-09'))->assertForbidden();
});

// --- Reporting records, overviews, and analytics ---

/**
 * A Verified establishment report for any month (column-balanced).
 */
function makeVerifiedReportFor(Listing $listing, User $lgu, string $month, int $total): MonthlyArrivalReport
{
    return MonthlyArrivalReport::query()->create([
        'lst_id' => $listing->lst_id, 'mun_id' => $listing->mun_id,
        'mar_period_month' => $month, 'mar_submission_source' => ReportSubmissionSource::Digital,
        'mar_status' => MonthlyReportStatus::Verified,
        'mar_party_male' => $total, 'mar_party_female' => 0, 'mar_party_adults' => $total, 'mar_party_children' => 0,
        'mar_party_seniors' => 0, 'mar_party_local' => $total, 'mar_party_foreign' => 0, 'mar_total_visitors' => $total,
        'mar_submitted_by' => $lgu->usr_id, 'mar_submitted_at' => now(), 'mar_verified_by' => $lgu->usr_id, 'mar_verified_at' => now(),
    ]);
}

test('monthly records count verified reports only, mark missing months, and label month-to-month change', function () {
    $listing = makeEstablishmentListing('Mati', 'MAT5', 'Trend Resort');
    $lgu = makeLguUser($listing->municipalityRecord);

    makeVerifiedReportFor($listing, $lgu, '2026-01-01', 100);
    makeVerifiedReportFor($listing, $lgu, '2026-02-01', 150);
    makeVerifiedReportFor($listing, $lgu, '2026-04-01', 150);
    MonthlyArrivalReport::query()->create([
        'lst_id' => $listing->lst_id, 'mun_id' => $listing->mun_id,
        'mar_period_month' => '2026-05-01', 'mar_submission_source' => ReportSubmissionSource::Digital,
        'mar_status' => MonthlyReportStatus::Submitted, 'mar_total_visitors' => 999,
        'mar_submitted_by' => $lgu->usr_id, 'mar_submitted_at' => now(),
    ]);

    $records = TourismAnalytics::monthlyRecords([
        'year' => 2026, 'month' => null, 'municipalityId' => null, 'listingId' => $listing->lst_id, 'classification' => null,
    ])->keyBy('month');

    expect($records[1]['change'])->toBe('No Comparison Available');
    expect($records[2]['change'])->toBe('Increased');
    expect($records[2]['changePercent'])->toBe(50.0);
    expect($records[3]['hasData'])->toBeFalse();
    expect($records[4]['change'])->toBe('No Comparison Available');
    expect($records[5]['hasData'])->toBeFalse();

    $summary = TourismAnalytics::yearSummary($records->values());
    expect($summary['total'])->toBe(400);
    expect($summary['monthsWithData'])->toBe(3);
    expect($summary['highest']['total'])->toBe(150);
    expect($summary['lowest']['label'])->toBe('January 2026');

    $quarters = TourismAnalytics::quarterlySummary($records->values());
    expect($quarters[0]['total'])->toBe(250);
    expect($quarters[0]['monthsWithData'])->toBe(2);
    expect($quarters[1]['total'])->toBe(150);
});

test('year comparison compares the same months only, and is unavailable without previous-year data', function () {
    $listing = makeEstablishmentListing('Mati', 'MAT6', 'Compare Resort');
    $lgu = makeLguUser($listing->municipalityRecord);
    $filters = ['month' => null, 'municipalityId' => null, 'listingId' => $listing->lst_id, 'classification' => null];

    makeVerifiedReportFor($listing, $lgu, '2026-01-01', 120);
    $current = TourismAnalytics::monthlyRecords([...$filters, 'year' => 2026]);
    $previous = TourismAnalytics::monthlyRecords([...$filters, 'year' => 2025]);
    expect(TourismAnalytics::yearComparison($current, $previous, 2026)['label'])->toBe('No Comparison Available');

    makeVerifiedReportFor($listing, $lgu, '2025-01-01', 100);
    makeVerifiedReportFor($listing, $lgu, '2025-06-01', 5000);
    $previous = TourismAnalytics::monthlyRecords([...$filters, 'year' => 2025]);
    $comparison = TourismAnalytics::yearComparison($current, $previous, 2026);

    expect($comparison['label'])->toBe('Increased');
    expect($comparison['previous'])->toBe(100);
    expect($comparison['percent'])->toBe(20.0);
});

test('the establishment Monthly Reports workspace has one year selector and Overview, Monthly Records, and Statistics tabs', function () {
    $listing = makeEstablishmentListing('Lupon', 'LUP5', 'Workspace Resort');
    $user = makeEstablishmentUser($listing);
    $lgu = makeLguUser($listing->municipalityRecord);

    makeVerifiedReportFor($listing, $lgu, '2026-07-01', 1120);
    makeVerifiedReportFor($listing, $lgu, '2026-08-01', 980);
    MonthlyArrivalReport::query()->create([
        'lst_id' => $listing->lst_id, 'mun_id' => $listing->mun_id,
        'mar_period_month' => '2026-09-01', 'mar_submission_source' => ReportSubmissionSource::Digital,
        'mar_status' => MonthlyReportStatus::ForCorrection, 'mar_total_visitors' => 777, 'mar_remarks' => 'Please recount.',
        'mar_submitted_by' => $user->usr_id, 'mar_submitted_at' => now(),
    ]);

    $response = test()->actingAs($user)->get(route('establishment.arrivals.monthly', ['year' => 2026]));

    // One page, three in-page tabs, one year selector.
    $response->assertOk()
        ->assertSee('Reporting Year')
        ->assertSee('data-tab-target="overview"', false)
        ->assertSee('data-tab-target="records"', false)
        ->assertSee('data-tab-target="statistics"', false)
        ->assertSee('data-tab-panel="records"', false)
        ->assertSee('data-tab-panel="statistics"', false);

    // Overview: counts, progress, and what needs attention.
    $response->assertSee('2026 Reporting Overview')
        ->assertSee('2,100')
        ->assertSee('Needs Your Attention')
        ->assertSee('Returned by the LGU: Please recount.');

    // Monthly Records: every month up to now, with the right action per status.
    $response->assertSee('Monthly Records — 2026')
        ->assertSee('January 2026')
        ->assertSee('Not Submitted')
        ->assertSee('Prepare Report')
        ->assertSee('View Remarks')
        ->assertSee('Correct Report')
        ->assertSee('Report Preview');

    // Statistics: verified-only figures (the For Correction 777 is excluded).
    $response->assertSee('Visitor Classifications — 2026')
        ->assertSee('Comparison not available');

    // A direct link opens the requested tab first.
    test()->actingAs($user)->get(route('establishment.arrivals.monthly', ['year' => 2026, 'tab' => 'statistics']))
        ->assertOk()
        ->assertSee('data-tab-panel="statistics" class=""', false);
});

test('Prepare Report on a Not Submitted month creates its draft and opens it for checking', function () {
    $listing = makeEstablishmentListing('Lupon', 'LUP6', 'Prepare Resort');
    $user = makeEstablishmentUser($listing);

    $listing->arrivals()->create([
        'arr_source' => 'staff', 'arr_date' => '2026-03-05', 'arr_visit_type' => 'Daytour',
        'arr_party_male' => 2, 'arr_party_female' => 2, 'arr_party_size' => 4, 'arr_status' => 'Recorded',
    ]);

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.draft'), ['period_month' => '2026-03'])->assertRedirect();
    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();

    test()->actingAs($user)->get(route('establishment.arrivals.monthly.show', $report))
        ->assertOk()
        ->assertSee('Guest Arrivals Included (1)')
        ->assertSee('Update Totals')
        ->assertSee('Submit to LGU');

    test()->actingAs($user)->get(route('establishment.arrivals.monthly', ['year' => 2026, 'tab' => 'records']))
        ->assertSee('Continue &amp; Submit', false);
});

test('an establishment can open, preview, and download its own report as PDF, but not another establishment', function () {
    $listing = makeEstablishmentListing('Boston', 'BOS5', 'Own Report Inn');
    $otherListing = makeEstablishmentListing('Boston', 'BOS5', 'Other Inn');
    $user = makeEstablishmentUser($listing);
    $otherUser = makeEstablishmentUser($otherListing);
    $lgu = makeLguUser($listing->municipalityRecord);
    $report = makeVerifiedReportFor($listing, $lgu, '2026-08-01', 640);

    test()->actingAs($user)->get(route('establishment.arrivals.monthly.show', $report))
        ->assertOk()
        ->assertSee('Report Details')
        ->assertSee('Report Preview')
        ->assertSee('This report is final')
        ->assertDontSee('Submit to LGU');

    test()->actingAs($user)->get(route('establishment.arrivals.monthly.show', [$report, 'view' => 'a4']))
        ->assertOk()
        ->assertSee(route('establishment.arrivals.monthly.preview', $report), false);

    test()->actingAs($user)->get(route('establishment.arrivals.monthly.preview', $report))
        ->assertOk()
        ->assertSee('Monthly Tourist Arrival Report')
        ->assertSee('Download PDF');

    $pdf = test()->actingAs($user)->get(route('establishment.arrivals.monthly.pdf', $report));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf');

    test()->actingAs($otherUser)->get(route('establishment.arrivals.monthly.show', $report))->assertForbidden();
    test()->actingAs($otherUser)->get(route('establishment.arrivals.monthly.pdf', $report))->assertForbidden();
});

test('LGU Municipal Reports has its own sidebar item with Overview, Monthly Records, and Statistics', function () {
    $listingA = makeEstablishmentListing('Caraga', 'CAR5', 'Golf Resort');
    $listingB = makeEstablishmentListing('Caraga', 'CAR5', 'Hotel India');
    $lgu = makeLguUser($listingA->municipalityRecord);

    makeVerifiedReportFor($listingA, $lgu, '2026-02-01', 800);
    makeVerifiedReportFor($listingA, $lgu, '2026-03-01', 1200);
    makeVerifiedReportFor($listingB, $lgu, '2026-03-01', 300);

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.index'))
        ->assertOk()
        ->assertSee(route('lgu.monthlyReports.municipal'), false);

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.municipal', ['year' => 2026]))
        ->assertOk()
        ->assertSee('Municipal Tourism Overview — 2026')
        ->assertSee('2,300')
        ->assertSee('2 of 2')
        ->assertSee('Golf Resort')
        ->assertSee('No 2025 records');

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.municipal', ['year' => 2026, 'section' => 'records']))
        ->assertOk()
        ->assertSee('Monthly Municipal Records — 2026')
        ->assertSee('March 2026')
        ->assertSee('Increased')
        ->assertSee('Quarterly Summary — 2026');

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.municipal', ['year' => 2026, 'section' => 'statistics']))
        ->assertOk()
        ->assertSee('By Visitor Classification — 2026')
        ->assertSee('By Category — 2026')
        ->assertSee('Hotel India');
});

test('the LGU municipal report detail shows coverage and report information, and downloads as PDF', function () {
    $listing = makeEstablishmentListing('Manay', 'MAN5', 'Juliet Lodge');
    makeEstablishmentListing('Manay', 'MAN5', 'Kilo Inn');
    $lgu = makeLguUser($listing->municipalityRecord);
    makeVerifiedReportFor($listing, $lgu, '2026-09-01', 410);

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.municipal.show', '2026-09'))
        ->assertOk()
        ->assertSee('1 of 2')
        ->assertSee('Visitor Classifications')
        ->assertSee('Report Information')
        ->assertSee('Assigned when submitted')
        ->assertSee('Download PDF');

    $pdf = test()->actingAs($lgu)->get(route('lgu.monthlyReports.municipal.pdf', '2026-09'));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf');
});

test('reporting pages still open when there are no reports at all yet', function () {
    $listing = makeEstablishmentListing('Tarragona', 'TAR5', 'Brand New Resort');
    $user = makeEstablishmentUser($listing);
    $lgu = makeLguUser($listing->municipalityRecord);

    test()->actingAs($user)->get(route('establishment.arrivals.monthly'))->assertOk();
    test()->actingAs($user)->get(route('establishment.arrivals.monthly', ['tab' => 'overview']))->assertOk()->assertSee('No verified reports');

    foreach (['overview', 'records', 'statistics'] as $section) {
        test()->actingAs($lgu)->get(route('lgu.monthlyReports.municipal', ['section' => $section]))->assertOk();
    }
});

// --- Quick-view modals and Report Preview ---

test('the establishment workspace opens View and View Remarks in a modal on the same page', function () {
    $listing = makeEstablishmentListing('Manay', 'MAN6', 'Modal Resort');
    $user = makeEstablishmentUser($listing);
    $lgu = makeLguUser($listing->municipalityRecord);
    $verified = makeVerifiedReportFor($listing, $lgu, '2026-06-01', 432);
    $returned = MonthlyArrivalReport::query()->create([
        'lst_id' => $listing->lst_id, 'mun_id' => $listing->mun_id,
        'mar_period_month' => '2026-07-01', 'mar_submission_source' => ReportSubmissionSource::Digital,
        'mar_status' => MonthlyReportStatus::ForCorrection, 'mar_total_visitors' => 12, 'mar_remarks' => 'Add the July 4 tour group.',
        'mar_submitted_by' => $user->usr_id, 'mar_submitted_at' => now(),
    ]);

    test()->actingAs($user)->get(route('establishment.arrivals.monthly', ['year' => 2026, 'tab' => 'records']))
        ->assertOk()
        ->assertSee('data-modal-open="report-modal-'.$verified->mar_id.'"', false)
        ->assertSee('id="report-modal-'.$verified->mar_id.'"', false)
        ->assertSee('data-modal-open="report-modal-'.$returned->mar_id.'"', false)
        ->assertSee('What to fix: Add the July 4 tour group.')
        ->assertSee('Open Full Report')
        ->assertSee('Report Preview');
});

test('report pages show Report Preview with Print and Download PDF, for the establishment and the LGU', function () {
    $listing = makeEstablishmentListing('Lupon', 'LUP7', 'Preview Lodge');
    $user = makeEstablishmentUser($listing);
    $lgu = makeLguUser($listing->municipalityRecord);
    $otherLgu = makeLguUser(makeMunicipality('Baganga', 'BAG7'));
    $report = makeVerifiedReportFor($listing, $lgu, '2026-05-01', 210);

    test()->actingAs($user)->get(route('establishment.arrivals.monthly.show', [$report, 'view' => 'a4']))
        ->assertOk()
        ->assertSee('Report Preview')
        ->assertSee('Print')
        ->assertSee(route('establishment.arrivals.monthly.pdf', $report), false)
        ->assertDontSee('A4 Preview');

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.show', [$report, 'view' => 'a4']))
        ->assertOk()
        ->assertSee('Report Preview')
        ->assertSee(route('lgu.monthlyReports.pdf', $report), false);

    $pdf = test()->actingAs($lgu)->get(route('lgu.monthlyReports.pdf', $report));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf');
    test()->actingAs($otherLgu)->get(route('lgu.monthlyReports.pdf', $report))->assertForbidden();

    // LGU Monthly Reports: View on a Verified report is a modal.
    test()->actingAs($lgu)->get(route('lgu.monthlyReports.index', ['period' => '2026-05']))
        ->assertOk()
        ->assertSee('data-modal-open="lgu-report-modal-'.$report->mar_id.'"', false)
        ->assertSee('id="lgu-report-modal-'.$report->mar_id.'"', false);

    // LGU Municipal Reports: View on a month not ready to submit is a modal.
    MonthlyArrivalReport::query()->create([
        'lst_id' => makeEstablishmentListing('Lupon', 'LUP7', 'Pending Lodge')->lst_id, 'mun_id' => $listing->mun_id,
        'mar_period_month' => '2026-05-01', 'mar_submission_source' => ReportSubmissionSource::Digital,
        'mar_status' => MonthlyReportStatus::Submitted, 'mar_total_visitors' => 5,
        'mar_submitted_by' => $lgu->usr_id, 'mar_submitted_at' => now(),
    ]);
    test()->actingAs($lgu)->get(route('lgu.monthlyReports.municipal', ['year' => 2026, 'section' => 'records']))
        ->assertOk()
        ->assertSee('data-modal-open="municipal-modal-2026-05"', false)
        ->assertSee('id="municipal-modal-2026-05"', false)
        ->assertDontSee('Preview A4');
});
