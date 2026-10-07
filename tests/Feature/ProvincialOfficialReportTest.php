<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — provincial official report.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\MonthlyReportStatus;
use App\Enums\ReportSubmissionSource;
use App\Enums\UserRole;
use App\Models\MonthlyArrivalReport;
use App\Models\User;

test('the Provincial Reports Official Report previews as a live Draft when the period is not yet verified', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Provincial Test Resort');
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    MonthlyArrivalReport::query()->create([
        'lst_id' => $listing->lst_id, 'mun_id' => $mati->mun_id,
        'mar_period_month' => now()->startOfMonth(), 'mar_submission_source' => ReportSubmissionSource::Digital,
        'mar_status' => MonthlyReportStatus::Verified, 'mar_total_visitors' => 10,
        'mar_party_male' => 6, 'mar_party_female' => 4,
        'mar_party_adults' => 8, 'mar_party_children' => 1, 'mar_party_seniors' => 1,
        'mar_party_local' => 9, 'mar_party_foreign' => 1,
        'mar_submitted_by' => $pto->usr_id, 'mar_submitted_at' => now(),
    ]);

    $response = test()->actingAs($pto)->get(route('pto.monthlyReports.officialReport', [
        'period' => now()->format('Y-m'), 'municipality_id' => $mati->mun_id,
    ]));

    $response->assertOk();
    $response->assertSee('DRAFT');
    $response->assertSee('Provincial Test Resort');
});

test('the Provincial Reports Official Report redirects to the frozen Municipal Report once one is Verified for that period', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Already Verified Resort');
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $report = makeMunicipalReport(['mun_id' => $mati->mun_id]);

    $submitter = User::factory()->create(['usr_role' => UserRole::Establishment]);
    MonthlyArrivalReport::query()->create([
        'lst_id' => $listing->lst_id, 'mun_id' => $mati->mun_id,
        'mar_period_month' => $report->mrp_period_start, 'mar_submission_source' => ReportSubmissionSource::Digital,
        'mar_status' => MonthlyReportStatus::Verified, 'mar_total_visitors' => 10,
        'mar_party_male' => 6, 'mar_party_female' => 4,
        'mar_party_adults' => 8, 'mar_party_children' => 1, 'mar_party_seniors' => 1,
        'mar_party_local' => 9, 'mar_party_foreign' => 1,
        'mar_submitted_by' => $submitter->usr_id, 'mar_submitted_at' => now(),
        'mrp_id' => $report->mrp_id,
    ]);

    test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report))->assertRedirect();

    $response = test()->actingAs($pto)->get(route('pto.monthlyReports.officialReport', [
        'period' => $report->mrp_period_start->format('Y-m'), 'municipality_id' => $mati->mun_id,
    ]));

    $response->assertRedirect(route('pto.municipalReports.officialReport', $report));
});

test('a non-PTO user cannot access the Provincial Reports official report routes', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu]);

    test()->actingAs($lgu)->get(route('pto.monthlyReports.officialReport', ['municipality_id' => $mati->mun_id]))->assertForbidden();
});
