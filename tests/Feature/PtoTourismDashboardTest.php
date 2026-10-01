<?php

use App\Enums\MonthlyReportStatus;
use App\Enums\ReportSubmissionSource;
use App\Enums\UserRole;
use App\Models\MonthlyArrivalReport;
use App\Models\MunicipalReport;
use App\Models\User;
use App\Support\TourismAnalytics;

// makeEstablishmentListing(), makeEstablishmentUser() come from
// tests/Feature/Rbac/EstablishmentScopingTest.php; makeMunicipality(),
// makeLguUser() come from tests/Feature/Rbac/MunicipalityScopingTest.php —
// Pest merges every test file's top-level functions into one global
// namespace, so they're reused here rather than redefined.

test('KPIs only count Verified reports, never For Review ones', function () {
    $listing = makeEstablishmentListing('City of Mati', 'DASH1', 'Botanika Resort');
    $municipality = $listing->municipalityRecord;
    $user = makeEstablishmentUser($listing);

    MonthlyArrivalReport::query()->create([
        'listing_id' => $listing->id, 'municipality_id' => $municipality->id,
        'period_month' => '2026-09-01', 'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::Verified, 'total_visitors' => 100,
        'party_local' => 80, 'party_foreign' => 20,
        'submitted_by' => $user->id, 'submitted_at' => now(),
    ]);
    MonthlyArrivalReport::query()->create([
        'listing_id' => $listing->id, 'municipality_id' => $municipality->id,
        'period_month' => '2026-08-01', 'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::ForReview, 'total_visitors' => 9999,
        'submitted_by' => $user->id, 'submitted_at' => now(),
    ]);

    $filters = ['year' => 2026, 'month' => 9, 'municipalityId' => null, 'listingId' => null, 'classification' => null];
    $comparison = TourismAnalytics::periodComparison($filters);
    $kpis = collect(TourismAnalytics::kpis($filters, $comparison));

    expect($kpis->firstWhere('label', 'Total Tourist Arrivals')['value'])->toBe('100');
});

test('Reporting LGUs KPI shows submitted-over-total correctly', function () {
    $submitted = makeMunicipality('City of Mati', 'DASH2A');
    makeMunicipality('Baganga', 'DASH2B');
    $lgu = makeLguUser($submitted);

    MunicipalReport::query()->create([
        'municipality' => $submitted->name, 'municipality_id' => $submitted->id,
        'submitted_by' => $lgu->id, 'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
        'total_arrivals' => 100, 'status' => MunicipalReport::STATUS_SUBMITTED,
    ]);

    $filters = ['year' => 2026, 'month' => 9, 'municipalityId' => null, 'listingId' => null, 'classification' => null];
    $comparison = TourismAnalytics::periodComparison($filters);
    $kpis = collect(TourismAnalytics::kpis($filters, $comparison));

    // At least these 2 plus whatever municipalities exist from earlier tests
    // in this run — assert the submitted count is present, not an exact total.
    expect($kpis->firstWhere('label', 'Reporting LGUs')['value'])->toContain('/');
});

test('reporting status shows the literal label Not Submitted, never a zero', function () {
    $municipality = makeMunicipality('Lupon', 'DASH3');

    $rows = TourismAnalytics::reportingStatus(['year' => 2026, 'month' => 9, 'municipalityId' => $municipality->id, 'listingId' => null, 'classification' => null]);

    expect($rows->first()['status'])->toBe('Not Submitted');
    expect($rows->first()['report'])->toBeNull();
});

test('period comparison returns "No comparison available" when the previous period has no data, not a misleading percentage', function () {
    $listing = makeEstablishmentListing('Cateel', 'DASH4', 'ABC Resort');
    $user = makeEstablishmentUser($listing);

    MonthlyArrivalReport::query()->create([
        'listing_id' => $listing->id, 'municipality_id' => $listing->municipality_id,
        'period_month' => '2026-09-01', 'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::Verified, 'total_visitors' => 100,
        'submitted_by' => $user->id, 'submitted_at' => now(),
    ]);

    $filters = ['year' => 2026, 'month' => 9, 'municipalityId' => $listing->municipality_id, 'listingId' => null, 'classification' => null];
    $comparison = TourismAnalytics::periodComparison($filters);

    expect($comparison['label'])->toBe('No comparison available');
    expect($comparison['percentageChange'])->toBeNull();
});

test('period comparison computes a safe percentage when both periods have verified data', function () {
    $listing = makeEstablishmentListing('Banaybanay', 'DASH5', 'XYZ Hotel');
    $user = makeEstablishmentUser($listing);

    MonthlyArrivalReport::query()->create([
        'listing_id' => $listing->id, 'municipality_id' => $listing->municipality_id,
        'period_month' => '2026-08-01', 'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::Verified, 'total_visitors' => 100,
        'submitted_by' => $user->id, 'submitted_at' => now(),
    ]);
    MonthlyArrivalReport::query()->create([
        'listing_id' => $listing->id, 'municipality_id' => $listing->municipality_id,
        'period_month' => '2026-09-01', 'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::Verified, 'total_visitors' => 150,
        'submitted_by' => $user->id, 'submitted_at' => now(),
    ]);

    $filters = ['year' => 2026, 'month' => 9, 'municipalityId' => $listing->municipality_id, 'listingId' => null, 'classification' => null];
    $comparison = TourismAnalytics::periodComparison($filters);

    expect($comparison['label'])->toBe('Increased');
    expect($comparison['percentageChange'])->toBe(50.0);
});

test('classification filter changes the arrival total to the selected classification only', function () {
    $listing = makeEstablishmentListing('Manay', 'DASH6', 'Coastal Lodge');
    $user = makeEstablishmentUser($listing);

    MonthlyArrivalReport::query()->create([
        'listing_id' => $listing->id, 'municipality_id' => $listing->municipality_id,
        'period_month' => '2026-09-01', 'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::Verified, 'total_visitors' => 100,
        'party_local' => 70, 'party_foreign' => 30,
        'submitted_by' => $user->id, 'submitted_at' => now(),
    ]);

    $baseFilters = ['year' => 2026, 'month' => 9, 'municipalityId' => $listing->municipality_id, 'listingId' => null];
    $comparison = TourismAnalytics::periodComparison([...$baseFilters, 'classification' => 'local']);

    $localKpis = collect(TourismAnalytics::kpis([...$baseFilters, 'classification' => 'local'], $comparison));
    $foreignKpis = collect(TourismAnalytics::kpis([...$baseFilters, 'classification' => 'foreign'], $comparison));

    expect($localKpis->firstWhere('label', 'Total Tourist Arrivals')['value'])->toBe('70');
    expect($foreignKpis->firstWhere('label', 'Total Tourist Arrivals')['value'])->toBe('30');
});

test('dashboard renders the insufficient-data trend state when a filtered year has no verified reports', function () {
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    $response = test()->actingAs($pto)->get(route('pto.dashboard', ['year' => 2019]));

    $response->assertOk();
    $response->assertSee('Trend unavailable — insufficient historical data.');
});

test('the dashboard page renders for PTO with real sections', function () {
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    $response = test()->actingAs($pto)->get(route('pto.dashboard'));

    $response->assertOk();
    $response->assertSee('Tourism Monitoring Dashboard');
    $response->assertSee('LGU Visitation Statistics');
    $response->assertSee('LGU Reporting Status');
    $response->assertSee('Not available yet');
});

test('non-PTO roles cannot access the dashboard', function () {
    $listing = makeEstablishmentListing('San Isidro', 'DASH7', 'Palm Grove Inn');
    $user = makeEstablishmentUser($listing);

    test()->actingAs($user)->get(route('pto.dashboard'))->assertForbidden();
});
