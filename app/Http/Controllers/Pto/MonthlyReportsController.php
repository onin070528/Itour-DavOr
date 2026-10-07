<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: PTO Provincial Reports — the province's consolidated monthly
 * reporting record (Overview | Monthly Records | Statistics) built from the
 * LGUs' municipal reports. Establishment-level encoding, review, and
 * verification stay LGU-only; the PTO's Verify / Return actions on LGU
 * reports reuse Pto\MunicipalReportsController's existing routes.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Enums\MonthlyReportStatus;
use App\Enums\ReportSubmissionSource;
use App\Exports\OfficialReportExport;
use App\Http\Controllers\Concerns\TracksReportHistory;
use App\Models\Listing;
use App\Models\MonthlyArrivalReport;
use App\Models\Municipality;
use App\Models\MunicipalReport;
use App\Support\OfficialReportBuilder;
use App\Support\OperationLogger;
use App\Support\TourismAnalytics;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class MonthlyReportsController extends PtoController
{
    use TracksReportHistory;

    /**
     * Provincial Reports: the PTO's one reporting workspace for a Reporting
     * Year, in three in-page tabs — Overview (province-wide reporting
     * situation), Monthly Records (one consolidated provincial record per
     * month; View / Review open a modal listing every LGU, where PTO can
     * Verify / Return LGU reports through the same routes and rules as LGU
     * Submissions, and expand each LGU to its establishments) and
     * Statistics. Official totals count PTO-verified municipal reports only.
     */
    public function index(Request $request): View
    {
        $arrYearOptions = TourismAnalytics::scopedYearOptions(null);
        $intYear = in_array((int) $request->query('year'), $arrYearOptions, true) ? (int) $request->query('year') : CarbonImmutable::now()->year;
        $strTab = in_array($request->query('tab'), ['records', 'statistics'], true) ? $request->query('tab') : 'overview';

        $objMunicipalities = Municipality::query()->orderBy('name')->get();
        $intMunicipalityCount = $objMunicipalities->count();

        // Latest revision of every LGU's municipal report this year.
        $objMunicipalReports = MunicipalReport::query()
            ->with(['submitter', 'reviewer'])
            ->whereNotNull('municipality_id')
            ->whereYear('period_start', $intYear)
            ->whereDoesntHave('supersededBy')
            ->get();
        $objReportsByMonth = $objMunicipalReports->groupBy(fn (MunicipalReport $objReport) => $objReport->period_start->month);

        // Establishment level, for the per-LGU drill-down inside each month's
        // modal. Drafts are owner-only, so they read as Not Submitted.
        $objEstablishmentReports = MonthlyArrivalReport::query()
            ->where('status', '!=', MonthlyReportStatus::Draft)
            ->whereYear('period_month', $intYear)
            ->get()
            ->groupBy(fn (MonthlyArrivalReport $objReport) => $objReport->period_month->month.'-'.$objReport->municipality_id);
        $objListingsByMunicipality = Listing::query()
            ->whereNotNull('municipality_id')
            ->where('category', '!=', 'destinations')
            ->orderBy('name')
            ->get(['id', 'name', 'municipality_id'])
            ->groupBy('municipality_id');

        $objRecords = TourismAnalytics::provincialMonthlyRecords($intYear);
        $objRecordsByMonth = $objRecords->keyBy('month');
        $dtCurrentMonth = CarbonImmutable::now()->startOfMonth();

        $objMonths = collect(range(12, 1))
            ->map(fn (int $intMonth) => CarbonImmutable::create($intYear, $intMonth, 1))
            ->reject(fn (CarbonImmutable $dtMonth) => $dtMonth->greaterThan($dtCurrentMonth))
            ->map(fn (CarbonImmutable $dtMonth) => $this->_provincialMonth(
                $dtMonth,
                $objMunicipalities,
                ($objReportsByMonth->get($dtMonth->month) ?? collect())->keyBy('municipality_id'),
                $objEstablishmentReports,
                $objListingsByMunicipality,
                $objRecordsByMonth->get($dtMonth->month),
            ))
            ->values();

        // Overview figures for the year.
        $intMonthsElapsed = $objMonths->count();
        $intApprovedCount = $objMunicipalReports->where('status', MunicipalReport::STATUS_APPROVED)->count();
        $intExpectedReports = $intMunicipalityCount * $intMonthsElapsed;

        $objPreviousRecords = TourismAnalytics::provincialMonthlyRecords($intYear - 1);
        $blnHasPrevious = $objPreviousRecords->contains('hasData', true);

        // Statistics: LGU reporting coverage for the year.
        $objLguCoverage = $objMunicipalities->map(function (Municipality $objMunicipality) use ($objMunicipalReports, $intMonthsElapsed) {
            $objOwn = $objMunicipalReports->where('municipality_id', $objMunicipality->id);
            $objApproved = $objOwn->where('status', MunicipalReport::STATUS_APPROVED);

            return [
                'municipality' => $objMunicipality,
                'submittedMonths' => $objOwn->count(),
                'verifiedMonths' => $objApproved->count(),
                'expectedMonths' => $intMonthsElapsed,
                'verifiedArrivals' => (int) $objApproved->sum('total_arrivals'),
            ];
        })->sortByDesc('verifiedArrivals')->values();

        return $this->renderPto($request, 'pto.monthly-reports.index', 'monthlyReports', 'Provincial Reports', [
            'year' => $intYear,
            'yearOptions' => $arrYearOptions,
            'activeTab' => $strTab,
            'months' => $objMonths,
            'municipalityCount' => $intMunicipalityCount,
            'overview' => [
                'total' => (int) $objRecords->sum('total'),
                'lgusReported' => $objMunicipalReports->pluck('municipality_id')->unique()->count(),
                'verifiedCount' => $intApprovedCount,
                'forCorrectionCount' => $objMunicipalReports->where('status', MunicipalReport::STATUS_RETURNED)->count(),
                'monthsCompleted' => $objMonths->where('isComplete', true)->count(),
                'monthsElapsed' => $intMonthsElapsed,
                'coveragePercent' => $intExpectedReports > 0 ? round($intApprovedCount / $intExpectedReports * 100, 1) : 0,
                'pendingReports' => $objMonths->flatMap(fn (array $arrMonth) => $arrMonth['lgus']->filter(fn (array $arrLgu) => $arrLgu['isPendingPto'])->map(fn (array $arrLgu) => [...$arrLgu, 'month' => $arrMonth['month']])),
            ],
            'statistics' => [
                'records' => $objRecords,
                'summary' => TourismAnalytics::yearSummary($objRecords),
                'hasPrevious' => $blnHasPrevious,
                'compare' => $request->boolean('compare') && $blnHasPrevious,
                'previousRecords' => $objPreviousRecords,
                'yearComparison' => TourismAnalytics::yearComparison($objRecords, $objPreviousRecords, $intYear),
                'visitorBreakdown' => TourismAnalytics::provincialVisitorBreakdown($intYear),
                'lguCoverage' => $objLguCoverage,
            ],
        ]);
    }

    /**
     * One month of the provincial record: every LGU's municipal report
     * (with its establishments), the month's counts, its provincial status,
     * and which action the PTO gets (Review when an LGU report is waiting on
     * PTO or was returned; View otherwise).
     *
     * @param  Collection<int, Municipality>  $objMunicipalities
     * @param  Collection<int, MunicipalReport>  $objReportsByMunicipality
     * @param  Collection<string, Collection<int, MonthlyArrivalReport>>  $objEstablishmentReports
     * @param  Collection<int, Collection<int, Listing>>  $objListingsByMunicipality
     * @param  ?array<string, mixed>  $arrRecord
     * @return array<string, mixed>
     */
    private function _provincialMonth(CarbonImmutable $dtMonth, Collection $objMunicipalities, Collection $objReportsByMunicipality, Collection $objEstablishmentReports, Collection $objListingsByMunicipality, ?array $arrRecord): array
    {
        $objLgus = $objMunicipalities->map(function (Municipality $objMunicipality) use ($dtMonth, $objReportsByMunicipality, $objEstablishmentReports, $objListingsByMunicipality) {
            $objReport = $objReportsByMunicipality->get($objMunicipality->id);
            $objEstReports = ($objEstablishmentReports->get($dtMonth->month.'-'.$objMunicipality->id) ?? collect())->keyBy('listing_id');

            $objEstablishments = ($objListingsByMunicipality->get($objMunicipality->id) ?? collect())->map(fn (Listing $objListing) => [
                'name' => $objListing->name,
                'report' => $objEstReports->get($objListing->id),
            ]);

            return [
                'municipality' => $objMunicipality,
                'report' => $objReport,
                'status' => MunicipalReportsController::statusLabel($objReport?->status),
                'isPendingPto' => in_array($objReport?->status, [MunicipalReport::STATUS_SUBMITTED, MunicipalReport::STATUS_REVIEWED], true),
                'establishments' => $objEstablishments,
                'verifiedEstablishmentCount' => $objEstReports->where('status', MonthlyReportStatus::Verified)->count(),
            ];
        });

        $intTotal = $objMunicipalities->count();
        $intApproved = $objLgus->filter(fn (array $arrLgu) => $arrLgu['report']?->status === MunicipalReport::STATUS_APPROVED)->count();
        $intPending = $objLgus->where('isPendingPto', true)->count();
        $intReturned = $objLgus->filter(fn (array $arrLgu) => $arrLgu['report']?->status === MunicipalReport::STATUS_RETURNED)->count();
        $intReported = $objLgus->filter(fn (array $arrLgu) => $arrLgu['report'] !== null)->count();

        [$strStatus, $strTone] = match (true) {
            $intPending > 0 => ['For PTO Review', 'warning'],
            $intReturned > 0 => ['Returned for Correction', 'danger'],
            $intTotal > 0 && $intApproved === $intTotal => ['Verified', 'success'],
            $intApproved > 0 => ['Partially Verified', 'info'],
            default => ['No LGU Reports', 'neutral'],
        };

        return [
            'month' => $dtMonth,
            'record' => $arrRecord,
            'lgus' => $objLgus,
            'reportedCount' => $intReported,
            'approvedCount' => $intApproved,
            'pendingCount' => $intPending,
            'returnedCount' => $intReturned,
            'isComplete' => $intTotal > 0 && $intApproved === $intTotal,
            'status' => $strStatus,
            'statusTone' => $strTone,
            'action' => match (true) {
                $intPending > 0 || $intReturned > 0 => 'review',
                $intReported > 0 => 'view',
                default => null,
            },
            'visitorBreakdown' => $intApproved > 0 ? TourismAnalytics::provincialVisitorBreakdown($dtMonth->year, $dtMonth->month) : null,
        ];
    } // end _provincialMonth

    /**
     * Read-only mirror of Lgu\MonthlyReportsController::show() — same
     * detail, no Verify action. Drafts are owner-only, so never shown here.
     */
    public function show(Request $request, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        abort_unless($request->user()->can('view', $monthlyArrivalReport), 403);

        $monthlyArrivalReport->loadMissing(['listing', 'submitter', 'verifier', 'arrivals']);

        return $this->renderPto($request, 'pto.monthly-reports.show', 'monthlyReports', 'Monthly Report', [
            'report' => $monthlyArrivalReport,
            'history' => $this->reportHistory($monthlyArrivalReport),
            'originBreakdown' => $monthlyArrivalReport->originBreakdown(),
        ]);
    }

    /**
     * Official Report (Provincial Reports). If the municipality already has
     * a Verified consolidated report for this period, that one IS the
     * province-ready document — redirect to it (same template, same
     * verification code, same frozen snapshot — see
     * Pto\MunicipalReportsController) instead of building a second,
     * divergent one. Otherwise renders a live Draft straight from
     * establishment-level data, useful before the LGU has consolidated yet.
     */
    public function officialReport(Request $request): View|RedirectResponse
    {
        $municipality = $this->resolveMunicipality($request, Municipality::query()->orderBy('name')->get());
        abort_if(! $municipality, 404, 'Select a municipality first.');
        $month = $this->resolvePeriod($request);

        $municipalReport = $this->currentMunicipalReport($municipality, $month);
        if ($municipalReport?->isFrozen()) {
            return redirect()->route('pto.municipalReports.officialReport', $municipalReport);
        }

        $data = $this->officialReportData($municipality, $month);

        OperationLogger::exported($request->user(), 'provincial_report', $municipality->id, ['action' => 'preview', 'period' => $month->toDateString()]);

        return view('pdf.official-report', [
            'report' => $data,
            'preview' => true,
            'pdfUrl' => route('pto.monthlyReports.officialReport.pdf', ['period' => $month->format('Y-m'), 'municipality_id' => $municipality->id]),
            'excelUrl' => route('pto.monthlyReports.officialReport.excel', ['period' => $month->format('Y-m'), 'municipality_id' => $municipality->id]),
        ]);
    }

    public function officialReportPdf(Request $request): Response|RedirectResponse
    {
        $municipality = $this->resolveMunicipality($request, Municipality::query()->orderBy('name')->get());
        abort_if(! $municipality, 404, 'Select a municipality first.');
        $month = $this->resolvePeriod($request);

        $municipalReport = $this->currentMunicipalReport($municipality, $month);
        if ($municipalReport?->isFrozen()) {
            return redirect()->route('pto.municipalReports.officialReport.pdf', $municipalReport);
        }

        $data = $this->officialReportData($municipality, $month);
        $this->abortIfUnbalanced($data);

        OperationLogger::exported($request->user(), 'provincial_report', $municipality->id, ['action' => 'download_pdf', 'period' => $month->toDateString()]);

        return Pdf::loadView('pdf.official-report', ['report' => $data, 'preview' => false])
            ->setPaper('a4')
            ->download("{$data['reference_number']}.pdf");
    }

    public function officialReportExcel(Request $request)
    {
        $municipality = $this->resolveMunicipality($request, Municipality::query()->orderBy('name')->get());
        abort_if(! $municipality, 404, 'Select a municipality first.');
        $month = $this->resolvePeriod($request);

        $municipalReport = $this->currentMunicipalReport($municipality, $month);
        if ($municipalReport?->isFrozen()) {
            return redirect()->route('pto.municipalReports.officialReport.excel', $municipalReport);
        }

        $data = $this->officialReportData($municipality, $month);
        $this->abortIfUnbalanced($data);

        OperationLogger::exported($request->user(), 'provincial_report', $municipality->id, ['action' => 'export_excel', 'period' => $month->toDateString()]);

        return Excel::download(new OfficialReportExport($data), "{$data['reference_number']}.xlsx");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function abortIfUnbalanced(array $data): void
    {
        $rows = $data['groups']->flatMap(fn (array $group) => $group['rows']);
        $errors = OfficialReportBuilder::validateColumnSums($rows);

        abort_if($errors !== [], 422, 'This report cannot be generated until its Male/Female, Adults/Children/Seniors, and Local/Foreign columns all add up to the Total for every establishment: '.implode(' ', $errors));
    }

    private function currentMunicipalReport(Municipality $municipality, CarbonImmutable $month): ?MunicipalReport
    {
        return MunicipalReport::query()
            ->with('reviewer')
            ->where('municipality_id', $municipality->id)
            ->whereDate('period_start', $month->toDateString())
            ->whereDoesntHave('supersededBy')
            ->first();
    }

    /**
     * Builds the generic shape resources/views/pdf/official-report.blade.php
     * renders, straight from establishment-level data — see
     * App\Support\OfficialReportBuilder. Always a live Draft: this method is
     * only ever reached when no Verified MunicipalReport exists yet for the
     * period (officialReport()/Pdf()/Excel() redirect to that one instead).
     *
     * @return array<string, mixed>
     */
    private function officialReportData(Municipality $municipality, CarbonImmutable $month): array
    {
        $establishments = Listing::query()
            ->with('categoryRecord')
            ->where('municipality_id', $municipality->id)
            ->get()
            ->filter(fn (Listing $listing) => $listing->isQrEnabled());

        $reportsByListing = MonthlyArrivalReport::query()
            ->where('municipality_id', $municipality->id)
            ->where('status', '!=', MonthlyReportStatus::Draft)
            ->forPeriod($month)
            ->with('submitter')
            ->get()
            ->keyBy('listing_id');

        $reported = $establishments->filter(fn (Listing $listing) => $reportsByListing->has($listing->id));

        $rows = $reported->map(function (Listing $listing) use ($reportsByListing) {
            $monthlyReport = $reportsByListing->get($listing->id);

            return [
                'establishment' => $listing->name,
                'category' => $listing->categoryRecord?->cat_name ?? 'Uncategorized',
                'source' => $monthlyReport->submission_source->label(),
                'male' => (int) $monthlyReport->party_male,
                'female' => (int) $monthlyReport->party_female,
                'total' => (int) $monthlyReport->total_visitors,
                'adults' => (int) $monthlyReport->party_adults,
                'children' => (int) $monthlyReport->party_children,
                'seniors' => (int) $monthlyReport->party_seniors,
                'local' => (int) $monthlyReport->party_local,
                'foreign' => (int) $monthlyReport->party_foreign,
            ];
        })->values();

        $digitalCount = $reported->filter(fn (Listing $listing) => $reportsByListing->get($listing->id)->submission_source === ReportSubmissionSource::Digital)->count();
        $paperCount = $reported->count() - $digitalCount;

        return [
            'title' => 'Provincial Tourism Report',
            'letterhead' => [
                'office_name' => 'Provincial Tourism Office',
                'office_subtitle' => 'Province of Davao Oriental — '.$municipality->name,
                'address' => 'Capitol Compound, Brgy. Dahican, City of Mati, Davao Oriental',
            ],
            'period_label' => $month->format('F Y'),
            'reference_number' => sprintf('PTR-%03d-%s', $municipality->id, $month->format('Ym')),
            'status_label' => 'Draft',
            'is_draft' => true,
            'verified_label' => null,
            'revision_number' => 1,
            'supersedes_reference' => null,
            'submission_summary' => "{$reported->count()} of {$establishments->count()} establishments reported ({$digitalCount} digital, {$paperCount} paper)",
            'groups' => OfficialReportBuilder::groupByCategory($rows),
            'grand_total' => OfficialReportBuilder::sumRows($rows),
            'remarks' => 'Preliminary — not yet consolidated or verified for this period.',
            'signatures' => [
                'prepared_by' => ['name' => null, 'position' => 'Provincial Tourism Office', 'date' => null],
                'reviewed_by' => ['name' => null, 'position' => 'Provincial Tourism Office', 'date' => null],
                'approved_by' => ['name' => null, 'position' => 'Provincial Tourism Office', 'date' => null],
            ],
            'verification_code' => null,
            'generated_at' => now()->format('M j, Y g:i A'),
        ];
    }

    private function resolveMunicipality(Request $request, Collection $municipalities): ?Municipality
    {
        $id = $request->query('municipality_id');

        if ($id && $match = $municipalities->firstWhere('id', (int) $id)) {
            return $match;
        }

        return $municipalities->first();
    }

    private function resolvePeriod(Request $request): CarbonImmutable
    {
        $period = $request->query('period');

        if (is_string($period) && preg_match('/^\d{4}-\d{2}$/', $period)) {
            return CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }
}
