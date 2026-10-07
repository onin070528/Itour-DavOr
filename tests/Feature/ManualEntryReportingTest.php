<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Phase 4 — Tourism Reports: Manual Entry of paper reports (Manual/Paper establishments only,
 *              Save Draft -> Preview -> Submit), the shared verification workflow, and verified-only municipal totals.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use App\Enums\MonthlyReportStatus;
use App\Enums\ReportingMethod;
use App\Enums\ReportSubmissionSource;
use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\MonthlyArrivalReport;
use App\Models\Municipality;
use App\Models\MunicipalReport;
use App\Models\OperationLog;
use App\Models\SecurityLog;
use App\Models\User;
use App\Support\ManualReportForm;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

function manualEntryMunicipality(string $strName, string $strCode): Municipality
{
    return Municipality::query()->firstOrCreate(['mun_code' => $strCode], ['mun_name' => $strName]);
}

function manualEntryLgu(Municipality $objMunicipality): User
{
    return User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_name' => "{$objMunicipality->mun_name} LGU",
        'usr_organization_subtitle' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
    ]);
}

/**
 * A Manual/Paper establishment (the default reporting method).
 *
 * @param  array<string, mixed>  $arrOverrides
 */
function manualEntryEstablishment(Municipality $objMunicipality, string $strName, array $arrOverrides = []): Listing
{
    return Listing::query()->create(array_merge([
        'lst_slug' => Str::slug("{$strName}-".Str::random(6)),
        'lst_name' => $strName,
        'lst_category' => 'accommodation',
        'lst_municipality' => $objMunicipality->mun_name,
        'mun_id' => $objMunicipality->mun_id,
        'lst_barangay' => 'Poblacion',
        'lst_status' => 'PUBLISHED',
    ], $arrOverrides));
}

function manualEntryOnlineEstablishment(Municipality $objMunicipality, string $strName): Listing
{
    $objListing = manualEntryEstablishment($objMunicipality, $strName);
    $objListing->forceFill(['lst_reporting_mode' => ReportingMethod::OnlineItour])->save();

    return $objListing->fresh();
}

/**
 * A report row in the given state, for setting up a month directly.
 */
function manualEntryReport(Listing $objListing, MonthlyReportStatus $enmStatus, int $intTotal, ReportSubmissionSource $enmSource = ReportSubmissionSource::ManualPaper, ?User $objSubmitter = null): MonthlyArrivalReport
{
    $blnIsSubmitted = $enmStatus !== MonthlyReportStatus::Draft;

    return MonthlyArrivalReport::query()->create([
        'lst_id' => $objListing->lst_id,
        'mun_id' => $objListing->mun_id,
        'mar_period_month' => '2026-09-01',
        'mar_submission_source' => $enmSource,
        'mar_status' => $enmStatus,
        'mar_party_male' => $intTotal, 'mar_party_female' => 0, 'mar_party_adults' => $intTotal,
        'mar_party_local' => $intTotal, 'mar_total_visitors' => $intTotal,
        'mar_submitted_by' => $blnIsSubmitted ? ($objSubmitter ?? User::factory()->create())->usr_id : null,
        'mar_submitted_at' => $blnIsSubmitted ? now() : null,
        'mar_verified_at' => $enmStatus === MonthlyReportStatus::Verified ? now() : null,
    ]);
}

/**
 * @return array<string, mixed>
 */
function manualEntryPayload(array $arrOverrides = []): array
{
    return array_merge([
        'period_month' => '2026-09',
        'party_male' => 40, 'party_female' => 60,
        'party_adults' => 80, 'party_children' => 15, 'party_seniors' => 5,
        'party_local' => 90, 'party_foreign' => 10,
    ], $arrOverrides);
}

// --- Manual Entry is for Manual/Paper establishments only ---

test('the Manual Entry page lists only the LGU\'s own Manual/Paper establishments', function () {
    $objMati = manualEntryMunicipality('City of Mati', 'MATI');
    $objBaganga = manualEntryMunicipality('Baganga', 'BAG');
    manualEntryEstablishment($objMati, 'Paper Inn');
    manualEntryOnlineEstablishment($objMati, 'Online Resort');
    manualEntryEstablishment($objMati, 'Mati Falls', ['lst_category' => 'destinations', 'lst_status' => 'Active']);
    manualEntryEstablishment($objBaganga, 'Baganga Paper Lodge');

    test()->actingAs(manualEntryLgu($objMati))->get(route('lgu.monthlyReports.manualEntry.index', ['period' => '2026-09']))
        ->assertOk()
        ->assertSee('Paper Inn')
        ->assertSee('Not encoded')
        ->assertSee('1 Online iTOUR establishment submits its own report and is not listed here.')
        ->assertDontSee('Online Resort')
        ->assertDontSee('Mati Falls')
        ->assertDontSee('Baganga Paper Lodge');
});

test('an Online iTOUR establishment cannot use Manual Entry — no second source for its month', function () {
    $objMati = manualEntryMunicipality('City of Mati', 'MATI');
    $objLgu = manualEntryLgu($objMati);
    $objListing = manualEntryOnlineEstablishment($objMati, 'Online Resort');

    test()->actingAs($objLgu)->get(route('lgu.monthlyReports.manualEntry', ['listing' => $objListing, 'period' => '2026-09']))
        ->assertRedirect(route('lgu.monthlyReports.manualEntry.index', ['period' => '2026-09']))
        ->assertSessionHas('toast_tone', 'danger');

    test()->actingAs($objLgu)->post(route('lgu.monthlyReports.manualEntry.store', $objListing), manualEntryPayload())
        ->assertRedirect(route('lgu.monthlyReports.manualEntry.index', ['period' => '2026-09']))
        ->assertSessionHas('toast_tone', 'danger');

    expect(MonthlyArrivalReport::query()->where('lst_id', $objListing->lst_id)->exists())->toBeFalse();
});

test('the Monthly Reports table offers Manual Entry only for Manual/Paper establishments', function () {
    $objMati = manualEntryMunicipality('City of Mati', 'MATI');
    $objPaper = manualEntryEstablishment($objMati, 'Paper Inn');
    $objOnline = manualEntryOnlineEstablishment($objMati, 'Online Resort');

    test()->actingAs(manualEntryLgu($objMati))->get(route('lgu.monthlyReports.index', ['period' => '2026-09']))
        ->assertOk()
        ->assertSee(route('lgu.monthlyReports.manualEntry', ['listing' => $objPaper, 'period' => '2026-09']), false)
        ->assertDontSee(route('lgu.monthlyReports.manualEntry', ['listing' => $objOnline, 'period' => '2026-09']), false)
        ->assertSee('Awaiting establishment')
        ->assertSee('0 of 2 establishments verified');
});

// --- Draft -> Preview -> Submit -> existing verification ---

test('a paper report is saved as a Draft, previewed, submitted, then verified through the existing workflow', function () {
    $objMati = manualEntryMunicipality('City of Mati', 'MATI');
    $objLgu = manualEntryLgu($objMati);
    $objListing = manualEntryEstablishment($objMati, 'Paper Inn');

    test()->actingAs($objLgu)->get(route('lgu.monthlyReports.manualEntry', ['listing' => $objListing, 'period' => '2026-09']))
        ->assertOk()
        ->assertSee('Provisional fields')
        ->assertSee('Save Draft')
        ->assertSee('Save &amp; Preview', false);

    // Save Draft — stays on the form.
    test()->actingAs($objLgu)->post(route('lgu.monthlyReports.manualEntry.store', $objListing), [...manualEntryPayload(), 'intent' => 'draft'])
        ->assertRedirect(route('lgu.monthlyReports.manualEntry', ['listing' => $objListing, 'period' => '2026-09']))
        ->assertSessionHasNoErrors();

    $objReport = MonthlyArrivalReport::query()->where('lst_id', $objListing->lst_id)->sole();
    expect($objReport->mar_status)->toBe(MonthlyReportStatus::Draft);
    expect($objReport->mar_submission_source)->toBe(ReportSubmissionSource::ManualPaper);
    expect($objReport->mar_total_visitors)->toBe(100);
    expect($objReport->mar_submitted_by)->toBeNull();
    expect($objReport->mar_submitted_at)->toBeNull();
    expect(OperationLog::query()->where('opl_entity_type', 'monthly_arrival_report')->where('opl_entity_id', $objReport->mar_id)->where('opl_action', 'create')->value('usr_id'))->toBe($objLgu->usr_id);

    // Editing the draft updates the same report; Save & Preview opens the A4 preview.
    test()->actingAs($objLgu)->post(route('lgu.monthlyReports.manualEntry.store', $objListing), [...manualEntryPayload(['party_male' => 50]), 'intent' => 'preview'])
        ->assertRedirect(route('lgu.monthlyReports.show', ['monthlyArrivalReport' => $objReport, 'view' => 'a4']));
    expect(MonthlyArrivalReport::query()->where('lst_id', $objListing->lst_id)->count())->toBe(1);
    expect($objReport->fresh()->mar_total_visitors)->toBe(110);
    expect($objReport->fresh()->mar_status)->toBe(MonthlyReportStatus::Draft);

    test()->actingAs($objLgu)->get(route('lgu.monthlyReports.show', ['monthlyArrivalReport' => $objReport, 'view' => 'a4']))
        ->assertOk()
        ->assertSee(route('lgu.monthlyReports.preview', $objReport), false)
        ->assertSee(route('lgu.monthlyReports.submit', $objReport), false);
    test()->actingAs($objLgu)->get(route('lgu.monthlyReports.preview', $objReport))->assertOk()->assertSee('Paper Inn');

    // A Draft is not in the review workflow yet.
    test()->actingAs($objLgu)->patch(route('lgu.monthlyReports.review', $objReport))->assertForbidden();
    test()->actingAs($objLgu)->patch(route('lgu.monthlyReports.verify', $objReport))->assertForbidden();

    // Submit — stamps the LGU encoder and time.
    test()->actingAs($objLgu)->patch(route('lgu.monthlyReports.submit', $objReport))->assertRedirect(route('lgu.monthlyReports.show', $objReport));
    $objReport->refresh();
    expect($objReport->mar_status)->toBe(MonthlyReportStatus::Submitted);
    expect($objReport->mar_submitted_by)->toBe($objLgu->usr_id);
    expect($objReport->mar_submitted_at)->not->toBeNull();
    expect($objReport->mar_submission_source)->toBe(ReportSubmissionSource::ManualPaper);

    // The same Review -> Verify path as an online report.
    test()->actingAs($objLgu)->patch(route('lgu.monthlyReports.review', $objReport))->assertRedirect();
    test()->actingAs($objLgu)->patch(route('lgu.monthlyReports.verify', $objReport))->assertRedirect();
    $objReport->refresh();
    expect($objReport->mar_status)->toBe(MonthlyReportStatus::Verified);
    expect($objReport->mar_verified_by)->toBe($objLgu->usr_id);

    // A paper submission is a monthly report, never individual arrival records.
    expect($objListing->arrivals()->count())->toBe(0);
});

test('Manual Entry validates every paper-report field on the server', function () {
    $objMati = manualEntryMunicipality('City of Mati', 'MATI');
    $objLgu = manualEntryLgu($objMati);
    $objListing = manualEntryEstablishment($objMati, 'Paper Inn');

    test()->actingAs($objLgu)->post(route('lgu.monthlyReports.manualEntry.store', $objListing), manualEntryPayload(['party_male' => -1, 'party_foreign' => 'many']))
        ->assertSessionHasErrors(['party_male', 'party_foreign']);

    $arrMissing = manualEntryPayload();
    unset($arrMissing['party_seniors']);
    test()->actingAs($objLgu)->post(route('lgu.monthlyReports.manualEntry.store', $objListing), $arrMissing)->assertSessionHasErrors('party_seniors');

    expect(MonthlyArrivalReport::query()->where('lst_id', $objListing->lst_id)->exists())->toBeFalse();
    expect(array_keys(ManualReportForm::rules()))->toBe(array_keys(ManualReportForm::fields()));
});

test('a paper draft cannot be submitted once its establishment has switched to Online iTOUR', function () {
    $objMati = manualEntryMunicipality('City of Mati', 'MATI');
    $objLgu = manualEntryLgu($objMati);
    $objListing = manualEntryEstablishment($objMati, 'Switching Inn');
    $objReport = manualEntryReport($objListing, MonthlyReportStatus::Draft, 25);
    $objListing->forceFill(['lst_reporting_mode' => ReportingMethod::OnlineItour])->save();

    test()->actingAs($objLgu)->patch(route('lgu.monthlyReports.submit', $objReport))->assertSessionHas('toast_tone', 'danger');

    expect($objReport->fresh()->mar_status)->toBe(MonthlyReportStatus::Draft);
});

// --- One report per establishment per month ---

test('an establishment keeps one report per month — a submitted month cannot be re-encoded or duplicated', function () {
    $objMati = manualEntryMunicipality('City of Mati', 'MATI');
    $objLgu = manualEntryLgu($objMati);
    $objListing = manualEntryEstablishment($objMati, 'Paper Inn');
    $objReport = manualEntryReport($objListing, MonthlyReportStatus::Submitted, 30, ReportSubmissionSource::ManualPaper, $objLgu);

    test()->actingAs($objLgu)->get(route('lgu.monthlyReports.manualEntry', ['listing' => $objListing, 'period' => '2026-09']))->assertStatus(422);
    test()->actingAs($objLgu)->post(route('lgu.monthlyReports.manualEntry.store', $objListing), manualEntryPayload())->assertSessionHas('toast_tone', 'danger');

    expect(MonthlyArrivalReport::query()->where('lst_id', $objListing->lst_id)->count())->toBe(1);
    expect($objReport->fresh()->mar_total_visitors)->toBe(30);

    // The database itself refuses a second row for the same establishment and month.
    expect(fn () => manualEntryReport($objListing, MonthlyReportStatus::Draft, 1))->toThrow(UniqueConstraintViolationException::class);
});

test('a month already reported online is not re-encoded on paper after the establishment switches to Manual/Paper', function () {
    $objMati = manualEntryMunicipality('City of Mati', 'MATI');
    $objLgu = manualEntryLgu($objMati);
    $objListing = manualEntryEstablishment($objMati, 'Former Online Inn');
    manualEntryReport($objListing, MonthlyReportStatus::Submitted, 12, ReportSubmissionSource::Digital);

    test()->actingAs($objLgu)->get(route('lgu.monthlyReports.manualEntry', ['listing' => $objListing, 'period' => '2026-09']))->assertStatus(422);
    test()->actingAs($objLgu)->post(route('lgu.monthlyReports.manualEntry.store', $objListing), manualEntryPayload())->assertSessionHas('toast_tone', 'danger');

    $objOnly = MonthlyArrivalReport::query()->where('lst_id', $objListing->lst_id)->sole();
    expect($objOnly->mar_submission_source)->toBe(ReportSubmissionSource::Digital);
    expect($objOnly->mar_total_visitors)->toBe(12);
});

// --- Municipality scoping and forged IDs ---

test('an LGU cannot encode, view, or submit paper reports for another municipality (403 and a security log)', function () {
    $objMati = manualEntryMunicipality('City of Mati', 'MATI');
    $objBaganga = manualEntryMunicipality('Baganga', 'BAG');
    $objMatiLgu = manualEntryLgu($objMati);
    $objBagangaListing = manualEntryEstablishment($objBaganga, 'Baganga Paper Lodge');
    $objBagangaDraft = manualEntryReport(manualEntryEstablishment($objBaganga, 'Baganga Draft Inn'), MonthlyReportStatus::Draft, 9);

    test()->actingAs($objMatiLgu)->get(route('lgu.monthlyReports.manualEntry', ['listing' => $objBagangaListing, 'period' => '2026-09']))->assertForbidden();
    test()->actingAs($objMatiLgu)->post(route('lgu.monthlyReports.manualEntry.store', $objBagangaListing), manualEntryPayload())->assertForbidden();
    test()->actingAs($objMatiLgu)->get(route('lgu.monthlyReports.show', $objBagangaDraft))->assertForbidden();
    test()->actingAs($objMatiLgu)->patch(route('lgu.monthlyReports.submit', $objBagangaDraft))->assertForbidden();

    expect(MonthlyArrivalReport::query()->where('lst_id', $objBagangaListing->lst_id)->exists())->toBeFalse();
    expect($objBagangaDraft->fresh()->mar_status)->toBe(MonthlyReportStatus::Draft);
    expect(SecurityLog::query()->where('usr_id', $objMatiLgu->usr_id)->where('sec_event_type', 'access_denied')->count())->toBeGreaterThanOrEqual(2);
});

test('forged establishment, municipality, source, status, and submitter fields are ignored', function () {
    $objMati = manualEntryMunicipality('City of Mati', 'MATI');
    $objBaganga = manualEntryMunicipality('Baganga', 'BAG');
    $objLgu = manualEntryLgu($objMati);
    $objListing = manualEntryEstablishment($objMati, 'Paper Inn');
    $objOtherListing = manualEntryEstablishment($objBaganga, 'Baganga Paper Lodge');
    $objOtherUser = User::factory()->create();

    test()->actingAs($objLgu)->post(route('lgu.monthlyReports.manualEntry.store', $objListing), manualEntryPayload([
        'listing_id' => $objOtherListing->lst_id,
        'municipality_id' => $objBaganga->mun_id,
        'submission_source' => ReportSubmissionSource::Digital->value,
        'status' => MonthlyReportStatus::Verified->value,
        'submitted_by' => $objOtherUser->usr_id,
        'verified_by' => $objOtherUser->usr_id,
        'total_visitors' => 999999,
    ]))->assertSessionHasNoErrors();

    $objReport = MonthlyArrivalReport::query()->sole();
    expect($objReport->lst_id)->toBe($objListing->lst_id);
    expect($objReport->mun_id)->toBe($objMati->mun_id);
    expect($objReport->mar_submission_source)->toBe(ReportSubmissionSource::ManualPaper);
    expect($objReport->mar_status)->toBe(MonthlyReportStatus::Draft);
    expect($objReport->mar_submitted_by)->toBeNull();
    expect($objReport->mar_verified_by)->toBeNull();
    expect($objReport->mar_total_visitors)->toBe(100);
});

test('destinations never have Manual Entry (404)', function () {
    $objMati = manualEntryMunicipality('City of Mati', 'MATI');
    $objDestination = manualEntryEstablishment($objMati, 'Mati Falls', ['lst_category' => 'destinations', 'lst_status' => 'Active']);

    test()->actingAs(manualEntryLgu($objMati))->get(route('lgu.monthlyReports.manualEntry', ['listing' => $objDestination, 'period' => '2026-09']))->assertNotFound();
});

// --- Municipal Reports: verified only, missing is never zero ---

test('municipal totals count Verified reports only, and show "X of Y establishments verified"', function () {
    $objMati = manualEntryMunicipality('City of Mati', 'MATI');
    $objLgu = manualEntryLgu($objMati);
    manualEntryReport(manualEntryEstablishment($objMati, 'Verified Paper Inn'), MonthlyReportStatus::Verified, 10, ReportSubmissionSource::ManualPaper, $objLgu);
    manualEntryReport(manualEntryOnlineEstablishment($objMati, 'Verified Online Resort'), MonthlyReportStatus::Verified, 20, ReportSubmissionSource::Digital);
    manualEntryReport(manualEntryEstablishment($objMati, 'Draft Paper Inn'), MonthlyReportStatus::Draft, 100);
    manualEntryReport(manualEntryOnlineEstablishment($objMati, 'Submitted Resort'), MonthlyReportStatus::Submitted, 1000, ReportSubmissionSource::Digital);
    manualEntryReport(manualEntryOnlineEstablishment($objMati, 'For Review Resort'), MonthlyReportStatus::ForReview, 2000, ReportSubmissionSource::Digital);
    manualEntryReport(manualEntryOnlineEstablishment($objMati, 'For Correction Resort'), MonthlyReportStatus::ForCorrection, 4000, ReportSubmissionSource::Digital);
    manualEntryEstablishment($objMati, 'Missing Report Inn');

    $objResponse = test()->actingAs($objLgu)->get(route('lgu.monthlyReports.municipal.show', '2026-09'));

    $objResponse->assertOk()->assertSee('2 of 7 establishments verified');
    $arrSummary = $objResponse->viewData('summary');
    expect($arrSummary['verifiedTotal'])->toBe(30);
    expect($arrSummary['verifiedCount'])->toBe(2);
    expect($arrSummary['notSubmittedCount'])->toBe(1);
    expect($arrSummary['canSubmit'])->toBeFalse();
});

test('consolidating sums Verified reports only and never turns a missing report into a zero row', function () {
    $objMati = manualEntryMunicipality('City of Mati', 'MATI');
    $objLgu = manualEntryLgu($objMati);
    manualEntryReport(manualEntryEstablishment($objMati, 'Verified Paper Inn'), MonthlyReportStatus::Verified, 10, ReportSubmissionSource::ManualPaper, $objLgu);
    manualEntryReport(manualEntryOnlineEstablishment($objMati, 'Verified Online Resort'), MonthlyReportStatus::Verified, 20, ReportSubmissionSource::Digital);
    $objMissing = manualEntryEstablishment($objMati, 'Missing Report Inn');

    test()->actingAs($objLgu)->post(route('lgu.monthlyReports.consolidate'), ['period_month' => '2026-09'])
        ->assertRedirect(route('lgu.monthlyReports.municipal.show', '2026-09'));

    $objMunicipalReport = MunicipalReport::query()->where('mun_id', $objMati->mun_id)->sole();
    expect($objMunicipalReport->mrp_total_arrivals)->toBe(30);
    expect(MonthlyArrivalReport::query()->where('lst_id', $objMissing->lst_id)->exists())->toBeFalse();
    expect(MonthlyArrivalReport::query()->where('mrp_id', $objMunicipalReport->mrp_id)->count())->toBe(2);
});

test('an unsubmitted paper draft keeps the month from being sent to PTO', function () {
    $objMati = manualEntryMunicipality('City of Mati', 'MATI');
    $objLgu = manualEntryLgu($objMati);
    manualEntryReport(manualEntryEstablishment($objMati, 'Verified Paper Inn'), MonthlyReportStatus::Verified, 10, ReportSubmissionSource::ManualPaper, $objLgu);
    manualEntryReport(manualEntryEstablishment($objMati, 'Draft Paper Inn'), MonthlyReportStatus::Draft, 100);

    test()->actingAs($objLgu)->post(route('lgu.monthlyReports.consolidate'), ['period_month' => '2026-09'])->assertSessionHas('toast_tone', 'danger');

    expect(MunicipalReport::query()->where('mun_id', $objMati->mun_id)->exists())->toBeFalse();
});

// --- Navigation ---

test('the LGU sidebar has one Tourism Reports area with Monthly Reports, Municipal Reports, and Manual Entry', function () {
    $objMati = manualEntryMunicipality('City of Mati', 'MATI');

    test()->actingAs(manualEntryLgu($objMati))->get(route('lgu.monthlyReports.manualEntry.index'))
        ->assertOk()
        ->assertSee('Tourism Reports')
        ->assertSee(route('lgu.monthlyReports.index'), false)
        ->assertSee(route('lgu.monthlyReports.municipal'), false)
        ->assertSee(route('lgu.monthlyReports.manualEntry.index'), false);
});
