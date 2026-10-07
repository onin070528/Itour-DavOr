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
    public function record(Request $request): View
    {
        return $this->renderEstablishment($request, 'establishment.arrivals.record', 'arrivals.record', 'Record Arrival', [
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
    public function store(Request $request, ArrivalRecorder $arrivalRecorder): JsonResponse
    {
        // Shared visit type / headcount / origin rules (ArrivalRecorder) plus
        // the staff-only fields: an editable date and an optional lead name.
        $data = Validator::make($request->all(), array_merge([
            'date' => ['required', 'date'],
            'visitorName' => ['nullable', 'string', 'max:255'],
        ], $arrivalRecorder->getRules($request)))
            ->after(fn ($validator) => $arrivalRecorder->checkHeadcount($validator, $request))
            ->validate();

        // Resolved via establishment_id, not a Listing.name match against
        // organization_name — see Establishment\ProfileController::ownListing().
        abort_if($request->user()->establishment_id === null, 403, 'Your account is not linked to an establishment yet.');
        $listing = $request->user()->establishment()->firstOrFail();

        try {
            $arrivalRecorder->record($listing, $data, ArrivalSource::Staff, $request->user()->id, $data['date']);
        } catch (\Throwable $e) {
            Log::error('Failed to record arrival.', ['exception' => $e, 'listing_id' => $listing->id]);

            return response()->json(['message' => 'Something went wrong while recording the arrival. Please try again.'], 500);
        }

        return response()->json(['message' => 'Arrival recorded.']);
    }

    /**
     * Arrival Records: guest arrivals previously recorded for this
     * establishment, staff-logged and self-checkin alike. Scoped via
     * establishment_id (not a Listing.name match against organization_name —
     * see ProfileController::ownListing()), so two establishments sharing a
     * display name never see each other's arrivals.
     */
    public function index(Request $request): View
    {
        abort_if($request->user()->establishment_id === null, 403, 'Your account is not linked to an establishment yet.');
        $listing = $request->user()->establishment()->firstOrFail();

        $arrivals = $listing->arrivals()
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Arrival $arrival) => [
                'id' => 'GR-'.$arrival->id,
                'date' => $arrival->date->toDateString(),
                'visitorName' => $arrival->visitor_name,
                'gender' => $arrival->gender,
                'classification' => $arrival->classification,
                'remarks' => $arrival->remarks,
                'status' => $arrival->status,
            ]);

        return $this->renderEstablishment($request, 'establishment.arrivals.index', 'arrivals.index', 'Arrival Records', [
            'arrivals' => $arrivals,
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
    public function monthlyReports(Request $request): View
    {
        abort_if($request->user()->establishment_id === null, 403, 'Your account is not linked to an establishment yet.');
        $listing = $request->user()->establishment()->firstOrFail();

        $reports = $listing->monthlyArrivalReports()
            ->with(['submitter', 'verifier'])
            ->orderByDesc('period_month')
            ->get();

        $arrYearOptions = TourismAnalytics::scopedYearOptions(null, $listing->id);
        $intYear = in_array((int) $request->query('year'), $arrYearOptions, true) ? (int) $request->query('year') : CarbonImmutable::now()->year;
        $strTab = in_array($request->query('tab'), ['records', 'statistics'], true) ? $request->query('tab') : 'overview';

        // Monthly Records: every month of the selected year that has
        // started (newest first). A month with no report is "Not
        // Submitted" — shown, never treated as zero.
        $objReportsByMonth = $reports->keyBy(fn (MonthlyArrivalReport $objReport) => $objReport->period_month->format('Y-m'));
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

        return $this->renderEstablishment($request, 'establishment.arrivals.monthly', 'arrivals.monthly', 'Monthly Reports', [
            'year' => $intYear,
            'yearOptions' => $arrYearOptions,
            'activeTab' => $strTab,
            'months' => $objMonths,
            'overview' => $this->_reportingOverview($listing->id, $intYear, $reports, $request->boolean('compare')),
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

        $objYearReports = $objReports->filter(fn (MonthlyArrivalReport $objReport) => $objReport->period_month->year === $intYear);

        return [
            'year' => $intYear,
            'records' => $objRecords,
            'summary' => TourismAnalytics::yearSummary($objRecords),
            'submittedCount' => $objYearReports->reject(fn (MonthlyArrivalReport $objReport) => $objReport->status === MonthlyReportStatus::Draft)->count(),
            'verifiedCount' => $objYearReports->where('status', MonthlyReportStatus::Verified)->count(),
            'forCorrectionCount' => $objYearReports->where('status', MonthlyReportStatus::ForCorrection)->count(),
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
    public function saveMonthlyDraft(Request $request): RedirectResponse
    {
        abort_if($request->user()->establishment_id === null, 403, 'Your account is not linked to an establishment yet.');
        $objListing = $request->user()->establishment()->firstOrFail();

        $arrData = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
        ]);

        $dtMonth = CarbonImmutable::createFromFormat('Y-m', $arrData['period_month'])->startOfMonth();
        $objExisting = $objListing->monthlyArrivalReports()->forPeriod($dtMonth)->first();

        if ($objExisting && ! $request->user()->can('editDraft', $objExisting)) {
            return back()->with('toast', "The {$dtMonth->format('F Y')} report has already been submitted and can no longer be edited.")->with('toast_tone', 'danger');
        }

        $objTotals = $objListing->arrivals()
            ->whereBetween('date', [$dtMonth->toDateString(), $dtMonth->endOfMonth()->toDateString()])
            ->selectRaw('
                COALESCE(SUM(party_male), 0) as party_male,
                COALESCE(SUM(party_female), 0) as party_female,
                COALESCE(SUM(party_adults), 0) as party_adults,
                COALESCE(SUM(party_children), 0) as party_children,
                COALESCE(SUM(party_seniors), 0) as party_seniors,
                COALESCE(SUM(party_local), 0) as party_local,
                COALESCE(SUM(party_foreign), 0) as party_foreign
            ')
            ->first();

        $arrFigures = [
            'party_male' => (int) $objTotals->party_male,
            'party_female' => (int) $objTotals->party_female,
            'party_adults' => (int) $objTotals->party_adults,
            'party_children' => (int) $objTotals->party_children,
            'party_seniors' => (int) $objTotals->party_seniors,
            'party_local' => (int) $objTotals->party_local,
            'party_foreign' => (int) $objTotals->party_foreign,
            'total_visitors' => (int) $objTotals->party_male + (int) $objTotals->party_female,
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
                        'listing_id' => $objListing->id,
                        'municipality_id' => $objListing->municipality_id,
                        'period_month' => $dtMonth->toDateString(),
                        'submission_source' => ReportSubmissionSource::Digital,
                        'status' => MonthlyReportStatus::Draft,
                        ...$arrFigures,
                    ]);

                $objListing->arrivals()
                    ->whereBetween('date', [$dtMonth->toDateString(), $dtMonth->endOfMonth()->toDateString()])
                    ->update(['monthly_arrival_report_id' => $objReport->id]);

                return $objReport;
            });
        } catch (\Throwable $e) {
            Log::error('Failed to save monthly report draft.', ['exception' => $e, 'listing_id' => $objListing->id]);

            return back()->with('toast', 'Something went wrong while saving the draft. Please try again.')->with('toast_tone', 'danger');
        }

        if ($objExisting) {
            OperationLogger::updated(
                $request->user(),
                'monthly_arrival_report',
                $objReport->id,
                $objListing->municipality_id,
                $objListing->id,
                OperationLogger::diff($arrBefore, $objReport),
            );
        } else {
            OperationLogger::created(
                $request->user(),
                'monthly_arrival_report',
                $objReport->id,
                $objListing->municipality_id,
                $objListing->id,
                [
                    'period_month' => $dtMonth->toDateString(),
                    'submission_source' => ReportSubmissionSource::Digital->value,
                    'status' => MonthlyReportStatus::Draft->value,
                    'total_visitors' => $objReport->total_visitors,
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
    public function showMonthlyReport(Request $request, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        abort_unless($request->user()->can('view', $monthlyArrivalReport), 403);

        $monthlyArrivalReport->loadMissing(['submitter', 'verifier', 'listing.categoryRecord']);

        // The guest arrivals this report was added up from, so the owner
        // can check them before submitting (and later, as a record).
        $objArrivals = $monthlyArrivalReport->arrivals()->orderBy('date')->orderBy('id')->get();

        return $this->renderEstablishment($request, 'establishment.arrivals.monthly-show', 'arrivals.monthly', 'Monthly Report', [
            'report' => $monthlyArrivalReport,
            'arrivals' => $objArrivals,
            'canSubmit' => $request->user()->can('submit', $monthlyArrivalReport),
            'activeView' => $request->query('view') === 'a4' ? 'a4' : 'details',
            'originBreakdown' => $monthlyArrivalReport->originBreakdown(),
        ]);
    } // end showMonthlyReport

    /**
     * A4 Preview of the establishment's own report — the same official
     * document layout the LGU and PTO use (DRAFT watermark until Verified).
     */
    public function previewMonthlyReport(Request $request, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        abort_unless($request->user()->can('view', $monthlyArrivalReport), 403);

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
    public function downloadMonthlyReportPdf(Request $request, MonthlyArrivalReport $monthlyArrivalReport): Response
    {
        abort_unless($request->user()->can('view', $monthlyArrivalReport), 403);

        $arrReport = OfficialReportBuilder::fromMonthlyArrivalReport($monthlyArrivalReport);

        try {
            return Pdf::loadView('pdf.official-report', ['report' => $arrReport, 'preview' => false])
                ->setPaper('a4')
                ->download("{$arrReport['reference_number']}.pdf");
        } catch (\Throwable $e) {
            Log::error('Failed to generate monthly report PDF.', ['exception' => $e, 'report_id' => $monthlyArrivalReport->id]);

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
    public function submitMonthlyReport(Request $request): RedirectResponse
    {
        abort_if($request->user()->establishment_id === null, 403, 'Your account is not linked to an establishment yet.');
        $listing = $request->user()->establishment()->firstOrFail();

        $data = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
        ]);

        $month = CarbonImmutable::createFromFormat('Y-m', $data['period_month'])->startOfMonth();
        $objReport = $listing->monthlyArrivalReports()->forPeriod($month)->first();

        if (! $objReport) {
            return back()->with('toast', "Save a draft of the {$month->format('F Y')} report and review it before submitting.")->with('toast_tone', 'danger');
        }

        if (! $request->user()->can('submit', $objReport)) {
            return back()->with('toast', "A report for {$month->format('F Y')} has already been submitted.")->with('toast_tone', 'danger');
        }

        $blnHasUnsavedArrivals = $listing->arrivals()
            ->whereBetween('date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->whereNull('monthly_arrival_report_id')
            ->exists();

        if ($blnHasUnsavedArrivals) {
            return redirect()->route('establishment.arrivals.monthly.show', $objReport)
                ->with('toast', 'New arrivals were recorded since this draft was saved. Save the draft again and review it before submitting.')
                ->with('toast_tone', 'danger');
        }

        $arrBefore = $objReport->getOriginal();

        $objReport->update([
            'status' => MonthlyReportStatus::Submitted,
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        OperationLogger::submitted(
            $request->user(),
            'monthly_arrival_report',
            $objReport->id,
            $listing->municipality_id,
            OperationLogger::diff($arrBefore, $objReport),
            $listing->id,
        );

        // Back to Monthly Records so the owner immediately sees the new status.
        return redirect()->route('establishment.arrivals.monthly', ['year' => $month->year, 'tab' => 'records'])
            ->with('toast', "{$month->format('F Y')} report submitted for LGU review.");
    }
}
