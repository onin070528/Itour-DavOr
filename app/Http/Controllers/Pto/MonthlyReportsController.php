<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Province-wide, read-only view of the same establishment × month
 * reporting status Lgu\MonthlyReportsController shows, switchable across
 * any municipality — encoding, reviewing, verifying, and consolidating
 * stay LGU-only actions; PTO only watches progress and drills into detail.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Enums\MonthlyReportStatus;
use App\Exports\OfficialReportExport;
use App\Http\Controllers\Concerns\TracksReportHistory;
use App\Models\Listing;
use App\Models\MonthlyArrivalReport;
use App\Models\Municipality;
use App\Models\MunicipalReport;
use App\Support\OfficialReportBuilder;
use App\Support\OperationLogger;
use App\Support\ReportWorkflowSteps;
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

    public function index(Request $objRequest): View
    {
        $objMunicipalities = Municipality::query()->orderBy('mun_name')->get();
        $dtmMonth = $this->resolvePeriod($objRequest);
        $objMunicipality = $this->resolveMunicipality($objRequest, $objMunicipalities);

        $objEstablishments = Listing::query()
            ->where('mun_id', $objMunicipality?->mun_id)
            ->where('lst_category', '!=', 'destinations')
            ->orderBy('lst_name')
            ->get();

        $objReportsByListing = MonthlyArrivalReport::query()
            ->where('mun_id', $objMunicipality?->mun_id)
            ->forPeriod($dtmMonth)
            ->with(['submitter', 'verifier'])
            ->get()
            ->keyBy('lst_id');

        $arrStatusPriority = ['Not Submitted' => 0, 'For Review' => 1, 'Verified' => 2];

        $objRows = $objEstablishments->map(function (Listing $objListing) use ($objReportsByListing) {
            $objReport = $objReportsByListing->get($objListing->lst_id);

            return [
                'listing' => $objListing,
                'report' => $objReport,
                'status' => $objReport?->mar_status->label() ?? 'Not Submitted',
            ];
        })->sortBy(fn (array $arrRow) => $arrStatusPriority[$arrRow['status']])->values();

        $intMissingCount = $objRows->whereNull('report')->count();
        $intForReviewCount = $objRows->filter(fn (array $arrRow) => $arrRow['report']?->mar_status === MonthlyReportStatus::ForReview)->count();
        $intVerifiedCount = $objRows->filter(fn (array $arrRow) => $arrRow['report']?->mar_status === MonthlyReportStatus::Verified)->count();
        $intSubmittedCount = $intForReviewCount + $intVerifiedCount;

        $blnAlreadyConsolidated = $objMunicipality && MunicipalReport::query()
            ->where('mun_id', $objMunicipality->mun_id)
            ->whereDate('mrp_period_start', $dtmMonth->toDateString())
            ->where('mrp_status', '!=', MunicipalReport::STATUS_RETURNED)
            ->whereDoesntHave('supersededBy')
            ->exists();

        return $this->renderPto($objRequest, 'pto.monthly-reports.index', 'monthlyReports', 'Provincial Reports', [
            'rows' => $objRows,
            'month' => $dtmMonth,
            'monthOptions' => $this->recentMonthOptions(),
            'municipalities' => $objMunicipalities,
            'municipality' => $objMunicipality,
            'missingCount' => $intMissingCount,
            'forReviewCount' => $intForReviewCount,
            'verifiedCount' => $intVerifiedCount,
            'submittedCount' => $intSubmittedCount,
            'alreadyConsolidated' => $blnAlreadyConsolidated,
            'steps' => ReportWorkflowSteps::compute($intSubmittedCount, $intForReviewCount, $intVerifiedCount, $blnAlreadyConsolidated),
        ]);
    }

    /**
     * Read-only mirror of Lgu\MonthlyReportsController::show() — same
     * detail, no Verify action.
     */
    public function show(Request $objRequest, MonthlyArrivalReport $monthlyArrivalReport): View
    {
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

        $intDigitalCount = $objReported->filter(fn (Listing $objListing) => $objReportsByListing->get($objListing->lst_id)->mar_submission_source->value === 'digital')->count();
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

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function recentMonthOptions(): Collection
    {
        return collect(range(0, 11))
            ->map(fn (int $intIndex) => CarbonImmutable::now()->subMonthsNoOverflow($intIndex)->startOfMonth());
    }
}
