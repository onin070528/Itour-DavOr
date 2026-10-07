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
    public function index(Request $objRequest): View
    {
        $arrYearOptions = TourismAnalytics::scopedYearOptions(null);
        $intYear = in_array((int) $objRequest->query('year'), $arrYearOptions, true) ? (int) $objRequest->query('year') : CarbonImmutable::now()->year;
        $strTab = in_array($objRequest->query('tab'), ['records', 'statistics'], true) ? $objRequest->query('tab') : 'overview';

        $objMunicipalities = Municipality::query()->orderBy('mun_name')->get();
        $intMunicipalityCount = $objMunicipalities->count();

        // Latest revision of every LGU's municipal report this year.
        $objMunicipalReports = MunicipalReport::query()
            ->with(['submitter', 'reviewer'])
            ->whereNotNull('mun_id')
            ->whereYear('mrp_period_start', $intYear)
            ->whereDoesntHave('supersededBy')
            ->get();
        $objReportsByMonth = $objMunicipalReports->groupBy(fn (MunicipalReport $objReport) => $objReport->mrp_period_start->month);

        // Establishment level, for the per-LGU drill-down inside each month's
        // modal. Drafts are owner-only, so they read as Not Submitted.
        $objEstablishmentReports = MonthlyArrivalReport::query()
            ->where('mar_status', '!=', MonthlyReportStatus::Draft)
            ->whereYear('mar_period_month', $intYear)
            ->get()
            ->groupBy(fn (MonthlyArrivalReport $objReport) => $objReport->mar_period_month->month.'-'.$objReport->mun_id);
        $objListingsByMunicipality = Listing::query()
            ->whereNotNull('mun_id')
            ->where('lst_category', '!=', 'destinations')
            ->orderBy('lst_name')
            ->get(['lst_id', 'lst_name', 'mun_id'])
            ->groupBy('mun_id');

        $objRecords = TourismAnalytics::provincialMonthlyRecords($intYear);
        $objRecordsByMonth = $objRecords->keyBy('month');
        $dtCurrentMonth = CarbonImmutable::now()->startOfMonth();

        $objMonths = collect(range(12, 1))
            ->map(fn (int $intMonth) => CarbonImmutable::create($intYear, $intMonth, 1))
            ->reject(fn (CarbonImmutable $dtMonth) => $dtMonth->greaterThan($dtCurrentMonth))
            ->map(fn (CarbonImmutable $dtMonth) => $this->_provincialMonth(
                $dtMonth,
                $objMunicipalities,
                ($objReportsByMonth->get($dtMonth->month) ?? collect())->keyBy('mun_id'),
                $objEstablishmentReports,
                $objListingsByMunicipality,
                $objRecordsByMonth->get($dtMonth->month),
            ))
            ->values();

        // Overview figures for the year.
        $intMonthsElapsed = $objMonths->count();
        $intApprovedCount = $objMunicipalReports->where('mrp_status', MunicipalReport::STATUS_APPROVED)->count();
        $intExpectedReports = $intMunicipalityCount * $intMonthsElapsed;

        $objPreviousRecords = TourismAnalytics::provincialMonthlyRecords($intYear - 1);
        $blnHasPrevious = $objPreviousRecords->contains('hasData', true);

        // Statistics: LGU reporting coverage for the year.
        $objLguCoverage = $objMunicipalities->map(function (Municipality $objMunicipality) use ($objMunicipalReports, $intMonthsElapsed) {
            $objOwn = $objMunicipalReports->where('mun_id', $objMunicipality->mun_id);
            $objApproved = $objOwn->where('mrp_status', MunicipalReport::STATUS_APPROVED);

            return [
                'municipality' => $objMunicipality,
                'submittedMonths' => $objOwn->count(),
                'verifiedMonths' => $objApproved->count(),
                'expectedMonths' => $intMonthsElapsed,
                'verifiedArrivals' => (int) $objApproved->sum('mrp_total_arrivals'),
            ];
        })->sortByDesc('verifiedArrivals')->values();

        return $this->renderPto($objRequest, 'pto.monthly-reports.index', 'monthlyReports', 'Provincial Reports', [
            'year' => $intYear,
            'yearOptions' => $arrYearOptions,
            'activeTab' => $strTab,
            'months' => $objMonths,
            'municipalityCount' => $intMunicipalityCount,
            'overview' => [
                'total' => (int) $objRecords->sum('total'),
                'lgusReported' => $objMunicipalReports->pluck('mun_id')->unique()->count(),
                'verifiedCount' => $intApprovedCount,
                'forCorrectionCount' => $objMunicipalReports->where('mrp_status', MunicipalReport::STATUS_RETURNED)->count(),
                'monthsCompleted' => $objMonths->where('isComplete', true)->count(),
                'monthsElapsed' => $intMonthsElapsed,
                'coveragePercent' => $intExpectedReports > 0 ? round($intApprovedCount / $intExpectedReports * 100, 1) : 0,
                'pendingReports' => $objMonths->flatMap(fn (array $arrMonth) => $arrMonth['lgus']->filter(fn (array $arrLgu) => $arrLgu['isPendingPto'])->map(fn (array $arrLgu) => [...$arrLgu, 'month' => $arrMonth['month']])),
            ],
            'statistics' => [
                'records' => $objRecords,
                'summary' => TourismAnalytics::yearSummary($objRecords),
                'hasPrevious' => $blnHasPrevious,
                'compare' => $objRequest->boolean('compare') && $blnHasPrevious,
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
            $objReport = $objReportsByMunicipality->get($objMunicipality->mun_id);
            $objEstReports = ($objEstablishmentReports->get($dtMonth->month.'-'.$objMunicipality->mun_id) ?? collect())->keyBy('lst_id');

            $objEstablishments = ($objListingsByMunicipality->get($objMunicipality->mun_id) ?? collect())->map(fn (Listing $objListing) => [
                'name' => $objListing->lst_name,
                'report' => $objEstReports->get($objListing->lst_id),
            ]);

            return [
                'municipality' => $objMunicipality,
                'report' => $objReport,
                'status' => MunicipalReportsController::statusLabel($objReport?->mrp_status),
                'isPendingPto' => in_array($objReport?->mrp_status, [MunicipalReport::STATUS_SUBMITTED, MunicipalReport::STATUS_REVIEWED], true),
                'establishments' => $objEstablishments,
                'verifiedEstablishmentCount' => $objEstReports->where('mar_status', MonthlyReportStatus::Verified)->count(),
            ];
        });

        $intTotal = $objMunicipalities->count();
        $intApproved = $objLgus->filter(fn (array $arrLgu) => $arrLgu['report']?->mrp_status === MunicipalReport::STATUS_APPROVED)->count();
        $intPending = $objLgus->where('isPendingPto', true)->count();
        $intReturned = $objLgus->filter(fn (array $arrLgu) => $arrLgu['report']?->mrp_status === MunicipalReport::STATUS_RETURNED)->count();
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
    public function show(Request $objRequest, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        abort_unless($objRequest->user()->can('view', $monthlyArrivalReport), 403);

        $monthlyArrivalReport->loadMissing(['listing', 'submitter', 'verifier', 'arrivals']);

        return $this->renderPto($objRequest, 'pto.monthly-reports.show', 'monthlyReports', 'Monthly Report', [
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
    public function officialReport(Request $objRequest): View|RedirectResponse
    {
        $objMunicipality = $this->resolveMunicipality($objRequest, Municipality::query()->orderBy('mun_name')->get());
        abort_if(! $objMunicipality, 404, 'Select a municipality first.');
        $dtmMonth = $this->resolvePeriod($objRequest);

        $objMunicipalReport = $this->currentMunicipalReport($objMunicipality, $dtmMonth);
        if ($objMunicipalReport?->isFrozen()) {
            return redirect()->route('pto.municipalReports.officialReport', $objMunicipalReport);
        }

        $arrData = $this->officialReportData($objMunicipality, $dtmMonth);

        OperationLogger::exported($objRequest->user(), 'provincial_report', $objMunicipality->mun_id, ['action' => 'preview', 'period' => $dtmMonth->toDateString()]);

        return view('pdf.official-report', [
            'report' => $arrData,
            'preview' => true,
            'pdfUrl' => route('pto.monthlyReports.officialReport.pdf', ['period' => $dtmMonth->format('Y-m'), 'municipality_id' => $objMunicipality->mun_id]),
            'excelUrl' => route('pto.monthlyReports.officialReport.excel', ['period' => $dtmMonth->format('Y-m'), 'municipality_id' => $objMunicipality->mun_id]),
        ]);
    }

    public function officialReportPdf(Request $objRequest): Response|RedirectResponse
    {
        $objMunicipality = $this->resolveMunicipality($objRequest, Municipality::query()->orderBy('mun_name')->get());
        abort_if(! $objMunicipality, 404, 'Select a municipality first.');
        $dtmMonth = $this->resolvePeriod($objRequest);

        $objMunicipalReport = $this->currentMunicipalReport($objMunicipality, $dtmMonth);
        if ($objMunicipalReport?->isFrozen()) {
            return redirect()->route('pto.municipalReports.officialReport.pdf', $objMunicipalReport);
        }

        $arrData = $this->officialReportData($objMunicipality, $dtmMonth);
        $this->abortIfUnbalanced($arrData);

        OperationLogger::exported($objRequest->user(), 'provincial_report', $objMunicipality->mun_id, ['action' => 'download_pdf', 'period' => $dtmMonth->toDateString()]);

        return Pdf::loadView('pdf.official-report', ['report' => $arrData, 'preview' => false])
            ->setPaper('a4')
            ->download("{$arrData['reference_number']}.pdf");
    }

    public function officialReportExcel(Request $objRequest)
    {
        $objMunicipality = $this->resolveMunicipality($objRequest, Municipality::query()->orderBy('mun_name')->get());
        abort_if(! $objMunicipality, 404, 'Select a municipality first.');
        $dtmMonth = $this->resolvePeriod($objRequest);

        $objMunicipalReport = $this->currentMunicipalReport($objMunicipality, $dtmMonth);
        if ($objMunicipalReport?->isFrozen()) {
            return redirect()->route('pto.municipalReports.officialReport.excel', $objMunicipalReport);
        }

        $arrData = $this->officialReportData($objMunicipality, $dtmMonth);
        $this->abortIfUnbalanced($arrData);

        OperationLogger::exported($objRequest->user(), 'provincial_report', $objMunicipality->mun_id, ['action' => 'export_excel', 'period' => $dtmMonth->toDateString()]);

        return Excel::download(new OfficialReportExport($arrData), "{$arrData['reference_number']}.xlsx");
    }

    /**
     * @param  array<string, mixed>  $arrData
     */
    private function abortIfUnbalanced(array $arrData): void
    {
        $objRows = $arrData['groups']->flatMap(fn (array $arrGroup) => $arrGroup['rows']);
        $arrErrors = OfficialReportBuilder::validateColumnSums($objRows);

        abort_if($arrErrors !== [], 422, 'This report cannot be generated until its Male/Female, Adults/Children/Seniors, and Local/Foreign columns all add up to the Total for every establishment: '.implode(' ', $arrErrors));
    }

    private function currentMunicipalReport(Municipality $objMunicipality, CarbonImmutable $dtmMonth): ?MunicipalReport
    {
        return MunicipalReport::query()
            ->with('reviewer')
            ->where('mun_id', $objMunicipality->mun_id)
            ->whereDate('mrp_period_start', $dtmMonth->toDateString())
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
    private function officialReportData(Municipality $objMunicipality, CarbonImmutable $dtmMonth): array
    {
        $objEstablishments = Listing::query()
            ->with('categoryRecord')
            ->where('mun_id', $objMunicipality->mun_id)
            ->get()
            ->filter(fn (Listing $objListing) => $objListing->isQrEnabled());

        $objReportsByListing = MonthlyArrivalReport::query()
            ->where('mun_id', $objMunicipality->mun_id)
            ->where('mar_status', '!=', MonthlyReportStatus::Draft)
            ->forPeriod($dtmMonth)
            ->with('submitter')
            ->get()
            ->keyBy('lst_id');

        $objReported = $objEstablishments->filter(fn (Listing $objListing) => $objReportsByListing->has($objListing->lst_id));

        $objRows = $objReported->map(function (Listing $objListing) use ($objReportsByListing) {
            $objMonthlyReport = $objReportsByListing->get($objListing->lst_id);

            return [
                'establishment' => $objListing->lst_name,
                'category' => $objListing->categoryRecord?->cat_name ?? 'Uncategorized',
                'source' => $objMonthlyReport->mar_submission_source->label(),
                'male' => (int) $objMonthlyReport->mar_party_male,
                'female' => (int) $objMonthlyReport->mar_party_female,
                'total' => (int) $objMonthlyReport->mar_total_visitors,
                'adults' => (int) $objMonthlyReport->mar_party_adults,
                'children' => (int) $objMonthlyReport->mar_party_children,
                'seniors' => (int) $objMonthlyReport->mar_party_seniors,
                'local' => (int) $objMonthlyReport->mar_party_local,
                'foreign' => (int) $objMonthlyReport->mar_party_foreign,
            ];
        })->values();

        $intDigitalCount = $objReported->filter(fn (Listing $objListing) => $objReportsByListing->get($objListing->lst_id)->mar_submission_source === ReportSubmissionSource::Digital)->count();
        $intPaperCount = $objReported->count() - $intDigitalCount;

        return [
            'title' => 'Provincial Tourism Report',
            'letterhead' => [
                'office_name' => 'Provincial Tourism Office',
                'office_subtitle' => 'Province of Davao Oriental — '.$objMunicipality->mun_name,
                'address' => 'Capitol Compound, Brgy. Dahican, City of Mati, Davao Oriental',
            ],
            'period_label' => $dtmMonth->format('F Y'),
            'reference_number' => sprintf('PTR-%03d-%s', $objMunicipality->mun_id, $dtmMonth->format('Ym')),
            'status_label' => 'Draft',
            'is_draft' => true,
            'verified_label' => null,
            'revision_number' => 1,
            'supersedes_reference' => null,
            'submission_summary' => "{$objReported->count()} of {$objEstablishments->count()} establishments reported ({$intDigitalCount} digital, {$intPaperCount} paper)",
            'groups' => OfficialReportBuilder::groupByCategory($objRows),
            'grand_total' => OfficialReportBuilder::sumRows($objRows),
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

    private function resolveMunicipality(Request $objRequest, Collection $objMunicipalities): ?Municipality
    {
        $strId = $objRequest->query('municipality_id');

        if ($strId && $objMatch = $objMunicipalities->firstWhere('mun_id', (int) $strId)) {
            return $objMatch;
        }

        return $objMunicipalities->first();
    }

    private function resolvePeriod(Request $objRequest): CarbonImmutable
    {
        $strPeriod = $objRequest->query('period');

        if (is_string($strPeriod) && preg_match('/^\d{4}-\d{2}$/', $strPeriod)) {
            return CarbonImmutable::createFromFormat('Y-m', $strPeriod)->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }
}
