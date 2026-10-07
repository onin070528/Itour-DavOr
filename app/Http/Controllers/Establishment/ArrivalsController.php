<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Handles the Establishment role's manual arrival-recording wizard —
 * showing the form and persisting a staff-entered guest arrival.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Establishment;

use App\Enums\ArrivalSource;
use App\Enums\MonthlyReportStatus;
use App\Enums\ReportSubmissionSource;
use App\Models\Arrival;
use App\Models\MonthlyArrivalReport;
use App\Services\ArrivalRecorder;
use App\Support\OfficialReportBuilder;
use App\Support\OperationLogger;
use App\Support\TourismAnalytics;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ArrivalsController extends EstablishmentController
{
    /**
     * Record Arrival: the two-column, Alpine.js-driven form used to log a guest.
     */
    public function record(Request $objRequest): View
    {
        return $this->renderEstablishment($objRequest, 'establishment.arrivals.record', 'arrivals.record', 'Record Arrival', [
            'provinces' => config('ph_provinces'),
            'countries' => config('countries'),
            'municipalities' => app(ArrivalRecorder::class)->getProvinceMunicipalityNames(),
        ]);
    }

    /**
     * Submits the form (called via fetch by arrivalForm()'s submit() method in
     * resources/js/establishment.js, which then shows a toast rather than
     * reloading the page). This is the staff-entered fallback for a guest who
     * can't scan the QR, so it captures the same visit type and
     * Local/International x Male/Female x Age-group companion breakdown as
     * the public self-checkin form (see CheckinController::store) rather than
     * one visitor's own gender/classification.
     */
    public function store(Request $objRequest, ArrivalRecorder $arrivalRecorder): JsonResponse
    {
        // Shared visit type / headcount / origin rules (ArrivalRecorder) plus
        // the staff-only fields: an editable date and an optional lead name.
        $arrData = Validator::make($objRequest->all(), array_merge([
            'date' => ['required', 'date'],
            'visitorName' => ['nullable', 'string', 'max:255'],
        ], $arrivalRecorder->getRules($objRequest)))
            ->after(fn ($objValidator) => $arrivalRecorder->checkHeadcount($objValidator, $objRequest))
            ->validate();

        // Resolved via lst_id, not a Listing.name match against
        // organization_name — see Establishment\ProfileController::ownListing().
        abort_if($objRequest->user()->lst_id === null, 403, 'Your account is not linked to an establishment yet.');
        $objListing = $objRequest->user()->establishment()->firstOrFail();

        try {
            $arrivalRecorder->record($objListing, $arrData, ArrivalSource::Staff, $objRequest->user()->usr_id, $arrData['date']);
        } catch (\Throwable $objException) {
            Log::error('Failed to record arrival.', ['exception' => $objException, 'listing_id' => $objListing->lst_id]);

            return response()->json(['message' => 'Something went wrong while recording the arrival. Please try again.'], 500);
        }

        return response()->json(['message' => 'Arrival recorded.']);
    }

    /**
     * Arrival Records: guest arrivals previously recorded for this
     * establishment, staff-logged and self-checkin alike. Scoped via
     * lst_id (not a Listing.name match against usr_organization_name —
     * see ProfileController::ownListing()), so two establishments sharing a
     * display name never see each other's arrivals.
     */
    public function index(Request $objRequest): View
    {
        abort_if($objRequest->user()->lst_id === null, 403, 'Your account is not linked to an establishment yet.');
        $objListing = $objRequest->user()->establishment()->firstOrFail();

        $objArrivals = $objListing->arrivals()
            ->orderByDesc('arr_date')
            ->orderByDesc('arr_id')
            ->get()
            ->map(fn (Arrival $objArrival) => [
                'id' => 'GR-'.$objArrival->arr_id,
                'date' => $objArrival->arr_date->toDateString(),
                'visitorName' => $objArrival->arr_visitor_name,
                'gender' => $objArrival->arr_gender,
                'classification' => $objArrival->arr_classification,
                'remarks' => $objArrival->arr_remarks,
                'status' => $objArrival->arr_status,
            ]);

        return $this->renderEstablishment($objRequest, 'establishment.arrivals.index', 'arrivals.index', 'Arrival Records', [
            'arrivals' => $objArrivals,
        ]);
    }

    /**
     * Monthly Reports: the establishment's one reporting workspace. A single
     * Reporting Year drives three in-page tabs — Overview (how reporting is
     * going this year), Monthly Records (every month up to now, with the
     * action each one needs: Prepare / Continue / Correct / View) and
     * Statistics (verified-only arrival figures). The report itself is
     * still prepared, reviewed and submitted through the existing Save
     * Draft -> Review -> Submit to LGU flow.
     */
    public function monthlyReports(Request $objRequest): View
    {
        abort_if($objRequest->user()->lst_id === null, 403, 'Your account is not linked to an establishment yet.');
        $objListing = $objRequest->user()->establishment()->firstOrFail();

        $objReports = $objListing->monthlyArrivalReports()
            ->with(['submitter', 'verifier'])
            ->orderByDesc('mar_period_month')
            ->get();

        $arrYearOptions = TourismAnalytics::scopedYearOptions(null, $objListing->lst_id);
        $intYear = in_array((int) $objRequest->query('year'), $arrYearOptions, true) ? (int) $objRequest->query('year') : CarbonImmutable::now()->year;
        $strTab = in_array($objRequest->query('tab'), ['records', 'statistics'], true) ? $objRequest->query('tab') : 'overview';

        // Monthly Records: every month of the selected year that has
        // started (newest first). A month with no report is "Not
        // Submitted" — shown, never treated as zero.
        $objReportsByMonth = $objReports->keyBy(fn (MonthlyArrivalReport $objReport) => $objReport->mar_period_month->format('Y-m'));
        $dtCurrentMonth = CarbonImmutable::now()->startOfMonth();

        $objMonths = collect(range(12, 1))
            ->map(fn (int $intMonth) => CarbonImmutable::create($intYear, $intMonth, 1))
            ->reject(fn (CarbonImmutable $dtMonth) => $dtMonth->greaterThan($dtCurrentMonth))
            ->map(fn (CarbonImmutable $dtMonth) => [
                'month' => $dtMonth,
                'report' => $objReportsByMonth->get($dtMonth->format('Y-m')),
                'isCurrentMonth' => $dtMonth->equalTo($dtCurrentMonth),
            ])
            ->values();

        return $this->renderEstablishment($objRequest, 'establishment.arrivals.monthly', 'arrivals.monthly', 'Monthly Reports', [
            'year' => $intYear,
            'yearOptions' => $arrYearOptions,
            'activeTab' => $strTab,
            'months' => $objMonths,
            'overview' => $this->_reportingOverview($objListing->lst_id, $intYear, $objReports, $objRequest->boolean('compare')),
        ]);
    }

    /**
     * The establishment's own yearly figures: report counts for the year
     * plus verified-only arrival statistics (monthly trend, highest /
     * lowest / average month, visitor classifications, and a previous-year
     * comparison that is offered only when that year has verified data).
     *
     * @param  Collection<int, MonthlyArrivalReport>  $objReports
     * @return array<string, mixed>
     */
    private function _reportingOverview(int $intListingId, int $intYear, Collection $objReports, bool $blnCompare): array
    {
        $arrFilters = ['year' => $intYear, 'month' => null, 'municipalityId' => null, 'listingId' => $intListingId, 'classification' => null];
        $objRecords = TourismAnalytics::monthlyRecords($arrFilters);
        $objPreviousRecords = TourismAnalytics::monthlyRecords([...$arrFilters, 'year' => $intYear - 1]);
        $blnHasPrevious = $objPreviousRecords->contains('hasData', true);

        $objYearReports = $objReports->filter(fn (MonthlyArrivalReport $objReport) => $objReport->mar_period_month->year === $intYear);

        return [
            'year' => $intYear,
            'records' => $objRecords,
            'summary' => TourismAnalytics::yearSummary($objRecords),
            'submittedCount' => $objYearReports->reject(fn (MonthlyArrivalReport $objReport) => $objReport->mar_status === MonthlyReportStatus::Draft)->count(),
            'verifiedCount' => $objYearReports->where('mar_status', MonthlyReportStatus::Verified)->count(),
            'forCorrectionCount' => $objYearReports->where('mar_status', MonthlyReportStatus::ForCorrection)->count(),
            'hasPrevious' => $blnHasPrevious,
            'compare' => $blnCompare && $blnHasPrevious,
            'previousRecords' => $objPreviousRecords,
            'yearComparison' => TourismAnalytics::yearComparison($objRecords, $objPreviousRecords, $intYear),
            'visitorBreakdown' => TourismAnalytics::visitorBreakdown($arrFilters),
        ];
    } // end _reportingOverview

    /**
     * Save Draft: sums one calendar month's Arrival rows (staff-logged and
     * self-checkin alike) into this establishment's MonthlyArrivalReport for
     * that month — creating it as a Draft, or recomputing an existing Draft /
     * For Correction report — and links those rows to it. Totals are always
     * computed here on the server, never typed in. A month with no recorded
     * arrivals still saves, as a zero-arrival report — a missing report is
     * never the same thing as a submitted zero one.
     */
    public function saveMonthlyDraft(Request $objRequest): RedirectResponse
    {
        abort_if($objRequest->user()->lst_id === null, 403, 'Your account is not linked to an establishment yet.');
        $objListing = $objRequest->user()->establishment()->firstOrFail();

        $arrData = $objRequest->validate([
            'period_month' => ['required', 'date_format:Y-m'],
        ]);

        $dtMonth = CarbonImmutable::createFromFormat('Y-m', $arrData['period_month'])->startOfMonth();
        $objExisting = $objListing->monthlyArrivalReports()->forPeriod($dtMonth)->first();

        if ($objExisting && ! $objRequest->user()->can('editDraft', $objExisting)) {
            return back()->with('toast', "The {$dtMonth->format('F Y')} report has already been submitted and can no longer be edited.")->with('toast_tone', 'danger');
        }

        $objTotals = $objListing->arrivals()
            ->whereBetween('arr_date', [$dtMonth->toDateString(), $dtMonth->endOfMonth()->toDateString()])
            ->selectRaw('
                COALESCE(SUM(arr_party_male), 0) as party_male,
                COALESCE(SUM(arr_party_female), 0) as party_female,
                COALESCE(SUM(arr_party_adults), 0) as party_adults,
                COALESCE(SUM(arr_party_children), 0) as party_children,
                COALESCE(SUM(arr_party_seniors), 0) as party_seniors,
                COALESCE(SUM(arr_party_local), 0) as party_local,
                COALESCE(SUM(arr_party_foreign), 0) as party_foreign
            ')
            ->first();

        $arrFigures = [
            'mar_party_male' => (int) $objTotals->party_male,
            'mar_party_female' => (int) $objTotals->party_female,
            'mar_party_adults' => (int) $objTotals->party_adults,
            'mar_party_children' => (int) $objTotals->party_children,
            'mar_party_seniors' => (int) $objTotals->party_seniors,
            'mar_party_local' => (int) $objTotals->party_local,
            'mar_party_foreign' => (int) $objTotals->party_foreign,
            'mar_total_visitors' => (int) $objTotals->party_male + (int) $objTotals->party_female,
        ];

        $arrBefore = $objExisting?->getOriginal();

        try {
            $objReport = DB::transaction(function () use ($objListing, $dtMonth, $arrFigures, $objExisting) {
                // An existing Draft / For Correction report is recomputed in
                // place (keeping its status and the LGU's remarks); otherwise
                // a new Draft is created.
                $objReport = $objExisting
                    ? tap($objExisting)->update($arrFigures)
                    : MonthlyArrivalReport::query()->create([
                        'lst_id' => $objListing->lst_id,
                        'mun_id' => $objListing->mun_id,
                        'mar_period_month' => $dtMonth->toDateString(),
                        'mar_submission_source' => ReportSubmissionSource::Digital,
                        'mar_status' => MonthlyReportStatus::Draft,
                        ...$arrFigures,
                    ]);

                $objListing->arrivals()
                    ->whereBetween('arr_date', [$dtMonth->toDateString(), $dtMonth->endOfMonth()->toDateString()])
                    ->update(['mar_id' => $objReport->mar_id]);

                return $objReport;
            });
        } catch (\Throwable $objException) {
            Log::error('Failed to save monthly report draft.', ['exception' => $objException, 'listing_id' => $objListing->lst_id]);

            return back()->with('toast', 'Something went wrong while saving the draft. Please try again.')->with('toast_tone', 'danger');
        }

        if ($objExisting) {
            OperationLogger::updated(
                $objRequest->user(),
                'monthly_arrival_report',
                $objReport->mar_id,
                $objListing->mun_id,
                $objListing->lst_id,
                OperationLogger::diff($arrBefore, $objReport),
            );
        } else {
            OperationLogger::created(
                $objRequest->user(),
                'monthly_arrival_report',
                $objReport->mar_id,
                $objListing->mun_id,
                $objListing->lst_id,
                [
                    'period_month' => $dtMonth->toDateString(),
                    'submission_source' => ReportSubmissionSource::Digital->value,
                    'status' => MonthlyReportStatus::Draft->value,
                    'total_visitors' => $objReport->mar_total_visitors,
                ],
            );
        }

        return redirect()->route('establishment.arrivals.monthly.show', $objReport)
            ->with('toast', "{$dtMonth->format('F Y')} draft saved. Review it before submitting to the LGU.");
    } // end saveMonthlyDraft

    /**
     * Review step: the saved report, read-only, with Edit (record any
     * missing arrivals, then Save Draft again) and Submit to LGU. Shows the
     * LGU's remarks when the report was returned For Correction.
     */
    public function showMonthlyReport(Request $objRequest, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        abort_unless($objRequest->user()->can('view', $monthlyArrivalReport), 403);

        $monthlyArrivalReport->loadMissing(['submitter', 'verifier', 'listing.categoryRecord']);

        // The guest arrivals this report was added up from, so the owner
        // can check them before submitting (and later, as a record).
        $objArrivals = $monthlyArrivalReport->arrivals()->orderBy('arr_date')->orderBy('arr_id')->get();

        return $this->renderEstablishment($objRequest, 'establishment.arrivals.monthly-show', 'arrivals.monthly', 'Monthly Report', [
            'report' => $monthlyArrivalReport,
            'arrivals' => $objArrivals,
            'canSubmit' => $objRequest->user()->can('submit', $monthlyArrivalReport),
            'activeView' => $objRequest->query('view') === 'a4' ? 'a4' : 'details',
            'originBreakdown' => $monthlyArrivalReport->originBreakdown(),
        ]);
    } // end showMonthlyReport

    /**
     * A4 Preview of the establishment's own report — the same official
     * document layout the LGU and PTO use (DRAFT watermark until Verified).
     */
    public function previewMonthlyReport(Request $objRequest, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        abort_unless($objRequest->user()->can('view', $monthlyArrivalReport), 403);

        return view('pdf.official-report', [
            'report' => OfficialReportBuilder::fromMonthlyArrivalReport($monthlyArrivalReport),
            'preview' => true,
            'pdfUrl' => route('establishment.arrivals.monthly.pdf', $monthlyArrivalReport),
        ]);
    } // end previewMonthlyReport

    /**
     * Download PDF — the same A4 document as the preview, through the
     * existing DomPDF setup.
     */
    public function downloadMonthlyReportPdf(Request $objRequest, MonthlyArrivalReport $monthlyArrivalReport): Response
    {
        abort_unless($objRequest->user()->can('view', $monthlyArrivalReport), 403);

        $arrReport = OfficialReportBuilder::fromMonthlyArrivalReport($monthlyArrivalReport);

        try {
            return Pdf::loadView('pdf.official-report', ['report' => $arrReport, 'preview' => false])
                ->setPaper('a4')
                ->download("{$arrReport['reference_number']}.pdf");
        } catch (\Throwable $objException) {
            Log::error('Failed to generate monthly report PDF.', ['exception' => $objException, 'report_id' => $monthlyArrivalReport->mar_id]);

            abort(500, 'The PDF could not be generated. Please use Print instead.');
        }
    } // end downloadMonthlyReportPdf

    /**
     * Submit to LGU: sends the saved Draft / For Correction report for the
     * chosen month to the LGU (status Submitted) and stamps who submitted
     * it and when; it is no longer editable by the establishment after
     * this. Submits exactly what was saved and reviewed — if arrivals were
     * recorded for that month after the draft was saved, the draft must be
     * saved and reviewed again first.
     */
    public function submitMonthlyReport(Request $objRequest): RedirectResponse
    {
        abort_if($objRequest->user()->lst_id === null, 403, 'Your account is not linked to an establishment yet.');
        $objListing = $objRequest->user()->establishment()->firstOrFail();

        $arrData = $objRequest->validate([
            'period_month' => ['required', 'date_format:Y-m'],
        ]);

        $dtMonth = CarbonImmutable::createFromFormat('Y-m', $arrData['period_month'])->startOfMonth();
        $objReport = $objListing->monthlyArrivalReports()->forPeriod($dtMonth)->first();

        if (! $objReport) {
            return back()->with('toast', "Save a draft of the {$dtMonth->format('F Y')} report and review it before submitting.")->with('toast_tone', 'danger');
        }

        if (! $objRequest->user()->can('submit', $objReport)) {
            return back()->with('toast', "A report for {$dtMonth->format('F Y')} has already been submitted.")->with('toast_tone', 'danger');
        }

        $blnHasUnsavedArrivals = $objListing->arrivals()
            ->whereBetween('arr_date', [$dtMonth->toDateString(), $dtMonth->endOfMonth()->toDateString()])
            ->whereNull('mar_id')
            ->exists();

        if ($blnHasUnsavedArrivals) {
            return redirect()->route('establishment.arrivals.monthly.show', $objReport)
                ->with('toast', 'New arrivals were recorded since this draft was saved. Save the draft again and review it before submitting.')
                ->with('toast_tone', 'danger');
        }

        $arrBefore = $objReport->getOriginal();

        $objReport->update([
            'mar_status' => MonthlyReportStatus::Submitted,
            'mar_submitted_by' => $objRequest->user()->usr_id,
            'mar_submitted_at' => now(),
        ]);

        OperationLogger::submitted(
            $objRequest->user(),
            'monthly_arrival_report',
            $objReport->mar_id,
            $objListing->mun_id,
            OperationLogger::diff($arrBefore, $objReport),
            $objListing->lst_id,
        );

        // Back to Monthly Records so the owner immediately sees the new status.
        return redirect()->route('establishment.arrivals.monthly', ['year' => $dtMonth->year, 'tab' => 'records'])
            ->with('toast', "{$dtMonth->format('F Y')} report submitted for LGU review.");
    }
}
