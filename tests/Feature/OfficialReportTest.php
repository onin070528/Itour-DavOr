<?php

use App\Enums\MonthlyReportStatus;
use App\Enums\ReportSubmissionSource;
use App\Enums\UserRole;
use App\Models\MonthlyArrivalReport;
use App\Models\Municipality;
use App\Models\MunicipalReport;
use App\Models\User;

function makeBalancedMonthlyReport(Municipality $municipality, $listing, MunicipalReport $municipalReport, array $overrides = []): MonthlyArrivalReport
{
    $submitter = User::factory()->create(['role' => UserRole::Establishment]);

    return MonthlyArrivalReport::query()->create(array_merge([
        'listing_id' => $listing->id,
        'municipality_id' => $municipality->id,
        'period_month' => $municipalReport->period_start,
        'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::Verified,
        'total_visitors' => 10,
        'party_male' => 6,
        'party_female' => 4,
        'party_adults' => 7,
        'party_children' => 2,
        'party_seniors' => 1,
        'party_local' => 8,
        'party_foreign' => 2,
        'submitted_by' => $submitter->id,
        'submitted_at' => now(),
        'municipal_report_id' => $municipalReport->id,
    ], $overrides));
}

test('a report with mismatched totals cannot be verified', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Mismatch Resort');
    $pto = actingAsPtoAdministrator();
    $report = makeMunicipalReport(['municipality_id' => $mati->id]);

    makeBalancedMonthlyReport($mati, $listing, $report, [
        'party_male' => 5, 'party_female' => 4, // 9 != total_visitors (10)
    ]);

    $response = test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report));

    $response->assertStatus(422);
    expect($report->fresh()->status)->toBe(MunicipalReport::STATUS_SUBMITTED);
    expect($report->fresh()->verification_code)->toBeNull();
});

test('a balanced report can be verified and gets a verification code and frozen snapshot', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Balanced Resort');
    $pto = actingAsPtoAdministrator();
    $report = makeMunicipalReport(['municipality_id' => $mati->id]);

    makeBalancedMonthlyReport($mati, $listing, $report);

    test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report))->assertRedirect();

    $report->refresh();
    expect($report->status)->toBe(MunicipalReport::STATUS_APPROVED);
    expect($report->verification_code)->not->toBeNull();
    expect($report->frozen_snapshot)->not->toBeNull();
    expect($report->frozen_snapshot['grand_total']['total'])->toBe(10);
});

test('a Draft (pending) report shows the watermark, and a Verified report does not', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Watermark Resort');
    $pto = actingAsPtoAdministrator();
    $report = makeMunicipalReport(['municipality_id' => $mati->id]);
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
    $report = makeMunicipalReport(['municipality_id' => $mati->id]);
    $monthlyReport = makeBalancedMonthlyReport($mati, $listing, $report);

    test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report))->assertRedirect();
    $report->refresh();
    $originalTotal = $report->frozen_snapshot['grand_total']['total'];

    // Mutate the underlying establishment-month data directly, bypassing
    // the normal resubmission flow — the Verified report's own PDF must
    // still reflect the original numbers.
    $monthlyReport->update(['total_visitors' => 999, 'party_male' => 999, 'party_female' => 0]);

    $response = test()->actingAs($pto)->get(route('pto.municipalReports.officialReport', $report->fresh()));
    $response->assertOk();
    // '#999' is a CSS color in the shared stylesheet, so assert against the
    // specific table-cell value rather than the bare substring.
    $response->assertDontSee('>999<', false);
    expect($report->fresh()->frozen_snapshot['grand_total']['total'])->toBe($originalTotal);
});

test('the Official Report PDF downloads successfully', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'PDF Resort');
    $pto = actingAsPtoAdministrator();
    $report = makeMunicipalReport(['municipality_id' => $mati->id]);
    makeBalancedMonthlyReport($mati, $listing, $report);

    $response = test()->actingAs($pto)->get(route('pto.municipalReports.officialReport.pdf', $report));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

test('the Official Report Excel export downloads successfully', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Excel Resort');
    $pto = actingAsPtoAdministrator();
    $report = makeMunicipalReport(['municipality_id' => $mati->id]);
    makeBalancedMonthlyReport($mati, $listing, $report);

    $response = test()->actingAs($pto)->get(route('pto.municipalReports.officialReport.excel', $report));

    $response->assertOk();
});

test('the public verification page confirms a verified report without showing arrival details', function () {
    $mati = makeMunicipalityFixture('City of Mati', 'MATI');
    $listing = makeEstablishmentListing('City of Mati', 'MATI', 'Verify Resort');
    $pto = actingAsPtoAdministrator();
    $report = makeMunicipalReport(['municipality_id' => $mati->id]);
    makeBalancedMonthlyReport($mati, $listing, $report);

    test()->actingAs($pto)->patch(route('pto.municipalReports.approve', $report))->assertRedirect();
    $code = $report->fresh()->verification_code;

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
    $report = makeMunicipalReport(['municipality_id' => $mati->id]);
    $lgu = User::factory()->create(['role' => UserRole::Lgu]);

    test()->actingAs($lgu)->get(route('pto.municipalReports.officialReport', $report))->assertForbidden();
    test()->actingAs($lgu)->get(route('pto.municipalReports.officialReport.pdf', $report))->assertForbidden();
});
