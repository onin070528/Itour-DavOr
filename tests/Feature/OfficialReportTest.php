<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — official report.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\MonthlyReportStatus;
use App\Enums\ReportSubmissionSource;
use App\Enums\UserRole;
use App\Models\MonthlyArrivalReport;
use App\Models\Municipality;
use App\Models\MunicipalReport;
use App\Models\User;

function makeBalancedMonthlyReport(Municipality $municipality, $listing, MunicipalReport $municipalReport, array $overrides = []): MonthlyArrivalReport
{
    $submitter = User::factory()->create(['usr_role' => UserRole::Establishment]);

    return MonthlyArrivalReport::query()->create(array_merge([
        'lst_id' => $listing->lst_id,
        'mun_id' => $municipality->mun_id,
        'mar_period_month' => $municipalReport->mrp_period_start,
        'mar_submission_source' => ReportSubmissionSource::Digital,
        'mar_status' => MonthlyReportStatus::Verified,
        'mar_total_visitors' => 10,
        'mar_party_male' => 6,
        'mar_party_female' => 4,
        'mar_party_adults' => 7,
        'mar_party_children' => 2,
        'mar_party_seniors' => 1,
        'mar_party_local' => 8,
        'mar_party_foreign' => 2,
        'mar_submitted_by' => $submitter->usr_id,
        'mar_submitted_at' => now(),
        'mrp_id' => $municipalReport->mrp_id,
    ], $overrides));
}

test('a report with mismatched totals cannot be verified', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Mismatch Resort');
    $pto = actingAsPtoAdministrator();
    $report = makeMunicipalReport(['mun_id' => $mati->mun_id]);

    makeBalancedMonthlyReport($mati, $listing, $report, [
        'mar_party_male' => 5, 'mar_party_female' => 4, // 9 != total_visitors (10)
    ]);

    $response = test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report));

    $response->assertStatus(422);
    expect($report->fresh()->mrp_status)->toBe(MunicipalReport::STATUS_SUBMITTED);
    expect($report->fresh()->mrp_verification_code)->toBeNull();
});

test('a balanced report can be verified and gets a verification code and frozen snapshot', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Balanced Resort');
    $pto = actingAsPtoAdministrator();
    $report = makeMunicipalReport(['mun_id' => $mati->mun_id]);

    makeBalancedMonthlyReport($mati, $listing, $report);

    test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report))->assertRedirect();

    $report->refresh();
    expect($report->mrp_status)->toBe(MunicipalReport::STATUS_APPROVED);
    expect($report->mrp_verification_code)->not->toBeNull();
    expect($report->mrp_frozen_snapshot)->not->toBeNull();
    expect($report->mrp_frozen_snapshot['grand_total']['total'])->toBe(10);
});

test('a Draft (pending) report shows the watermark, and a Verified report does not', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Watermark Resort');
    $pto = actingAsPtoAdministrator();
    $report = makeMunicipalReport(['mun_id' => $mati->mun_id]);
    makeBalancedMonthlyReport($mati, $listing, $report);

    $draftResponse = test()->actingAs($pto)->get(route('pto.municipalReports.officialReport', $report));
    $draftResponse->assertOk();
    $draftResponse->assertSee('DRAFT');

    test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report))->assertRedirect();

    $verifiedResponse = test()->actingAs($pto)->get(route('pto.municipalReports.officialReport', $report->fresh()));
    $verifiedResponse->assertOk();
    $verifiedResponse->assertDontSee('DRAFT');
});

test('a verified report does not change when later data changes', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Frozen Resort');
    $pto = actingAsPtoAdministrator();
    $report = makeMunicipalReport(['mun_id' => $mati->mun_id]);
    $monthlyReport = makeBalancedMonthlyReport($mati, $listing, $report);

    test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report))->assertRedirect();
    $report->refresh();
    $originalTotal = $report->mrp_frozen_snapshot['grand_total']['total'];

    // Mutate the underlying establishment-month data directly, bypassing
    // the normal resubmission flow — the Verified report's own PDF must
    // still reflect the original numbers.
    $monthlyReport->update(['mar_total_visitors' => 999, 'mar_party_male' => 999, 'mar_party_female' => 0]);

    $response = test()->actingAs($pto)->get(route('pto.municipalReports.officialReport', $report->fresh()));
    $response->assertOk();
    // '#999' is a CSS color in the shared stylesheet, so assert against the
    // specific table-cell value rather than the bare substring.
    $response->assertDontSee('>999<', false);
    expect($report->fresh()->mrp_frozen_snapshot['grand_total']['total'])->toBe($originalTotal);
});

test('the Official Report PDF downloads successfully', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'PDF Resort');
    $pto = actingAsPtoAdministrator();
    $report = makeMunicipalReport(['mun_id' => $mati->mun_id]);
    makeBalancedMonthlyReport($mati, $listing, $report);

    $response = test()->actingAs($pto)->get(route('pto.municipalReports.officialReport.pdf', $report));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('the Official Report Excel export downloads successfully', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Excel Resort');
    $pto = actingAsPtoAdministrator();
    $report = makeMunicipalReport(['mun_id' => $mati->mun_id]);
    makeBalancedMonthlyReport($mati, $listing, $report);

    $response = test()->actingAs($pto)->get(route('pto.municipalReports.officialReport.excel', $report));

    $response->assertOk();
});

test('the public verification page confirms a verified report without showing arrival details', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Verify Resort');
    $pto = actingAsPtoAdministrator();
    $report = makeMunicipalReport(['mun_id' => $mati->mun_id]);
    makeBalancedMonthlyReport($mati, $listing, $report);

    test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report))->assertRedirect();
    $code = $report->fresh()->mrp_verification_code;

    $response = test()->get(route('reports.verify', ['code' => $code]));

    $response->assertOk();
    $response->assertSee('genuine');
    $response->assertSee('Verified');
    $response->assertDontSee('Verify Resort');
});

test('an unknown verification code shows a not-found message', function () {
    $response = test()->get(route('reports.verify', ['code' => 'ITOUR-DOESNOTEXIST']));

    $response->assertOk();
    $response->assertSee('No report matches');
});

test('a non-PTO user cannot access the official report routes', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $report = makeMunicipalReport(['mun_id' => $mati->mun_id]);
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu]);

    test()->actingAs($lgu)->get(route('pto.municipalReports.officialReport', $report))->assertForbidden();
    test()->actingAs($lgu)->get(route('pto.municipalReports.officialReport.pdf', $report))->assertForbidden();
});
