<?php

use App\Enums\MonthlyReportStatus;
use App\Enums\ReportSubmissionSource;
use App\Enums\UserRole;
use App\Models\MonthlyArrivalReport;
use App\Models\User;

test('the Provincial Reports Official Report previews as a live Draft when the period is not yet verified', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Provincial Test Resort');
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    MonthlyArrivalReport::query()->create([
        'listing_id' => $listing->id, 'municipality_id' => $mati->id,
        'period_month' => now()->startOfMonth(), 'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::Verified, 'total_visitors' => 10,
        'party_male' => 6, 'party_female' => 4,
        'party_adults' => 8, 'party_children' => 1, 'party_seniors' => 1,
        'party_local' => 9, 'party_foreign' => 1,
        'submitted_by' => $pto->id, 'submitted_at' => now(),
    ]);

    $response = test()->actingAs($pto)->get(route('pto.monthlyReports.officialReport', [
        'period' => now()->format('Y-m'), 'municipality_id' => $mati->id,
    ]));

    $response->assertOk();
    $response->assertSee('DRAFT');
    $response->assertSee('Provincial Test Resort');
});

test('the Provincial Reports Official Report redirects to the frozen Municipal Report once one is Verified for that period', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Already Verified Resort');
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);
    $report = makeMunicipalReport(['municipality_id' => $mati->id]);

    $submitter = User::factory()->create(['role' => UserRole::Establishment]);
    MonthlyArrivalReport::query()->create([
        'listing_id' => $listing->id, 'municipality_id' => $mati->id,
        'period_month' => $report->period_start, 'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::Verified, 'total_visitors' => 10,
        'party_male' => 6, 'party_female' => 4,
        'party_adults' => 8, 'party_children' => 1, 'party_seniors' => 1,
        'party_local' => 9, 'party_foreign' => 1,
        'submitted_by' => $submitter->id, 'submitted_at' => now(),
        'municipal_report_id' => $report->id,
    ]);

    test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report))->assertRedirect();

    $response = test()->actingAs($pto)->get(route('pto.monthlyReports.officialReport', [
        'period' => $report->period_start->format('Y-m'), 'municipality_id' => $mati->id,
    ]));

    $response->assertRedirect(route('pto.municipalReports.officialReport', $report));
});

test('a non-PTO user cannot access the Provincial Reports official report routes', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $lgu = User::factory()->create(['role' => UserRole::Lgu]);

    test()->actingAs($lgu)->get(route('pto.monthlyReports.officialReport', ['municipality_id' => $mati->id]))->assertForbidden();
});
