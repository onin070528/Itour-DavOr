<?php

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
        'source' => 'staff', 'date' => '2026-09-05', 'visit_type' => 'Daytour',
        'party_male' => 2, 'party_female' => 1, 'party_adults' => 3, 'party_children' => 0,
        'party_seniors' => 0, 'party_local' => 3, 'party_foreign' => 0, 'party_size' => 3, 'status' => 'Recorded',
    ]);
    $listing->arrivals()->create([
        'source' => 'self_checkin', 'date' => '2026-09-20', 'visit_type' => 'Overnight',
        'party_male' => 1, 'party_female' => 1, 'party_adults' => 1, 'party_children' => 1,
        'party_seniors' => 0, 'party_local' => 1, 'party_foreign' => 1, 'party_size' => 2, 'status' => 'Recorded',
    ]);

    test()->actingAs($user)
        ->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])
        ->assertRedirect();

    $report = MonthlyArrivalReport::query()->where('listing_id', $listing->id)->sole();

    expect($report->submission_source)->toBe(ReportSubmissionSource::Digital);
    expect($report->status)->toBe(MonthlyReportStatus::ForReview);
    expect($report->party_male)->toBe(3);
    expect($report->party_female)->toBe(2);
    expect($report->total_visitors)->toBe(5);
    expect($report->submitted_by)->toBe($user->id);
    expect($listing->arrivals()->whereNull('monthly_arrival_report_id')->count())->toBe(0);
    expect($listing->arrivals()->where('monthly_arrival_report_id', $report->id)->count())->toBe(2);
});

test('a month with no recorded arrivals still submits as a zero-arrival report', function () {
    $listing = makeEstablishmentListing('Baganga', 'BAG', 'Coastal Lodge');
    $user = makeEstablishmentUser($listing);

    test()->actingAs($user)
        ->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])
        ->assertRedirect();

    $report = MonthlyArrivalReport::query()->where('listing_id', $listing->id)->sole();
    expect($report->total_visitors)->toBe(0);
    expect($report->status)->toBe(MonthlyReportStatus::ForReview);
});

test('an establishment cannot submit the same month twice', function () {
    $listing = makeEstablishmentListing('Lupon', 'LUP', 'ABC Resort');
    $user = makeEstablishmentUser($listing);

    test()->actingAs($user)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])->assertRedirect();
    test()->actingAs($user)->post(route('establishment.arrivals.monthly.submit'), ['period_month' => '2026-09'])->assertRedirect();

    expect(MonthlyArrivalReport::query()->where('listing_id', $listing->id)->count())->toBe(1);
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

    $report = MonthlyArrivalReport::query()->where('listing_id', $listing->id)->sole();
    expect($report->submission_source)->toBe(ReportSubmissionSource::ManualPaper);
    expect($report->status)->toBe(MonthlyReportStatus::ForReview);
    expect($report->total_visitors)->toBe(230);
    expect($report->submitted_by)->toBe($lgu->id);
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
        'listing_id' => $listing->id, 'municipality_id' => $municipality->id,
        'period_month' => '2026-09-01', 'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::ForReview, 'total_visitors' => 10,
        'submitted_by' => $establishmentUser->id, 'submitted_at' => now(),
    ]);

    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.verify', $report))->assertRedirect();
    expect($report->fresh()->status)->toBe(MonthlyReportStatus::Verified);
    expect($report->fresh()->verified_by)->toBe($lgu->id);

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
    $listingB = DB::table('listings')->insertGetId([
        'slug' => 'xyz-hotel-'.Str::random(6), 'name' => 'XYZ Hotel', 'category' => 'accommodation',
        'municipality' => $municipality->name, 'municipality_id' => $municipality->id,
        'barangay' => 'Poblacion', 'status' => 'PUBLISHED', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $lgu = makeLguUser($municipality);
    $establishmentUser = makeEstablishmentUser($listingA);

    $verified = MonthlyArrivalReport::query()->create([
        'listing_id' => $listingA->id, 'municipality_id' => $municipality->id,
        'period_month' => '2026-09-01', 'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::Verified, 'total_visitors' => 150,
        'submitted_by' => $establishmentUser->id, 'submitted_at' => now(),
        'verified_by' => $lgu->id, 'verified_at' => now(),
    ]);
    $forReview = MonthlyArrivalReport::query()->create([
        'listing_id' => $listingB, 'municipality_id' => $municipality->id,
        'period_month' => '2026-09-01', 'submission_source' => ReportSubmissionSource::ManualPaper,
        'status' => MonthlyReportStatus::ForReview, 'total_visitors' => 230,
        'submitted_by' => $lgu->id, 'submitted_at' => now(),
    ]);

    test()->actingAs($lgu)->post(route('lgu.monthlyReports.consolidate'), ['period_month' => '2026-09'])->assertRedirect();

    $municipalReport = MunicipalReport::query()->where('municipality_id', $municipality->id)->sole();
    expect($municipalReport->total_arrivals)->toBe(150);
    expect($municipalReport->status)->toBe(MunicipalReport::STATUS_SUBMITTED);
    expect($verified->fresh()->municipal_report_id)->toBe($municipalReport->id);
    expect($forReview->fresh()->municipal_report_id)->toBeNull();
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
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    MonthlyArrivalReport::query()->create([
        'listing_id' => $listing->id, 'municipality_id' => $listing->municipality_id,
        'period_month' => '2026-09-01', 'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::Verified, 'total_visitors' => 5,
        'submitted_by' => $pto->id, 'submitted_at' => now(),
    ]);

    expect(MonthlyArrivalReport::query()->visibleTo($pto)->count())->toBe(1);
});

test('PTO municipal report show page renders the consolidated-from drill-down table', function () {
    $listing = makeEstablishmentListing('Tarragona', 'TRG', 'Sunrise Villas');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);
    $establishmentUser = makeEstablishmentUser($listing);
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    $monthlyReport = MonthlyArrivalReport::query()->create([
        'listing_id' => $listing->id, 'municipality_id' => $municipality->id,
        'period_month' => '2026-09-01', 'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::Verified, 'total_visitors' => 42,
        'submitted_by' => $establishmentUser->id, 'submitted_at' => now(),
        'verified_by' => $lgu->id, 'verified_at' => now(),
    ]);

    $municipalReport = MunicipalReport::query()->create([
        'municipality' => $municipality->name, 'municipality_id' => $municipality->id,
        'submitted_by' => $lgu->id, 'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
        'total_arrivals' => 42, 'status' => MunicipalReport::STATUS_SUBMITTED,
    ]);
    $monthlyReport->update(['municipal_report_id' => $municipalReport->id]);

    $response = test()->actingAs($pto)->get(route('pto.municipalReports.show', $municipalReport));

    $response->assertOk();
    $response->assertSee('Breakdown by Establishment');
    $response->assertSee($listing->name);
    $response->assertSee('42');
});

test('LGU Tourism Reports page shows the workflow steps, KPI cards, and sorts Not Submitted rows first', function () {
    $verified = makeEstablishmentListing('Boston', 'BOS2', 'ABC Resort');
    $municipality = $verified->municipalityRecord;
    $notSubmitted = Listing::query()->create([
        'slug' => 'xyz-hotel-'.Str::random(6), 'name' => 'XYZ Hotel', 'category' => 'accommodation',
        'municipality' => $municipality->name, 'municipality_id' => $municipality->id,
        'barangay' => 'Poblacion', 'status' => 'PUBLISHED',
    ]);
    $lgu = makeLguUser($municipality);
    $establishmentUser = makeEstablishmentUser($verified);

    MonthlyArrivalReport::query()->create([
        'listing_id' => $verified->id, 'municipality_id' => $municipality->id,
        'period_month' => '2026-09-01', 'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::Verified, 'total_visitors' => 10,
        'submitted_by' => $establishmentUser->id, 'submitted_at' => now(),
        'verified_by' => $lgu->id, 'verified_at' => now(),
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
    $notSubmittedPos = strpos($content, $notSubmitted->name);
    $verifiedPos = strpos($content, $verified->name);
    expect($notSubmittedPos)->not->toBeFalse();
    expect($verifiedPos)->not->toBeFalse();
    expect($notSubmittedPos)->toBeLessThan($verifiedPos);
});

test('PTO Provincial Reports page is read-only and can switch municipalities', function () {
    $listing = makeEstablishmentListing('Caraga', 'CAR2', 'Riverside Resort');
    $municipality = $listing->municipalityRecord;
    $establishmentUser = makeEstablishmentUser($listing);
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    $report = MonthlyArrivalReport::query()->create([
        'listing_id' => $listing->id, 'municipality_id' => $municipality->id,
        'period_month' => '2026-09-01', 'submission_source' => ReportSubmissionSource::ManualPaper,
        'status' => MonthlyReportStatus::ForReview, 'total_visitors' => 7,
        'submitted_by' => $pto->id, 'submitted_at' => now(),
    ]);

    $response = test()->actingAs($pto)->get(route('pto.monthlyReports.index', [
        'period' => '2026-09', 'municipality_id' => $municipality->id,
    ]));

    $response->assertOk();
    $response->assertSee($listing->name);
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
        'listing_id' => $listing->id, 'municipality_id' => $municipality->id,
        'period_month' => '2026-09-01', 'submission_source' => ReportSubmissionSource::ManualPaper,
        'status' => MonthlyReportStatus::ForReview, 'party_male' => 10, 'party_female' => 5, 'total_visitors' => 15,
        'submitted_by' => $lgu->id, 'submitted_at' => now(),
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

    expect($report->fresh()->total_visitors)->toBe(25);

    $log = OperationLog::where('entity_type', 'monthly_arrival_report')->where('entity_id', $report->id)->where('action', 'update')->first();
    expect($log)->not->toBeNull();
    expect($log->reason)->toBe('Miscounted male visitors on the paper report.');
    expect($log->old_values['party_male'])->toBe(10);
    expect($log->new_values['party_male'])->toBe(20);
});

test('correcting a Verified report reverts it to For Review', function () {
    $listing = makeEstablishmentListing('Baganga', 'BAG2', 'ABC Resort');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);
    $establishmentUser = makeEstablishmentUser($listing);

    $report = MonthlyArrivalReport::query()->create([
        'listing_id' => $listing->id, 'municipality_id' => $municipality->id,
        'period_month' => '2026-09-01', 'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::Verified, 'total_visitors' => 15,
        'submitted_by' => $establishmentUser->id, 'submitted_at' => now(),
        'verified_by' => $lgu->id, 'verified_at' => now(),
    ]);

    test()->actingAs($lgu)->put(route('lgu.monthlyReports.update', $report), [
        'party_male' => 1, 'party_female' => 1, 'party_adults' => 0, 'party_children' => 0,
        'party_seniors' => 0, 'party_local' => 0, 'party_foreign' => 0,
        'reason' => 'Correcting after PTO returned the consolidated report.',
    ])->assertRedirect();

    $fresh = $report->fresh();
    expect($fresh->status)->toBe(MonthlyReportStatus::ForReview);
    expect($fresh->verified_by)->toBeNull();
    expect($fresh->verified_at)->toBeNull();
});

test('a report cannot be corrected while part of a still-pending or already-approved municipal report', function () {
    $listing = makeEstablishmentListing('Cateel', 'CAT2', 'ABC Resort');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);
    $establishmentUser = makeEstablishmentUser($listing);

    $municipalReport = MunicipalReport::query()->create([
        'municipality' => $municipality->name, 'municipality_id' => $municipality->id,
        'submitted_by' => $lgu->id, 'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
        'total_arrivals' => 15, 'status' => MunicipalReport::STATUS_SUBMITTED,
    ]);
    $report = MonthlyArrivalReport::query()->create([
        'listing_id' => $listing->id, 'municipality_id' => $municipality->id,
        'period_month' => '2026-09-01', 'submission_source' => ReportSubmissionSource::Digital,
        'status' => MonthlyReportStatus::Verified, 'total_visitors' => 15,
        'submitted_by' => $establishmentUser->id, 'submitted_at' => now(),
        'verified_by' => $lgu->id, 'verified_at' => now(),
        'municipal_report_id' => $municipalReport->id,
    ]);

    test()->actingAs($lgu)->get(route('lgu.monthlyReports.edit', $report))->assertForbidden();
    test()->actingAs($lgu)->put(route('lgu.monthlyReports.update', $report), ['reason' => 'x'])->assertForbidden();

    $municipalReport->update(['status' => MunicipalReport::STATUS_RETURNED]);
    test()->actingAs($lgu)->get(route('lgu.monthlyReports.edit', $report->fresh()))->assertOk();

    $municipalReport->update(['status' => MunicipalReport::STATUS_APPROVED]);
    test()->actingAs($lgu)->get(route('lgu.monthlyReports.edit', $report->fresh()))->assertForbidden();
});

test('verification history shows on both the LGU and PTO report detail pages', function () {
    $listing = makeEstablishmentListing('San Isidro', 'SAN2', 'ABC Resort');
    $municipality = $listing->municipalityRecord;
    $lgu = makeLguUser($municipality);
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    test()->actingAs($lgu)->post(route('lgu.monthlyReports.manualEntry.store', $listing), [
        'period_month' => '2026-09',
        'party_male' => 5, 'party_female' => 5, 'party_adults' => 10,
        'party_children' => 0, 'party_seniors' => 0, 'party_local' => 10, 'party_foreign' => 0,
    ]);
    $report = MonthlyArrivalReport::query()->where('listing_id', $listing->id)->sole();

    test()->actingAs($lgu)->patch(route('lgu.monthlyReports.verify', $report));

    $lguResponse = test()->actingAs($lgu)->get(route('lgu.monthlyReports.show', $report));
    $lguResponse->assertSee('Verification History');
    $lguResponse->assertSee('Create');
    $lguResponse->assertSee('Validate');

    $ptoResponse = test()->actingAs($pto)->get(route('pto.monthlyReports.show', $report));
    $ptoResponse->assertSee('Verification History');
});
