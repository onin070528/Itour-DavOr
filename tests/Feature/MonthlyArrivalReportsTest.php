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
        ->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])
        ->assertRedirect();

    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();

    expect($report->mar_submission_source)->toBe(ReportSubmissionSource::Digital);
    expect($report->mar_status)->toBe(MonthlyReportStatus::ForReview);
    expect($report->mar_party_male)->toBe(3);
    expect($report->mar_party_female)->toBe(2);
    expect($report->mar_total_visitors)->toBe(5);
    expect($report->mar_submitted_by)->toBe($user->usr_id);
    expect($listing->arrivals()->whereNull('mar_id')->count())->toBe(0);
    expect($listing->arrivals()->where('mar_id', $report->mar_id)->count())->toBe(2);
});

test('a month with no recorded arrivals still submits as a zero-arrival report', function () {
    $listing = makeEstablishmentListing('Baganga', 'BAG', 'Coastal Lodge');
    $user = makeEstablishmentUser($listing);

    test()->actingAs($user)
        ->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])
        ->assertRedirect();

    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();
    expect($report->mar_total_visitors)->toBe(0);
    expect($report->mar_status)->toBe(MonthlyReportStatus::ForReview);
});

test('an establishment cannot submit the same month twice', function () {
    $listing = makeEstablishmentListing('Lupon', 'LUP', 'ABC Resort');
    $user = makeEstablishmentUser($listing);

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])->assertRedirect();
    test()->actingAs($user)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])->assertRedirect();

    expect(MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->count())->toBe(1);
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

    $report = MonthlyArrivalReport::query()->where('lst_id', $listing->lst_id)->sole();
    expect($report->mar_submission_source)->toBe(ReportSubmissionSource::ManualPaper);
    expect($report->mar_status)->toBe(MonthlyReportStatus::ForReview);
    expect($report->mar_total_visitors)->toBe(230);
    expect($report->mar_submitted_by)->toBe($lgu->usr_id);
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

    test()->actingAs($lgu)->post(route('lgu.monthlyReports.consolidate'), ['period_month' => '2026-09'])->assertRedirect();

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
    $response->assertSee('Encode Paper Report');

    $content = $response->getContent();
    $notSubmittedPos = strpos($content, $notSubmitted->lst_name);
    $verifiedPos = strpos($content, $verified->lst_name);
    expect($notSubmittedPos)->not->toBeFalse();
    expect($verifiedPos)->not->toBeFalse();
    expect($notSubmittedPos)->toBeLessThan($verifiedPos);
});

test('PTO Provincial Reports page is read-only and can switch municipalities', function () {
    $listing = makeEstablishmentListing('Caraga', 'CAR2', 'Riverside Resort');
    $municipality = $listing->municipalityRecord;
    $establishmentUser = makeEstablishmentUser($listing);
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    $report = MonthlyArrivalReport::query()->create([
        'lst_id' => $listing->lst_id, 'mun_id' => $municipality->mun_id,
        'mar_period_month' => '2026-09-01', 'mar_submission_source' => ReportSubmissionSource::ManualPaper,
        'mar_status' => MonthlyReportStatus::ForReview, 'mar_total_visitors' => 7,
        'mar_submitted_by' => $pto->usr_id, 'mar_submitted_at' => now(),
    ]);

    $response = test()->actingAs($pto)->get(route('pto.monthlyReports.index', [
        'period' => '2026-09', 'municipality_id' => $municipality->mun_id,
    ]));

    $response->assertOk();
    $response->assertSee($listing->lst_name);
    $response->assertDontSee('Encode Paper Report');
    $response->assertDontSee('Monthly Consolidation');
    $response->assertSee('Municipality / LGU');

    test()->actingAs($pto)->get(route('pto.monthlyReports.show', $report))->assertOk()->assertDontSee('Verify');
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

    test()->actingAs($user)
        ->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])
        ->assertRedirect();
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

    test()->actingAs($user)
        ->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])
        ->assertRedirect();
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
