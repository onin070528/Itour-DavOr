<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: The LGU's Tourism Reports area. Monthly Reports: the
 * per-establishment status table, Review (with A4 preview), Return for
 * Correction, and Verify — digital and paper reports follow the same path.
 * Manual Entry: paper reports of Manual/Paper establishments (Save Draft ->
 * Preview -> Submit, then the same review path). Municipal Reports: the
 * LGU's own consolidated report per month, generated from Verified
 * establishment reports only (View, Preview A4, Submit to PTO).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Enums\MonthlyReportStatus;
use App\Enums\ReportSubmissionSource;
use App\Http\Controllers\Concerns\AuthorizesOwnMunicipality;
use App\Http\Controllers\Concerns\TracksReportHistory;
use App\Models\Listing;
use App\Models\MonthlyArrivalReport;
use App\Models\Municipality;
use App\Models\MunicipalReport;
use App\Models\OperationLog;
use App\Models\User;
use App\Support\ManualReportForm;
use App\Support\OfficialReportBuilder;
use App\Support\OperationLogger;
use App\Support\ReportWorkflowSteps;
use App\Support\TourismAnalytics;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MonthlyReportsController extends LguController
{
    use AuthorizesOwnMunicipality, TracksReportHistory;

    /**
     * The establishment × month status table for the account's municipality.
     * Establishments with no MonthlyArrivalReport row for the selected
     * period are shown as "Not Submitted" — a display-only state computed
     * here, never written to the database.
     */
    public function index(Request $objRequest): View
    {
        $objUser = $objRequest->user();
        $dtmMonth = $this->resolvePeriod($objRequest);

        $objRows = $this->_establishmentRows($objUser, $dtmMonth);
        $arrSummary = $this->_municipalSummary($objUser, $dtmMonth, $objRows);

        return $this->renderLgu($objRequest, 'lgu.monthly-reports.index', 'reports.monthly', 'Monthly Reports', [
            'rows' => $objRows,
            'month' => $dtmMonth,
            'monthOptions' => $this->recentMonthOptions(),
            'summary' => $arrSummary,
            'missingCount' => $arrSummary['notSubmittedCount'],
            'forReviewCount' => $arrSummary['awaitingReviewCount'],
            'verifiedCount' => $arrSummary['verifiedCount'],
            'submittedCount' => $arrSummary['submittedCount'],
            'alreadyConsolidated' => $arrSummary['isSentToPto'],
            // For Correction reports are still in the review loop, so they
            // keep "Review & verify" as the current step alongside For Review.
            'steps' => ReportWorkflowSteps::compute(
                $arrSummary['submittedCount'],
                $arrSummary['awaitingReviewCount'] + $arrSummary['forCorrectionCount'],
                $arrSummary['verifiedCount'],
                $arrSummary['isSentToPto'],
            ),
        ]);
    }

    /**
     * Municipal Reports (own sidebar item): the municipality's official
     * consolidated reporting record and tourism monitoring area for one
     * year, in three sections — Overview (yearly totals, trend, year
     * comparison), Monthly Records (one consolidated report per month with
     * View / Preview A4, plus the quarterly summary) and Statistics (by
     * visitor classification, category, establishment). Every figure comes
     * from Verified establishment reports only; nothing is re-entered.
     */
    public function municipalIndex(Request $request): View
    {
        $objUser = $request->user();
        $arrYearOptions = TourismAnalytics::scopedYearOptions($objUser->mun_id);
        $intYear = in_array((int) $request->query('year'), $arrYearOptions, true) ? (int) $request->query('year') : CarbonImmutable::now()->year;
        $strSection = in_array($request->query('section'), ['records', 'statistics'], true) ? $request->query('section') : 'overview';

        $arrFilters = ['year' => $intYear, 'month' => null, 'municipalityId' => $objUser->mun_id, 'listingId' => null, 'classification' => null];
        $objRecords = TourismAnalytics::monthlyRecords($arrFilters);
        $objPreviousRecords = TourismAnalytics::monthlyRecords([...$arrFilters, 'year' => $intYear - 1]);
        $blnHasPrevious = $objPreviousRecords->contains('hasData', true);

        // Monthly Records: the consolidated report status for each month of
        // the year that has started (future months have nothing to show).
        $objEstablishments = $this->_establishments($objUser);
        $dtNow = CarbonImmutable::now()->startOfMonth();
        $objMonths = collect(range(12, 1))
            ->map(fn (int $intMonth) => CarbonImmutable::create($intYear, $intMonth, 1))
            ->reject(fn (CarbonImmutable $dtMonth) => $dtMonth->greaterThan($dtNow))
            ->map(fn (CarbonImmutable $dtMonth) => [
                'month' => $dtMonth,
                'record' => $objRecords->firstWhere('month', $dtMonth->month),
                ...$this->_municipalSummary($objUser, $dtMonth, $this->_establishmentRows($objUser, $dtMonth, $objEstablishments)),
            ])
            ->values();

        $objEstablishmentBreakdown = TourismAnalytics::establishmentBreakdown($arrFilters);

        return $this->renderLgu($request, 'lgu.monthly-reports.municipal-index', 'reports.municipal', 'Municipal Reports', [
            'year' => $intYear,
            'yearOptions' => $arrYearOptions,
            'section' => $strSection,
            'records' => $objRecords,
            'summary' => TourismAnalytics::yearSummary($objRecords),
            'quarters' => TourismAnalytics::quarterlySummary($objRecords),
            'hasPrevious' => $blnHasPrevious,
            'compare' => $request->boolean('compare') && $blnHasPrevious,
            'previousRecords' => $objPreviousRecords,
            'yearComparison' => TourismAnalytics::yearComparison($objRecords, $objPreviousRecords, $intYear),
            'months' => $objMonths,
            'establishmentCount' => $objEstablishments->count(),
            'reportingEstablishmentCount' => $objEstablishmentBreakdown->count(),
            'verifiedReportCount' => (int) $objRecords->sum('reportCount'),
            'establishmentBreakdown' => $objEstablishmentBreakdown,
            'categoryBreakdown' => TourismAnalytics::categoryBreakdown($arrFilters),
            'visitorBreakdown' => TourismAnalytics::visitorBreakdown($arrFilters),
        ]);
    } // end municipalIndex

    /**
     * View Municipal Report: the consolidated report for one month — every
     * establishment's status, which Verified reports make up the municipal
     * total, any column-sum problems PTO would reject, PTO's remarks if it
     * returned the report, and the Preview A4 / Submit to PTO actions.
     */
    public function municipalShow(Request $request, string $period): View
    {
        $objUser = $request->user();
        $dtMonth = CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth();

        $objRows = $this->_establishmentRows($objUser, $dtMonth);
        $arrSummary = $this->_municipalSummary($objUser, $dtMonth, $objRows);
        $arrReportData = $this->_municipalReportData($objUser, $dtMonth, $arrSummary);

        $arrBalanceErrors = OfficialReportBuilder::validateColumnSums(
            $arrReportData['groups']->flatMap(fn (array $arrGroup) => $arrGroup['rows'])
        );

        $objHistory = $arrSummary['municipalReport']
            ? OperationLog::query()
                ->where('opl_entity_type', 'municipal_report')
                ->where('opl_entity_id', $arrSummary['municipalReport']->mrp_id)
                ->with('user')
                ->orderByDesc('opl_created_at')
                ->get()
            : collect();

        return $this->renderLgu($request, 'lgu.monthly-reports.municipal-show', 'reports.municipal', 'Municipal Report', [
            'month' => $dtMonth,
            'rows' => $objRows,
            'summary' => $arrSummary,
            'grandTotal' => $arrReportData['grand_total'],
            'balanceErrors' => $arrBalanceErrors,
            'history' => $objHistory,
            'visitorBreakdown' => TourismAnalytics::visitorBreakdown([
                'year' => $dtMonth->year, 'month' => $dtMonth->month,
                'municipalityId' => $objUser->mun_id, 'listingId' => null, 'classification' => null,
            ]),
        ]);
    } // end municipalShow

    /**
     * Preview A4: the municipal report in the official A4 layout — live
     * from Verified establishment reports before it is sent, or the saved
     * (and, once PTO verifies it, frozen) copy afterwards.
     */
    public function municipalPreview(Request $request, string $period): View
    {
        $objUser = $request->user();
        $dtMonth = CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth();

        $arrSummary = $this->_municipalSummary($objUser, $dtMonth, $this->_establishmentRows($objUser, $dtMonth));

        OperationLogger::exported($objUser, 'municipal_report', $objUser->mun_id, ['action' => 'preview', 'period' => $dtMonth->toDateString()]);

        return view('pdf.official-report', [
            'report' => $this->_municipalReportData($objUser, $dtMonth, $arrSummary),
            'preview' => true,
            'backUrl' => route('lgu.monthlyReports.municipal.show', $dtMonth->format('Y-m')),
            'pdfUrl' => route('lgu.monthlyReports.municipal.pdf', $dtMonth->format('Y-m')),
        ]);
    } // end municipalPreview

    /**
     * Download PDF of the municipal report — the same A4 document as the
     * preview, through the existing DomPDF setup.
     */
    public function municipalPdf(Request $request, string $period): Response
    {
        $objUser = $request->user();
        $dtMonth = CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth();

        $arrSummary = $this->_municipalSummary($objUser, $dtMonth, $this->_establishmentRows($objUser, $dtMonth));
        $arrReport = $this->_municipalReportData($objUser, $dtMonth, $arrSummary);

        OperationLogger::exported($objUser, 'municipal_report', $objUser->mun_id, ['action' => 'download_pdf', 'period' => $dtMonth->toDateString()]);

        try {
            $strFileName = $arrSummary['isSentToPto'] ? $arrReport['reference_number'] : 'Municipal-Report-'.$dtMonth->format('Y-m');

            return Pdf::loadView('pdf.official-report', ['report' => $arrReport, 'preview' => false])
                ->setPaper('a4')
                ->download("{$strFileName}.pdf");
        } catch (\Throwable $e) {
            Log::error('Failed to generate municipal report PDF.', ['exception' => $e, 'period' => $period]);

            abort(500, 'The PDF could not be generated. Please use Print instead.');
        }
    } // end municipalPdf

    /**
     * Manual Entry (Tourism Reports -> Manual Entry): the month's Manual/Paper
     * establishments and where each one's paper report stands — Not
     * encoded, Draft, or already in the review workflow. Online iTOUR
     * establishments are not listed: they submit their own reports, and a
     * second (paper) source for the same month is never allowed.
     */
    public function manualEntryIndex(Request $request): View
    {
        $objUser = $request->user();
        $dtMonth = $this->resolvePeriod($request);

        [$objPaperRows, $objOnlineRows] = $this->_establishmentRows($objUser, $dtMonth)
            ->partition(fn (array $arrRow) => ! $arrRow['listing']->reportingMethod()->isOnline());

        return $this->renderLgu($request, 'lgu.monthly-reports.manual-entry-index', 'reports.manualEntry', 'Manual Entry', [
            'rows' => $objPaperRows->values(),
            'onlineCount' => $objOnlineRows->count(),
            'month' => $dtMonth,
            'monthOptions' => $this->recentMonthOptions(),
        ]);
    } // end manualEntryIndex

    /**
     * The paper-report encoding form for one Manual/Paper establishment +
     * month (Source is always Manual/Paper). A month that already has a
     * report in the workflow (digital or paper), or an establishment's own
     * digital draft, is not re-encoded here; this office's paper Draft is
     * reopened for editing instead.
     */
    public function showManualEntry(Request $objRequest, Listing $listing): View|RedirectResponse
    {
        $this->_authorizeManualEntryListing($objRequest, $listing);

        $dtmMonth = $this->resolvePeriod($objRequest);

        if ($listing->reportingMethod()->isOnline()) {
            return $this->_onlineEstablishmentRedirect($listing, $dtmMonth);
        }

        $objExisting = $listing->monthlyArrivalReports()->forPeriod($dtmMonth)->first();

        abort_if($objExisting && ! $objRequest->user()->can('editDraft', $objExisting), 422, 'This establishment already has a report (or a draft in progress) for that month.');

        return $this->renderLgu($objRequest, 'lgu.monthly-reports.manual-entry', 'reports.manualEntry', 'Manual Entry', [
            'listing' => $listing,
            'month' => $dtmMonth,
            'report' => $objExisting,
            'fieldGroups' => ManualReportForm::groups(),
            'blnIsProvisional' => ManualReportForm::IS_PROVISIONAL,
        ]);
    }

    /**
     * Save Draft: stores the encoded paper report as a Draft (Source =
     * Manual/Paper) — creating the month's report or updating this office's
     * existing Draft. Nothing reaches the review workflow until it is
     * previewed and submitted (submit(), below), which stamps the LGU
     * encoder and submission time. One report per establishment per month
     * is kept by the unique (lst_id, mar_period_month) index.
     */
    public function storeManualEntry(Request $objRequest, Listing $listing): RedirectResponse
    {
        $this->_authorizeManualEntryListing($objRequest, $listing);

        $arrData = $objRequest->validate([
            'period_month' => ['required', 'date_format:Y-m'],
            'intent' => ['nullable', 'in:draft,preview'],
            ...ManualReportForm::rules(),
        ]);

        $dtmMonth = CarbonImmutable::createFromFormat('Y-m', $arrData['period_month'])->startOfMonth();

        if ($listing->reportingMethod()->isOnline()) {
            return $this->_onlineEstablishmentRedirect($listing, $dtmMonth);
        }

        $objExisting = $listing->monthlyArrivalReports()->forPeriod($dtmMonth)->first();

        if ($objExisting && ! $objRequest->user()->can('editDraft', $objExisting)) {
            return back()->with('toast', "{$listing->lst_name} already has a report for {$dtmMonth->format('F Y')}.")->with('toast_tone', 'danger');
        }

        $arrFigures = ManualReportForm::figures($arrData);

        try {
            if ($objExisting) {
                $arrBefore = $objExisting->getOriginal();
                $objExisting->update($arrFigures);
                $objReport = $objExisting;

                OperationLogger::updated(
                    $objRequest->user(),
                    'monthly_arrival_report',
                    $objReport->mar_id,
                    $listing->mun_id,
                    $listing->lst_id,
                    OperationLogger::diff($arrBefore, $objReport),
                );
            } else {
                $objReport = MonthlyArrivalReport::query()->create([
                    'lst_id' => $listing->lst_id,
                    'mun_id' => $listing->mun_id,
                    'mar_period_month' => $dtmMonth->toDateString(),
                    'mar_submission_source' => ReportSubmissionSource::ManualPaper,
                    'mar_status' => MonthlyReportStatus::Draft,
                    ...$arrFigures,
                ]);

                OperationLogger::created(
                    $objRequest->user(),
                    'monthly_arrival_report',
                    $objReport->mar_id,
                    $listing->mun_id,
                    $listing->lst_id,
                    [
                        'period_month' => $dtmMonth->toDateString(),
                        'submission_source' => ReportSubmissionSource::ManualPaper->value,
                        'status' => MonthlyReportStatus::Draft->value,
                        'total_visitors' => $objReport->mar_total_visitors,
                    ],
                );
            }
        } catch (UniqueConstraintViolationException) {
            // Another report for this establishment and month was saved in the meantime.
            return back()->withInput()->with('toast', "{$listing->lst_name} already has a report for {$dtmMonth->format('F Y')}.")->with('toast_tone', 'danger');
        }

        if (($arrData['intent'] ?? 'draft') === 'preview') {
            return redirect()->route('lgu.monthlyReports.show', ['monthlyArrivalReport' => $objReport, 'view' => 'a4'])
                ->with('toast', 'Draft saved. Check the preview against the paper report, then submit.');
        }

        return redirect()->route('lgu.monthlyReports.manualEntry', ['listing' => $listing, 'period' => $dtmMonth->format('Y-m')])
            ->with('toast', "Draft saved for {$listing->lst_name}, {$dtmMonth->format('F Y')}.");
    }

    /**
     * Submit: sends this office's paper Draft into the same review queue as
     * a digital submission (status Submitted), stamping the LGU encoder and
     * the submission time. From there it follows the identical Review ->
     * Verify -> Municipal consolidation path.
     */
    public function submit(Request $request, MonthlyArrivalReport $monthlyArrivalReport): RedirectResponse
    {
        abort_unless(
            $request->user()->can('submit', $monthlyArrivalReport),
            403,
            'Only a draft paper report encoded by your office can be submitted.'
        );

        if ($monthlyArrivalReport->listing->reportingMethod()->isOnline()) {
            return back()
                ->with('toast', "{$monthlyArrivalReport->listing->lst_name} now reports through Online iTOUR, so this paper draft can't be submitted. Its own report for that month comes through iTOUR.")
                ->with('toast_tone', 'danger');
        }

        $arrBefore = $monthlyArrivalReport->getOriginal();

        $monthlyArrivalReport->update([
            'mar_status' => MonthlyReportStatus::Submitted,
            'mar_submitted_by' => $request->user()->usr_id,
            'mar_submitted_at' => now(),
        ]);

        OperationLogger::submitted(
            $request->user(),
            'monthly_arrival_report',
            $monthlyArrivalReport->mar_id,
            $monthlyArrivalReport->mun_id,
            OperationLogger::diff($arrBefore, $monthlyArrivalReport),
            $monthlyArrivalReport->lst_id,
        );

        return redirect()->route('lgu.monthlyReports.show', $monthlyArrivalReport)
            ->with('toast', 'Report submitted. Review and verify it once you have checked it against the paper report.');
    } // end submit

    /**
     * Review: the LGU opens a newly Submitted report, which moves it to For
     * Review (logged) so the establishment and PTO can see it is being
     * checked. Verify / Return for Correction are available from there.
     */
    public function startReview(Request $request, MonthlyArrivalReport $monthlyArrivalReport): RedirectResponse
    {
        abort_unless(
            $request->user()->can('startReview', $monthlyArrivalReport),
            403,
            'Only a newly submitted report can be opened for review.'
        );

        $arrBefore = $monthlyArrivalReport->getOriginal();

        $monthlyArrivalReport->update(['mar_status' => MonthlyReportStatus::ForReview]);

        OperationLogger::updated(
            $request->user(),
            'monthly_arrival_report',
            $monthlyArrivalReport->mar_id,
            $monthlyArrivalReport->mun_id,
            $monthlyArrivalReport->lst_id,
            OperationLogger::diff($arrBefore, $monthlyArrivalReport),
        );

        return redirect()->route('lgu.monthlyReports.show', $monthlyArrivalReport);
    } // end startReview

    /**
     * Establishment report in the A4 official layout (shown in the review
     * page's "A4 Preview" tab, and printable on its own).
     */
    public function preview(Request $request, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        abort_unless($request->user()->can('view', $monthlyArrivalReport), 403);

        return view('pdf.official-report', [
            'report' => OfficialReportBuilder::fromMonthlyArrivalReport($monthlyArrivalReport),
            'preview' => true,
            'pdfUrl' => route('lgu.monthlyReports.pdf', $monthlyArrivalReport),
        ]);
    } // end preview

    /**
     * Download PDF of an establishment report — the same A4 document as
     * the Report Preview, through the existing DomPDF setup.
     */
    public function pdf(Request $request, MonthlyArrivalReport $monthlyArrivalReport): Response
    {
        abort_unless($request->user()->can('view', $monthlyArrivalReport), 403);

        $arrReport = OfficialReportBuilder::fromMonthlyArrivalReport($monthlyArrivalReport);

        try {
            return Pdf::loadView('pdf.official-report', ['report' => $arrReport, 'preview' => false])
                ->setPaper('a4')
                ->download("{$arrReport['reference_number']}.pdf");
        } catch (\Throwable $e) {
            Log::error('Failed to generate establishment report PDF.', ['exception' => $e, 'report_id' => $monthlyArrivalReport->mar_id]);

            abort(500, 'The PDF could not be generated. Please use Print instead.');
        }
    } // end pdf

    /**
     * Returns an establishment's submitted report For Correction. Remarks
     * are required and are shown to the establishment; the return (who,
     * when, why, old/new status) is recorded in operation_logs. The
     * establishment fixes its arrivals, saves the draft again, and
     * resubmits, which puts the report back to For Review.
     */
    public function returnForCorrection(Request $request, MonthlyArrivalReport $monthlyArrivalReport): RedirectResponse
    {
        abort_unless(
            $request->user()->can('returnForCorrection', $monthlyArrivalReport),
            403,
            'Only a digitally submitted report awaiting review can be returned for correction.'
        );

        $arrData = $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        $arrBefore = $monthlyArrivalReport->getOriginal();

        $monthlyArrivalReport->update([
            'mar_status' => MonthlyReportStatus::ForCorrection,
            'mar_remarks' => $arrData['remarks'],
        ]);

        OperationLogger::returned(
            $request->user(),
            'monthly_arrival_report',
            $monthlyArrivalReport->mar_id,
            $arrData['remarks'],
            $monthlyArrivalReport->mun_id,
            OperationLogger::diff($arrBefore, $monthlyArrivalReport),
            $monthlyArrivalReport->lst_id,
        );

        return redirect()->route('lgu.monthlyReports.show', $monthlyArrivalReport)
            ->with('toast', 'Report returned to the establishment for correction.');
    } // end returnForCorrection

    /**
     * Review screen: totals, source/status, and — for Digital reports —
     * the underlying Arrival rows it was aggregated from, so the LGU can
     * spot-check before verifying. Also shows this report's full audit
     * trail (every create/verify/correction) and whether it's locked from
     * further correction (its MunicipalReport has already been Approved by
     * PTO — a provincial figure PTO has accepted shouldn't change
     * retroactively outside a fresh submission).
     */
    public function show(Request $objRequest, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        abort_unless($objRequest->user()->can('view', $monthlyArrivalReport), 403);

        $monthlyArrivalReport->loadMissing(['listing.categoryRecord', 'submitter', 'verifier', 'arrivals', 'municipalReport']);

        // Same column-sum rule PTO enforces before it can verify the
        // municipal report — surfaced here so the LGU catches it while
        // reviewing instead of PTO bouncing the whole month later.
        $arrBalanceErrors = array_values(array_filter(
            OfficialReportBuilder::validateColumnSums(OfficialReportBuilder::breakdownRows(collect([$monthlyArrivalReport]))),
            fn (string $strError) => ! str_starts_with($strError, 'Grand Total')
        ));

        return $this->renderLgu($objRequest, 'lgu.monthly-reports.show', 'reports.monthly', 'Monthly Report', [
            'report' => $monthlyArrivalReport,
            'locked' => ! $objRequest->user()->can('update', $monthlyArrivalReport),
            'history' => $this->reportHistory($monthlyArrivalReport),
            'originBreakdown' => $monthlyArrivalReport->originBreakdown(),
            'activeView' => $objRequest->query('view') === 'a4' ? 'a4' : 'details',
            'balanceErrors' => $arrBalanceErrors,
        ]);
    }

    /**
     * Correction form — pre-filled with the report's current breakdown.
     * Available for both Digital and Manual/Paper reports: an establishment
     * that already submitted has no way to resubmit a month, so if its
     * figures were wrong, the LGU is the only one who can fix them (e.g.
     * after PTO returns the consolidated report for clarification).
     */
    public function edit(Request $objRequest, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        abort_unless(
            $objRequest->user()->can('update', $monthlyArrivalReport),
            403,
            'This report cannot be corrected here: it is still a draft or with the establishment for correction, or it is part of a provincial report pending or approved by PTO.'
        );

        $monthlyArrivalReport->loadMissing('listing');

        return $this->renderLgu($objRequest, 'lgu.monthly-reports.edit', 'reports.monthly', 'Correct Report', [
            'report' => $monthlyArrivalReport,
        ]);
    }

    /**
     * Applies the correction and logs it (old values, new values, who,
     * when, and why — the reason is required). A report that was already
     * Verified reverts to ForReview, since a corrected report needs a
     * fresh verification rather than keeping a sign-off that predates the
     * correction.
     */
    public function update(Request $objRequest, MonthlyArrivalReport $monthlyArrivalReport): RedirectResponse
    {
        abort_unless(
            $objRequest->user()->can('update', $monthlyArrivalReport),
            403,
            'This report cannot be corrected here: it is still a draft or with the establishment for correction, or it is part of a provincial report pending or approved by PTO.'
        );

        $arrData = $objRequest->validate([
            'party_male' => ['required', 'integer', 'min:0'],
            'party_female' => ['required', 'integer', 'min:0'],
            'party_adults' => ['required', 'integer', 'min:0'],
            'party_children' => ['required', 'integer', 'min:0'],
            'party_seniors' => ['required', 'integer', 'min:0'],
            'party_local' => ['required', 'integer', 'min:0'],
            'party_foreign' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $blnWasVerified = $monthlyArrivalReport->mar_status === MonthlyReportStatus::Verified;
        $arrBefore = $monthlyArrivalReport->getOriginal();

        try {
            $monthlyArrivalReport->update([
                'mar_party_male' => $arrData['party_male'],
                'mar_party_female' => $arrData['party_female'],
                'mar_party_adults' => $arrData['party_adults'],
                'mar_party_children' => $arrData['party_children'],
                'mar_party_seniors' => $arrData['party_seniors'],
                'mar_party_local' => $arrData['party_local'],
                'mar_party_foreign' => $arrData['party_foreign'],
                'mar_total_visitors' => $arrData['party_male'] + $arrData['party_female'],
                ...($blnWasVerified ? ['mar_status' => MonthlyReportStatus::ForReview, 'mar_verified_by' => null, 'mar_verified_at' => null] : []),
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to correct the monthly arrival report.', ['exception' => $objException, 'mar_id' => $monthlyArrivalReport->mar_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated(
            $objRequest->user(),
            'monthly_arrival_report',
            $monthlyArrivalReport->mar_id,
            $monthlyArrivalReport->mun_id,
            $monthlyArrivalReport->lst_id,
            OperationLogger::diff($arrBefore, $monthlyArrivalReport),
            $arrData['reason'],
        );

        return redirect()->route('lgu.monthlyReports.show', $monthlyArrivalReport)
            ->with('toast', $blnWasVerified ? 'Report corrected — re-verify before consolidating again.' : 'Report corrected.');
    }

    public function verify(Request $objRequest, MonthlyArrivalReport $monthlyArrivalReport): RedirectResponse
    {
        abort_unless(
            $objRequest->user()->can('verify', $monthlyArrivalReport),
            403,
            'Only a submitted report awaiting review can be verified.'
        );

        $arrBefore = $monthlyArrivalReport->getOriginal();

        try {
            $monthlyArrivalReport->update([
                'mar_status' => MonthlyReportStatus::Verified,
                'mar_verified_by' => $objRequest->user()->usr_id,
                'mar_verified_at' => now(),
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to verify the monthly arrival report.', ['exception' => $objException, 'mar_id' => $monthlyArrivalReport->mar_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::validated(
            $objRequest->user(),
            'monthly_arrival_report',
            $monthlyArrivalReport->mar_id,
            $monthlyArrivalReport->mun_id,
            $monthlyArrivalReport->lst_id,
            OperationLogger::diff($arrBefore, $monthlyArrivalReport),
        );

        return back()->with('toast', 'Report verified.');
    }

    /**
     * Sums every Verified report for the municipality+period into a
     * MunicipalReport and submits it to PTO (status SUBMITTED) — this one
     * action covers both "consolidate" and "submit to PTO", since there is
     * no separate handoff step. Only Verified reports ever count toward the
     * total; a month with none yet cannot be consolidated.
     */
    public function consolidate(Request $objRequest): RedirectResponse
    {
        $objUser = $objRequest->user();
        $arrData = $objRequest->validate([
            'period_month' => ['required', 'date_format:Y-m'],
        ]);
        $dtmMonth = CarbonImmutable::createFromFormat('Y-m', $arrData['period_month'])->startOfMonth();

        $objVerifiedReports = MonthlyArrivalReport::query()
            ->visibleTo($objUser)
            ->forPeriod($dtmMonth)
            ->where('mar_status', MonthlyReportStatus::Verified)
            ->get();

        if ($objVerifiedReports->isEmpty()) {
            return back()->with('toast', "No verified reports for {$dtmMonth->format('F Y')} yet — nothing to consolidate.")->with('toast_tone', 'danger');
        }

        // Ready rule: every report the LGU has received must be decided
        // first. Establishments that never reported stay Not Submitted on
        // the municipal report (never counted as zero) and do not block it.
        $arrSummary = $this->_municipalSummary($objUser, $dtmMonth, $this->_establishmentRows($objUser, $dtmMonth));

        if ($arrSummary['pendingCount'] > 0) {
            return back()->with('toast', "{$arrSummary['pendingCount']} establishment report(s) for {$dtmMonth->format('F Y')} still need review or correction before submitting to PTO.")->with('toast_tone', 'danger');
        }

        $objExisting = MunicipalReport::query()
            ->where('mun_id', $objUser->mun_id)
            ->whereDate('mrp_period_start', $dtmMonth->toDateString())
            ->whereDoesntHave('supersededBy')
            ->first();

        // Pending PTO review can't be resubmitted out from under it, but a
        // Verified (APPROVED) report CAN be reopened by a fresh LGU
        // resubmission — it just goes back to For Review and is logged as a
        // reopen, not an ordinary consolidation.
        abort_if(
            $objExisting && in_array($objExisting->mrp_status, [MunicipalReport::STATUS_SUBMITTED, MunicipalReport::STATUS_REVIEWED], true),
            403,
            'This municipality already has a report pending PTO review for that month.'
        );
        $blnWasVerified = $objExisting?->isFrozen() ?? false;

        $intTotal = (int) $objVerifiedReports->sum('mar_total_visitors');

        try {
            $objMunicipalReport = DB::transaction(function () use ($objExisting, $blnWasVerified, $objUser, $dtmMonth, $intTotal, $objVerifiedReports) {
                // A Verified report's own row is frozen forever (its
                // mrp_frozen_snapshot, PDF, and verification code must never
                // change) — reopening it creates a brand-new revision row
                // instead of overwriting it. Returning a RETURNED report (or
                // consolidating for the first time) still updates/creates in
                // place as before, since neither of those is frozen.
                $objMunicipalReport = match (true) {
                    $blnWasVerified => MunicipalReport::query()->create([
                        'mrp_municipality' => $objExisting->mrp_municipality,
                        'mun_id' => $objExisting->mun_id,
                        'mrp_submitted_by' => $objUser->usr_id,
                        'mrp_period_start' => $dtmMonth->toDateString(),
                        'mrp_period_end' => $dtmMonth->endOfMonth()->toDateString(),
                        'mrp_total_arrivals' => $intTotal,
                        'mrp_status' => MunicipalReport::STATUS_SUBMITTED,
                        'mrp_revision_number' => $objExisting->mrp_revision_number + 1,
                        'mrp_supersedes_id' => $objExisting->mrp_id,
                    ]),
                    $objExisting !== null => tap($objExisting)->update([
                        'mrp_total_arrivals' => $intTotal,
                        'mrp_status' => MunicipalReport::STATUS_SUBMITTED,
                        'mrp_submitted_by' => $objUser->usr_id,
                        'mrp_reviewed_by' => null,
                        'mrp_reviewed_at' => null,
                    ]),
                    default => MunicipalReport::query()->create([
                        'mrp_municipality' => $objUser->usr_organization_subtitle,
                        'mun_id' => $objUser->mun_id,
                        'mrp_submitted_by' => $objUser->usr_id,
                        'mrp_period_start' => $dtmMonth->toDateString(),
                        'mrp_period_end' => $dtmMonth->endOfMonth()->toDateString(),
                        'mrp_total_arrivals' => $intTotal,
                        'mrp_status' => MunicipalReport::STATUS_SUBMITTED,
                    ]),
                };

                $objVerifiedReports->each->update(['mrp_id' => $objMunicipalReport->mrp_id]);

                return $objMunicipalReport;
            });
        } catch (\Throwable $objException) {
            Log::error('Failed to consolidate the municipal report.', ['exception' => $objException, 'mun_id' => $objUser->mun_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        $arrNewValues = [
            'period_month' => $dtmMonth->toDateString(),
            'total_arrivals' => $intTotal,
            'source_report_count' => $objVerifiedReports->count(),
        ];

        if ($blnWasVerified) {
            OperationLogger::reopened($objUser, 'municipal_report', $objMunicipalReport->mrp_id, $objUser->mun_id, $arrNewValues);
        } else {
            OperationLogger::consolidated($objUser, 'municipal_report', $objMunicipalReport->mrp_id, $objUser->mun_id, $arrNewValues);
        }

        return redirect()->route('lgu.monthlyReports.municipal.show', $dtmMonth->format('Y-m'))
            ->with('toast', "{$dtmMonth->format('F Y')} municipal report submitted to PTO.");
    }

    /**
     * Manual Entry is for the LGU's own establishments only: another
     * municipality's is 403 (security-logged), a destination is 404.
     */
    private function _authorizeManualEntryListing(Request $request, Listing $listing): void
    {
        $this->authorizeOwnMunicipality($request, $listing);
        abort_if($listing->lst_category === 'destinations', 404);
    } // end _authorizeManualEntryListing

    /**
     * An Online iTOUR establishment submits its own report; encoding a
     * paper one too would give the month two sources.
     */
    private function _onlineEstablishmentRedirect(Listing $listing, CarbonImmutable $dtMonth): RedirectResponse
    {
        return redirect()->route('lgu.monthlyReports.manualEntry.index', ['period' => $dtMonth->format('Y-m')])
            ->with('toast', "{$listing->lst_name} reports through Online iTOUR, so it submits its own monthly report. Manual Entry is only for Manual/Paper establishments.")
            ->with('toast_tone', 'danger');
    } // end _onlineEstablishmentRedirect

    /**
     * The municipality's establishments (destinations never report).
     *
     * @return Collection<int, Listing>
     */
    private function _establishments(User $objUser): Collection
    {
        return Listing::query()
            ->visibleTo($objUser)
            ->where('lst_category', '!=', 'destinations')
            ->with('categoryRecord')
            ->orderBy('lst_name')
            ->get();
    } // end _establishments

    /**
     * One row per establishment for the month with its report and display
     * status. Establishments with no report show "Not Submitted" — a
     * display-only state, never written to the database, and never zero.
     *
     * @param  ?Collection<int, Listing>  $objEstablishments  Preloaded list, to avoid re-querying per month.
     * @return Collection<int, array{listing: Listing, report: ?MonthlyArrivalReport, status: string, draftInProgress: bool}>
     */
    private function _establishmentRows(User $objUser, CarbonImmutable $dtMonth, ?Collection $objEstablishments = null): Collection
    {
        $objEstablishments ??= $this->_establishments($objUser);

        $objReportsByListing = MonthlyArrivalReport::query()
            ->visibleTo($objUser)
            ->forPeriod($dtMonth)
            ->with(['submitter', 'verifier'])
            ->get()
            ->keyBy('lst_id');

        // Rows needing LGU attention surface at the top of the table.
        $arrStatusPriority = ['Not Submitted' => 0, 'Draft' => 1, 'Submitted' => 2, 'For Review' => 3, 'For Correction' => 4, 'Verified' => 5];

        return $objEstablishments->map(function (Listing $objListing) use ($objReportsByListing, $objUser) {
            $objReport = $objReportsByListing->get($objListing->lst_id);

            // An establishment's unsubmitted Draft is owner-only (see
            // MonthlyArrivalReportPolicy::view()), so it reads as Not
            // Submitted here — flagged only so the row doesn't offer a
            // Manual Entry link that would collide with that draft.
            $blnIsHiddenDraft = $objReport !== null && ! $objUser->can('view', $objReport);

            return [
                'listing' => $objListing,
                'report' => $blnIsHiddenDraft ? null : $objReport,
                'status' => $blnIsHiddenDraft ? 'Not Submitted' : ($objReport?->mar_status->label() ?? 'Not Submitted'),
                'draftInProgress' => $blnIsHiddenDraft,
            ];
        })->sortBy(fn (array $arrRow) => $arrStatusPriority[$arrRow['status']])->values();
    } // end _establishmentRows

    /**
     * The month's municipal-report picture, shared by both tabs and the
     * Submit to PTO check so they can never disagree.
     *
     * @param  Collection<int, array{listing: Listing, report: ?MonthlyArrivalReport, status: string, draftInProgress: bool}>  $objRows
     * @return array<string, mixed>
     */
    private function _municipalSummary(User $objUser, CarbonImmutable $dtMonth, Collection $objRows): array
    {
        $objReports = $objRows->pluck('report')->filter()->values();
        $countStatus = fn (array $arrStatuses) => $objReports->filter(fn (MonthlyArrivalReport $objReport) => in_array($objReport->mar_status, $arrStatuses, true))->count();

        $objVerifiedReports = $objReports->filter(fn (MonthlyArrivalReport $objReport) => $objReport->mar_status === MonthlyReportStatus::Verified)->values();
        $intAwaitingReviewCount = $countStatus(MonthlyReportStatus::awaitingReview());
        $intForCorrectionCount = $countStatus([MonthlyReportStatus::ForCorrection]);
        $intOwnDraftCount = $countStatus([MonthlyReportStatus::Draft]);
        $intPendingCount = $intAwaitingReviewCount + $intForCorrectionCount + $intOwnDraftCount;

        $objMunicipalReport = MunicipalReport::query()
            ->with(['submitter', 'reviewer'])
            ->where('mun_id', $objUser->mun_id)
            ->whereDate('mrp_period_start', $dtMonth->toDateString())
            ->whereDoesntHave('supersededBy')
            ->first();

        $blnIsSentToPto = $objMunicipalReport !== null && $objMunicipalReport->mrp_status !== MunicipalReport::STATUS_RETURNED;
        $blnHasVerified = $objVerifiedReports->isNotEmpty();
        $blnCanSubmit = ! $blnIsSentToPto && $blnHasVerified && $intPendingCount === 0;

        [$strStatus, $strTone] = match (true) {
            $objMunicipalReport?->mrp_status === MunicipalReport::STATUS_APPROVED => ['Verified by PTO', 'success'],
            $objMunicipalReport?->mrp_status === MunicipalReport::STATUS_REVIEWED => ['For PTO Review', 'warning'],
            $objMunicipalReport?->mrp_status === MunicipalReport::STATUS_SUBMITTED => ['Submitted to PTO', 'info'],
            $objMunicipalReport?->mrp_status === MunicipalReport::STATUS_RETURNED => ['Returned by PTO', 'danger'],
            $intPendingCount > 0 => ['Pending Establishment Reviews', 'warning'],
            $blnHasVerified => ['Ready for Submission', 'success'],
            default => ['No Verified Reports', 'neutral'],
        };

        return [
            'establishmentCount' => $objRows->count(),
            'notSubmittedCount' => $objRows->whereNull('report')->count(),
            'awaitingReviewCount' => $intAwaitingReviewCount,
            'forCorrectionCount' => $intForCorrectionCount,
            'verifiedCount' => $objVerifiedReports->count(),
            'submittedCount' => $intAwaitingReviewCount + $intForCorrectionCount + $objVerifiedReports->count(),
            'pendingCount' => $intPendingCount,
            'verifiedReports' => $objVerifiedReports,
            'verifiedTotal' => (int) $objVerifiedReports->sum('mar_total_visitors'),
            'municipalReport' => $objMunicipalReport,
            'isSentToPto' => $blnIsSentToPto,
            'canSubmit' => $blnCanSubmit,
            'status' => $strStatus,
            'statusTone' => $strTone,
        ];
    } // end _municipalSummary

    /**
     * Official Report data for the month's municipal report: the saved copy
     * once it has been sent to PTO (frozen snapshot once PTO verified it),
     * otherwise a live consolidation of the Verified establishment reports
     * — including after PTO returns it, since that is what will be resent.
     *
     * @param  array<string, mixed>  $arrSummary  From _municipalSummary().
     * @return array<string, mixed>
     */
    private function _municipalReportData(User $objUser, CarbonImmutable $dtMonth, array $arrSummary): array
    {
        $objMunicipalReport = $arrSummary['municipalReport'];

        if ($arrSummary['isSentToPto']) {
            return $objMunicipalReport->isFrozen() && $objMunicipalReport->mrp_frozen_snapshot
                ? $objMunicipalReport->mrp_frozen_snapshot
                : OfficialReportBuilder::fromMunicipalReport($objMunicipalReport);
        }

        return OfficialReportBuilder::fromLiveConsolidation(
            Municipality::query()->findOrFail($objUser->mun_id),
            $dtMonth,
            $arrSummary['verifiedReports'],
            $objUser->usr_name,
            $objUser->usr_role?->title(),
        );
    } // end _municipalReportData

    private function resolvePeriod(Request $objRequest): CarbonImmutable
    {
        $strPeriod = $objRequest->query('period');

        if (is_string($strPeriod) && preg_match('/^\d{4}-\d{2}$/', $strPeriod)) {
            return CarbonImmutable::createFromFormat('Y-m', $strPeriod)->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function recentMonthOptions(): Collection
    {
        return collect(range(0, 11))
            ->map(fn (int $intIndex) => CarbonImmutable::now()->subMonthsNoOverflow($intIndex)->startOfMonth());
    }
}
