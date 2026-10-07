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
    public function index(Request $request): View
    {
        $user = $request->user();
        $month = $this->resolvePeriod($request);

        $rows = $this->_establishmentRows($user, $month);
        $arrSummary = $this->_municipalSummary($user, $month, $rows);

        return $this->renderLgu($request, 'lgu.monthly-reports.index', 'reports.monthly', 'Monthly Reports', [
            'rows' => $rows,
            'month' => $month,
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
        $arrYearOptions = TourismAnalytics::scopedYearOptions($objUser->municipality_id);
        $intYear = in_array((int) $request->query('year'), $arrYearOptions, true) ? (int) $request->query('year') : CarbonImmutable::now()->year;
        $strSection = in_array($request->query('section'), ['records', 'statistics'], true) ? $request->query('section') : 'overview';

        $arrFilters = ['year' => $intYear, 'month' => null, 'municipalityId' => $objUser->municipality_id, 'listingId' => null, 'classification' => null];
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
                ->where('entity_type', 'municipal_report')
                ->where('entity_id', $arrSummary['municipalReport']->id)
                ->with('user')
                ->orderByDesc('created_at')
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
                'municipalityId' => $objUser->municipality_id, 'listingId' => null, 'classification' => null,
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

        OperationLogger::exported($objUser, 'municipal_report', $objUser->municipality_id, ['action' => 'preview', 'period' => $dtMonth->toDateString()]);

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

        OperationLogger::exported($objUser, 'municipal_report', $objUser->municipality_id, ['action' => 'download_pdf', 'period' => $dtMonth->toDateString()]);

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
    public function showManualEntry(Request $request, Listing $listing): View|RedirectResponse
    {
        $this->_authorizeManualEntryListing($request, $listing);

        $month = $this->resolvePeriod($request);

        if ($listing->reportingMethod()->isOnline()) {
            return $this->_onlineEstablishmentRedirect($listing, $month);
        }

        $objExisting = $listing->monthlyArrivalReports()->forPeriod($month)->first();

        abort_if($objExisting && ! $request->user()->can('editDraft', $objExisting), 422, 'This establishment already has a report (or a draft in progress) for that month.');

        return $this->renderLgu($request, 'lgu.monthly-reports.manual-entry', 'reports.manualEntry', 'Manual Entry', [
            'listing' => $listing,
            'month' => $month,
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
     * is kept by the unique (listing_id, period_month) index.
     */
    public function storeManualEntry(Request $request, Listing $listing): RedirectResponse
    {
        $this->_authorizeManualEntryListing($request, $listing);

        $data = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
            'intent' => ['nullable', 'in:draft,preview'],
            ...ManualReportForm::rules(),
        ]);

        $month = CarbonImmutable::createFromFormat('Y-m', $data['period_month'])->startOfMonth();

        if ($listing->reportingMethod()->isOnline()) {
            return $this->_onlineEstablishmentRedirect($listing, $month);
        }

        $objExisting = $listing->monthlyArrivalReports()->forPeriod($month)->first();

        if ($objExisting && ! $request->user()->can('editDraft', $objExisting)) {
            return back()->with('toast', "{$listing->name} already has a report for {$month->format('F Y')}.")->with('toast_tone', 'danger');
        }

        $arrFigures = ManualReportForm::figures($data);

        try {
            if ($objExisting) {
                $arrBefore = $objExisting->getOriginal();
                $objExisting->update($arrFigures);
                $report = $objExisting;

                OperationLogger::updated(
                    $request->user(),
                    'monthly_arrival_report',
                    $report->id,
                    $listing->municipality_id,
                    $listing->id,
                    OperationLogger::diff($arrBefore, $report),
                );
            } else {
                $report = MonthlyArrivalReport::query()->create([
                    'listing_id' => $listing->id,
                    'municipality_id' => $listing->municipality_id,
                    'period_month' => $month->toDateString(),
                    'submission_source' => ReportSubmissionSource::ManualPaper,
                    'status' => MonthlyReportStatus::Draft,
                    ...$arrFigures,
                ]);

                OperationLogger::created(
                    $request->user(),
                    'monthly_arrival_report',
                    $report->id,
                    $listing->municipality_id,
                    $listing->id,
                    [
                        'period_month' => $month->toDateString(),
                        'submission_source' => ReportSubmissionSource::ManualPaper->value,
                        'status' => MonthlyReportStatus::Draft->value,
                        'total_visitors' => $report->total_visitors,
                    ],
                );
            }
        } catch (UniqueConstraintViolationException) {
            // Another report for this establishment and month was saved in the meantime.
            return back()->withInput()->with('toast', "{$listing->name} already has a report for {$month->format('F Y')}.")->with('toast_tone', 'danger');
        }

        if (($data['intent'] ?? 'draft') === 'preview') {
            return redirect()->route('lgu.monthlyReports.show', ['monthlyArrivalReport' => $report, 'view' => 'a4'])
                ->with('toast', 'Draft saved. Check the preview against the paper report, then submit.');
        }

        return redirect()->route('lgu.monthlyReports.manualEntry', ['listing' => $listing, 'period' => $month->format('Y-m')])
            ->with('toast', "Draft saved for {$listing->name}, {$month->format('F Y')}.");
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
                ->with('toast', "{$monthlyArrivalReport->listing->name} now reports through Online iTOUR, so this paper draft can't be submitted. Its own report for that month comes through iTOUR.")
                ->with('toast_tone', 'danger');
        }

        $arrBefore = $monthlyArrivalReport->getOriginal();

        $monthlyArrivalReport->update([
            'status' => MonthlyReportStatus::Submitted,
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        OperationLogger::submitted(
            $request->user(),
            'monthly_arrival_report',
            $monthlyArrivalReport->id,
            $monthlyArrivalReport->municipality_id,
            OperationLogger::diff($arrBefore, $monthlyArrivalReport),
            $monthlyArrivalReport->listing_id,
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

        $monthlyArrivalReport->update(['status' => MonthlyReportStatus::ForReview]);

        OperationLogger::updated(
            $request->user(),
            'monthly_arrival_report',
            $monthlyArrivalReport->id,
            $monthlyArrivalReport->municipality_id,
            $monthlyArrivalReport->listing_id,
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
            Log::error('Failed to generate establishment report PDF.', ['exception' => $e, 'report_id' => $monthlyArrivalReport->id]);

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
            'status' => MonthlyReportStatus::ForCorrection,
            'remarks' => $arrData['remarks'],
        ]);

        OperationLogger::returned(
            $request->user(),
            'monthly_arrival_report',
            $monthlyArrivalReport->id,
            $arrData['remarks'],
            $monthlyArrivalReport->municipality_id,
            OperationLogger::diff($arrBefore, $monthlyArrivalReport),
            $monthlyArrivalReport->listing_id,
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
    public function show(Request $request, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        abort_unless($request->user()->can('view', $monthlyArrivalReport), 403);

        $monthlyArrivalReport->loadMissing(['listing.categoryRecord', 'submitter', 'verifier', 'arrivals', 'municipalReport']);

        // Same column-sum rule PTO enforces before it can verify the
        // municipal report — surfaced here so the LGU catches it while
        // reviewing instead of PTO bouncing the whole month later.
        $arrBalanceErrors = array_values(array_filter(
            OfficialReportBuilder::validateColumnSums(OfficialReportBuilder::breakdownRows(collect([$monthlyArrivalReport]))),
            fn (string $strError) => ! str_starts_with($strError, 'Grand Total')
        ));

        return $this->renderLgu($request, 'lgu.monthly-reports.show', 'reports.monthly', 'Monthly Report', [
            'report' => $monthlyArrivalReport,
            'locked' => ! $request->user()->can('update', $monthlyArrivalReport),
            'history' => $this->reportHistory($monthlyArrivalReport),
            'originBreakdown' => $monthlyArrivalReport->originBreakdown(),
            'activeView' => $request->query('view') === 'a4' ? 'a4' : 'details',
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
    public function edit(Request $request, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        abort_unless(
            $request->user()->can('update', $monthlyArrivalReport),
            403,
            'This report cannot be corrected here: it is still a draft or with the establishment for correction, or it is part of a provincial report pending or approved by PTO.'
        );

        $monthlyArrivalReport->loadMissing('listing');

        return $this->renderLgu($request, 'lgu.monthly-reports.edit', 'reports.monthly', 'Correct Report', [
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
    public function update(Request $request, MonthlyArrivalReport $monthlyArrivalReport): RedirectResponse
    {
        abort_unless(
            $request->user()->can('update', $monthlyArrivalReport),
            403,
            'This report cannot be corrected here: it is still a draft or with the establishment for correction, or it is part of a provincial report pending or approved by PTO.'
        );

        $data = $request->validate([
            'party_male' => ['required', 'integer', 'min:0'],
            'party_female' => ['required', 'integer', 'min:0'],
            'party_adults' => ['required', 'integer', 'min:0'],
            'party_children' => ['required', 'integer', 'min:0'],
            'party_seniors' => ['required', 'integer', 'min:0'],
            'party_local' => ['required', 'integer', 'min:0'],
            'party_foreign' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $wasVerified = $monthlyArrivalReport->status === MonthlyReportStatus::Verified;
        $before = $monthlyArrivalReport->getOriginal();

        $monthlyArrivalReport->update([
            'party_male' => $data['party_male'],
            'party_female' => $data['party_female'],
            'party_adults' => $data['party_adults'],
            'party_children' => $data['party_children'],
            'party_seniors' => $data['party_seniors'],
            'party_local' => $data['party_local'],
            'party_foreign' => $data['party_foreign'],
            'total_visitors' => $data['party_male'] + $data['party_female'],
            ...($wasVerified ? ['status' => MonthlyReportStatus::ForReview, 'verified_by' => null, 'verified_at' => null] : []),
        ]);

        OperationLogger::updated(
            $request->user(),
            'monthly_arrival_report',
            $monthlyArrivalReport->id,
            $monthlyArrivalReport->municipality_id,
            $monthlyArrivalReport->listing_id,
            OperationLogger::diff($before, $monthlyArrivalReport),
            $data['reason'],
        );

        return redirect()->route('lgu.monthlyReports.show', $monthlyArrivalReport)
            ->with('toast', $wasVerified ? 'Report corrected — re-verify before consolidating again.' : 'Report corrected.');
    }

    public function verify(Request $request, MonthlyArrivalReport $monthlyArrivalReport): RedirectResponse
    {
        abort_unless(
            $request->user()->can('verify', $monthlyArrivalReport),
            403,
            'Only a submitted report awaiting review can be verified.'
        );

        $before = $monthlyArrivalReport->getOriginal();

        $monthlyArrivalReport->update([
            'status' => MonthlyReportStatus::Verified,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        OperationLogger::validated(
            $request->user(),
            'monthly_arrival_report',
            $monthlyArrivalReport->id,
            $monthlyArrivalReport->municipality_id,
            $monthlyArrivalReport->listing_id,
            OperationLogger::diff($before, $monthlyArrivalReport),
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
    public function consolidate(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
        ]);
        $month = CarbonImmutable::createFromFormat('Y-m', $data['period_month'])->startOfMonth();

        $verifiedReports = MonthlyArrivalReport::query()
            ->visibleTo($user)
            ->forPeriod($month)
            ->where('status', MonthlyReportStatus::Verified)
            ->get();

        if ($verifiedReports->isEmpty()) {
            return back()->with('toast', "No verified reports for {$month->format('F Y')} yet — nothing to consolidate.")->with('toast_tone', 'danger');
        }

        // Ready rule: every report the LGU has received must be decided
        // first. Establishments that never reported stay Not Submitted on
        // the municipal report (never counted as zero) and do not block it.
        $arrSummary = $this->_municipalSummary($user, $month, $this->_establishmentRows($user, $month));

        if ($arrSummary['pendingCount'] > 0) {
            return back()->with('toast', "{$arrSummary['pendingCount']} establishment report(s) for {$month->format('F Y')} still need review or correction before submitting to PTO.")->with('toast_tone', 'danger');
        }

        $existing = MunicipalReport::query()
            ->where('municipality_id', $user->municipality_id)
            ->whereDate('period_start', $month->toDateString())
            ->whereDoesntHave('supersededBy')
            ->first();

        // Pending PTO review can't be resubmitted out from under it, but a
        // Verified (APPROVED) report CAN be reopened by a fresh LGU
        // resubmission — it just goes back to For Review and is logged as a
        // reopen, not an ordinary consolidation.
        abort_if(
            $existing && in_array($existing->status, [MunicipalReport::STATUS_SUBMITTED, MunicipalReport::STATUS_REVIEWED], true),
            403,
            'This municipality already has a report pending PTO review for that month.'
        );
        $wasVerified = $existing?->isFrozen() ?? false;

        $total = (int) $verifiedReports->sum('total_visitors');

        $municipalReport = DB::transaction(function () use ($existing, $wasVerified, $user, $month, $total, $verifiedReports) {
            // A Verified report's own row is frozen forever (its
            // frozen_snapshot, PDF, and verification code must never
            // change) — reopening it creates a brand-new revision row
            // instead of overwriting it. Returning a RETURNED report (or
            // consolidating for the first time) still updates/creates in
            // place as before, since neither of those is frozen.
            $municipalReport = match (true) {
                $wasVerified => MunicipalReport::query()->create([
                    'municipality' => $existing->municipality,
                    'municipality_id' => $existing->municipality_id,
                    'submitted_by' => $user->id,
                    'period_start' => $month->toDateString(),
                    'period_end' => $month->endOfMonth()->toDateString(),
                    'total_arrivals' => $total,
                    'status' => MunicipalReport::STATUS_SUBMITTED,
                    'revision_number' => $existing->revision_number + 1,
                    'supersedes_id' => $existing->id,
                ]),
                $existing !== null => tap($existing)->update([
                    'total_arrivals' => $total,
                    'status' => MunicipalReport::STATUS_SUBMITTED,
                    'submitted_by' => $user->id,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]),
                default => MunicipalReport::query()->create([
                    'municipality' => $user->organization_subtitle,
                    'municipality_id' => $user->municipality_id,
                    'submitted_by' => $user->id,
                    'period_start' => $month->toDateString(),
                    'period_end' => $month->endOfMonth()->toDateString(),
                    'total_arrivals' => $total,
                    'status' => MunicipalReport::STATUS_SUBMITTED,
                ]),
            };

            $verifiedReports->each->update(['municipal_report_id' => $municipalReport->id]);

            return $municipalReport;
        });

        $newValues = [
            'period_month' => $month->toDateString(),
            'total_arrivals' => $total,
            'source_report_count' => $verifiedReports->count(),
        ];

        if ($wasVerified) {
            OperationLogger::reopened($user, 'municipal_report', $municipalReport->id, $user->municipality_id, $newValues);
        } else {
            OperationLogger::consolidated($user, 'municipal_report', $municipalReport->id, $user->municipality_id, $newValues);
        }

        return redirect()->route('lgu.monthlyReports.municipal.show', $month->format('Y-m'))
            ->with('toast', "{$month->format('F Y')} municipal report submitted to PTO.");
    }

    /**
     * Manual Entry is for the LGU's own establishments only: another
     * municipality's is 403 (security-logged), a destination is 404.
     */
    private function _authorizeManualEntryListing(Request $request, Listing $listing): void
    {
        $this->authorizeOwnMunicipality($request, $listing);
        abort_if($listing->category === 'destinations', 404);
    } // end _authorizeManualEntryListing

    /**
     * An Online iTOUR establishment submits its own report; encoding a
     * paper one too would give the month two sources.
     */
    private function _onlineEstablishmentRedirect(Listing $listing, CarbonImmutable $dtMonth): RedirectResponse
    {
        return redirect()->route('lgu.monthlyReports.manualEntry.index', ['period' => $dtMonth->format('Y-m')])
            ->with('toast', "{$listing->name} reports through Online iTOUR, so it submits its own monthly report. Manual Entry is only for Manual/Paper establishments.")
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
            ->where('category', '!=', 'destinations')
            ->with('categoryRecord')
            ->orderBy('name')
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
            ->keyBy('listing_id');

        // Rows needing LGU attention surface at the top of the table.
        $arrStatusPriority = ['Not Submitted' => 0, 'Draft' => 1, 'Submitted' => 2, 'For Review' => 3, 'For Correction' => 4, 'Verified' => 5];

        return $objEstablishments->map(function (Listing $objListing) use ($objReportsByListing, $objUser) {
            $objReport = $objReportsByListing->get($objListing->id);

            // An establishment's unsubmitted Draft is owner-only (see
            // MonthlyArrivalReportPolicy::view()), so it reads as Not
            // Submitted here — flagged only so the row doesn't offer a
            // Manual Entry link that would collide with that draft.
            $blnIsHiddenDraft = $objReport !== null && ! $objUser->can('view', $objReport);

            return [
                'listing' => $objListing,
                'report' => $blnIsHiddenDraft ? null : $objReport,
                'status' => $blnIsHiddenDraft ? 'Not Submitted' : ($objReport?->status->label() ?? 'Not Submitted'),
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
        $countStatus = fn (array $arrStatuses) => $objReports->filter(fn (MonthlyArrivalReport $objReport) => in_array($objReport->status, $arrStatuses, true))->count();

        $objVerifiedReports = $objReports->filter(fn (MonthlyArrivalReport $objReport) => $objReport->status === MonthlyReportStatus::Verified)->values();
        $intAwaitingReviewCount = $countStatus(MonthlyReportStatus::awaitingReview());
        $intForCorrectionCount = $countStatus([MonthlyReportStatus::ForCorrection]);
        $intOwnDraftCount = $countStatus([MonthlyReportStatus::Draft]);
        $intPendingCount = $intAwaitingReviewCount + $intForCorrectionCount + $intOwnDraftCount;

        $objMunicipalReport = MunicipalReport::query()
            ->with(['submitter', 'reviewer'])
            ->where('municipality_id', $objUser->municipality_id)
            ->whereDate('period_start', $dtMonth->toDateString())
            ->whereDoesntHave('supersededBy')
            ->first();

        $blnIsSentToPto = $objMunicipalReport !== null && $objMunicipalReport->status !== MunicipalReport::STATUS_RETURNED;
        $blnHasVerified = $objVerifiedReports->isNotEmpty();
        $blnCanSubmit = ! $blnIsSentToPto && $blnHasVerified && $intPendingCount === 0;

        [$strStatus, $strTone] = match (true) {
            $objMunicipalReport?->status === MunicipalReport::STATUS_APPROVED => ['Verified by PTO', 'success'],
            $objMunicipalReport?->status === MunicipalReport::STATUS_REVIEWED => ['For PTO Review', 'warning'],
            $objMunicipalReport?->status === MunicipalReport::STATUS_SUBMITTED => ['Submitted to PTO', 'info'],
            $objMunicipalReport?->status === MunicipalReport::STATUS_RETURNED => ['Returned by PTO', 'danger'],
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
            'verifiedTotal' => (int) $objVerifiedReports->sum('total_visitors'),
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
            return $objMunicipalReport->isFrozen() && $objMunicipalReport->frozen_snapshot
                ? $objMunicipalReport->frozen_snapshot
                : OfficialReportBuilder::fromMunicipalReport($objMunicipalReport);
        }

        return OfficialReportBuilder::fromLiveConsolidation(
            Municipality::query()->findOrFail($objUser->municipality_id),
            $dtMonth,
            $arrSummary['verifiedReports'],
            $objUser->name,
            $objUser->role?->title(),
        );
    } // end _municipalReportData

    private function resolvePeriod(Request $request): CarbonImmutable
    {
        $period = $request->query('period');

        if (is_string($period) && preg_match('/^\d{4}-\d{2}$/', $period)) {
            return CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function recentMonthOptions(): Collection
    {
        return collect(range(0, 11))
            ->map(fn (int $i) => CarbonImmutable::now()->subMonthsNoOverflow($i)->startOfMonth());
    }
}
