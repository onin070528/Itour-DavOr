<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: PTO review of consolidated municipal tourism reports submitted
 * by LGU Tourism Admins — list every municipality for a period (even those
 * that haven't submitted), detail, verify, and return-for-clarification.
 *
 * MunicipalReport rows are created by Lgu\MonthlyReportsController::consolidate(),
 * which sums an LGU's Verified MonthlyArrivalReport rows for a municipality
 * and period and submits the result here (status SUBMITTED) — this
 * controller only covers the PTO side of reviewing rows that already exist.
 *
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Exports\OfficialReportExport;
use App\Models\Municipality;
use App\Models\MunicipalReport;
use App\Models\OperationLog;
use App\Support\OfficialReportBuilder;
use App\Support\OperationLogger;
use App\Support\TourismAnalytics;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class MunicipalReportsController extends PtoController
{
    /**
     * The four statuses this page ever shows (A4/A6: Not Submitted is a
     * literal label, never a stored status) — the single place that maps
     * MunicipalReport's stored SUBMITTED/REVIEWED/APPROVED/RETURNED onto
     * Stage 3's vocabulary.
     */
    private const ATTENTION_STATUSES = ['For Review', 'For Clarification', 'Not Submitted'];

    /**
     * LGU Submissions: one row per municipality (all 11, every time) for the
     * selected year/month — a municipality with no report row for that
     * period always reads "Not Submitted," never a missing row.
     */
    public function index(Request $objRequest): View
    {
        $intYear = (int) ($objRequest->query('year') ?: CarbonImmutable::now()->year);
        $intMonth = (int) ($objRequest->query('month') ?: CarbonImmutable::now()->month);
        $dtmPeriod = CarbonImmutable::create($intYear, $intMonth, 1);
        $strStatusFilter = $objRequest->query('status');

        $objReportsByMunicipality = MunicipalReport::query()
            ->with('submitter')
            ->whereYear('mrp_period_start', $intYear)
            ->whereMonth('mrp_period_start', $intMonth)
            ->whereNotNull('mun_id')
            ->whereDoesntHave('supersededBy')
            ->get()
            ->keyBy('mun_id');

        $objRows = Municipality::query()->orderBy('mun_name')->get()->map(function (Municipality $objMunicipality) use ($objReportsByMunicipality) {
            $objReport = $objReportsByMunicipality->get($objMunicipality->mun_id);

            return [
                'municipality' => $objMunicipality,
                'report' => $objReport,
                'status' => self::statusLabel($objReport?->mrp_status),
            ];
        });

        // "Reports Requiring Attention" on the Dashboard links here with
        // status=attention, which stands for the three non-Verified statuses.
        if ($strStatusFilter === 'attention') {
            $objRows = $objRows->filter(fn (array $arrRow) => in_array($arrRow['status'], self::ATTENTION_STATUSES, true))->values();
        }

        return $this->renderPto($objRequest, 'pto.municipal-reports.index', 'municipalReports', 'LGU Submissions', [
            'rows' => $objRows,
            'year' => $intYear,
            'month' => $intMonth,
            'period' => $dtmPeriod,
            'yearOptions' => TourismAnalytics::yearOptions(),
            'statusFilter' => $strStatusFilter,
        ]);
    }

    /**
     * Municipal Report detail: full report contents, the establishment
     * breakdown (QR-enabled records only — Listing::isQrEnabled() is the
     * single source of truth), a comparison with the previous period, and
     * the PTO's verify / return-for-clarification actions (hidden once
     * Verified).
     */
    public function show(Request $objRequest, MunicipalReport $municipalReport): View
    {
        abort_unless($objRequest->user()->can('view', $municipalReport), 403);

        [$objBreakdown, $objMissingEstablishments] = $this->loadBreakdown($municipalReport);

        $objPreviousReport = $municipalReport->mun_id
            ? MunicipalReport::query()
                ->where('mun_id', $municipalReport->mun_id)
                ->where('mrp_period_start', '<', $municipalReport->mrp_period_start)
                ->whereDoesntHave('supersededBy')
                ->orderByDesc('mrp_period_start')
                ->first()
            : null;

        $arrComparison = null;
        if ($objPreviousReport) {
            $intDifference = $municipalReport->mrp_total_arrivals - $objPreviousReport->mrp_total_arrivals;
            $arrComparison = [
                'report' => $objPreviousReport,
                'difference' => $intDifference,
                'percentageChange' => $objPreviousReport->mrp_total_arrivals > 0
                    ? round(($intDifference / $objPreviousReport->mrp_total_arrivals) * 100, 1)
                    : null,
            ];
        }

        $objHistory = OperationLog::query()
            ->where('opl_entity_type', 'municipal_report')
            ->where('opl_entity_id', $municipalReport->mrp_id)
            ->with('user')
            ->orderByDesc('opl_created_at')
            ->get();

        return $this->renderPto($objRequest, 'pto.municipal-reports.show', 'municipalReports', 'Municipal Report', [
            'report' => $municipalReport,
            'breakdown' => $objBreakdown,
            'missingEstablishments' => $objMissingEstablishments,
            'comparison' => $arrComparison,
            'history' => $objHistory,
        ]);
    }

    /**
     * Verify. Only a report currently pending review (SUBMITTED/REVIEWED)
     * can be verified — a Not Submitted "report" has no row to bind to this
     * route at all, and an already-Verified or Returned-but-unresolved
     * report must go through the LGU resubmission flow first.
     */
    public function approve(Request $objRequest, MunicipalReport $municipalReport): RedirectResponse
    {
        abort_unless($objRequest->user()->can('approve', $municipalReport), 403);
        abort_unless(
            in_array($municipalReport->mrp_status, [MunicipalReport::STATUS_SUBMITTED, MunicipalReport::STATUS_REVIEWED], true),
            403,
            'Only a report pending review can be verified.'
        );

        [$objBreakdown] = $this->loadBreakdown($municipalReport);
        $arrErrors = OfficialReportBuilder::validateColumnSums($this->breakdownToRows($objBreakdown));
        abort_if($arrErrors !== [], 422, 'This report cannot be verified until its Male/Female, Adults/Children/Seniors, and Local/Foreign columns all add up to the Total for every establishment: '.implode(' ', $arrErrors));

        $arrBefore = $municipalReport->getOriginal();

        try {
            $municipalReport->update([
                'mrp_status' => MunicipalReport::STATUS_APPROVED,
                'mrp_reviewed_by' => $objRequest->user()->usr_id,
                'mrp_reviewed_at' => now(),
                'mrp_verification_code' => OfficialReportBuilder::generateVerificationCode(),
            ]);

            // Frozen the moment it's Verified — the Official Report PDF for a
            // Verified report always renders from this snapshot, never from
            // live monthlyArrivalReports (which get reassigned to a brand-new
            // revision row the moment the LGU resubmits — see
            // Lgu\MonthlyReportsController::consolidate()).
            $municipalReport->update([
                'mrp_frozen_snapshot' => $this->officialReportData($municipalReport->fresh(['submitter', 'reviewer'])),
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to verify the municipal report.', ['exception' => $objException, 'mrp_id' => $municipalReport->mrp_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::approved($objRequest->user(), 'municipal_report', $municipalReport->mrp_id, $this->municipalityId($municipalReport), OperationLogger::diff($arrBefore, $municipalReport));

        return back()->with('toast', "{$municipalReport->mrp_municipality}'s report was verified.");
    }

    /**
     * Official Report preview — the paper-style view, separate from the
     * dashboard chrome. A Verified report renders its frozen_snapshot; any
     * other status renders live (and therefore still-changeable) data.
     */
    public function officialReport(Request $objRequest, MunicipalReport $municipalReport): View
    {
        abort_unless($objRequest->user()->can('export', $municipalReport), 403);

        $arrData = $municipalReport->isFrozen() && $municipalReport->mrp_frozen_snapshot
            ? $municipalReport->mrp_frozen_snapshot
            : $this->officialReportData($municipalReport);

        OperationLogger::exported($objRequest->user(), 'municipal_report', $this->municipalityId($municipalReport), ['action' => 'preview', 'report_id' => $municipalReport->mrp_id]);

        return view('pdf.official-report', [
            'report' => $arrData,
            'preview' => true,
            'pdfUrl' => route('pto.municipalReports.officialReport.pdf', $municipalReport),
            'excelUrl' => route('pto.municipalReports.officialReport.excel', $municipalReport),
        ]);
    }

    public function officialReportPdf(Request $objRequest, MunicipalReport $municipalReport): Response
    {
        abort_unless($objRequest->user()->can('export', $municipalReport), 403);

        $arrData = $municipalReport->isFrozen() && $municipalReport->mrp_frozen_snapshot
            ? $municipalReport->mrp_frozen_snapshot
            : $this->officialReportData($municipalReport);

        OperationLogger::exported($objRequest->user(), 'municipal_report', $this->municipalityId($municipalReport), ['action' => 'download_pdf', 'report_id' => $municipalReport->mrp_id]);

        $objPdf = Pdf::loadView('pdf.official-report', ['report' => $arrData, 'preview' => false])->setPaper('a4');

        return $objPdf->download("{$arrData['reference_number']}.pdf");
    }

    public function officialReportExcel(Request $objRequest, MunicipalReport $municipalReport)
    {
        abort_unless($objRequest->user()->can('export', $municipalReport), 403);

        $arrData = $municipalReport->isFrozen() && $municipalReport->mrp_frozen_snapshot
            ? $municipalReport->mrp_frozen_snapshot
            : $this->officialReportData($municipalReport);

        OperationLogger::exported($objRequest->user(), 'municipal_report', $this->municipalityId($municipalReport), ['action' => 'export_excel', 'report_id' => $municipalReport->mrp_id]);

        return Excel::download(new OfficialReportExport($arrData), "{$arrData['reference_number']}.xlsx");
    }

    /**
     * @return array{0: Collection, 1: Collection}
     */
    private function loadBreakdown(MunicipalReport $objMunicipalReport): array
    {
        return OfficialReportBuilder::municipalBreakdown($objMunicipalReport);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function breakdownToRows(Collection $objBreakdown): Collection
    {
        return OfficialReportBuilder::breakdownRows($objBreakdown);
    }

    /**
     * Builds the generic shape resources/views/pdf/official-report.blade.php
     * renders — shared with the LGU's Municipal Reports views, see
     * App\Support\OfficialReportBuilder::fromMunicipalReport().
     *
     * @return array<string, mixed>
     */
    private function officialReportData(MunicipalReport $objMunicipalReport): array
    {
        return OfficialReportBuilder::fromMunicipalReport($objMunicipalReport);
    }

    public function return(Request $objRequest, MunicipalReport $municipalReport): RedirectResponse
    {
        abort_unless($objRequest->user()->can('return', $municipalReport), 403);
        abort_if($municipalReport->mrp_status === MunicipalReport::STATUS_APPROVED, 403, 'A verified report cannot be returned — ask the LGU to resubmit instead.');

        $arrData = $objRequest->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        $arrBefore = $municipalReport->getOriginal();

        try {
            $municipalReport->update([
                'mrp_status' => MunicipalReport::STATUS_RETURNED,
                'mrp_reviewed_by' => $objRequest->user()->usr_id,
                'mrp_reviewed_at' => now(),
                'mrp_remarks' => $arrData['remarks'],
            ]);
        } catch (\Throwable $objException) {
            Log::error('Failed to return the municipal report for clarification.', ['exception' => $objException, 'mrp_id' => $municipalReport->mrp_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::returned($objRequest->user(), 'municipal_report', $municipalReport->mrp_id, $arrData['remarks'], $this->municipalityId($municipalReport), OperationLogger::diff($arrBefore, $municipalReport));

        return back()->with('toast', "{$municipalReport->mrp_municipality}'s report was returned for clarification.");
    }

    /**
     * Stage 3's four-status vocabulary (Not Submitted / For Review / For
     * Clarification / Verified) — the single place that maps onto it, so
     * the index table, the Dashboard's reporting-status table, and the
     * "Reports Requiring Attention" link never drift apart.
     */
    public static function statusLabel(?string $strStatus): string
    {
        return match ($strStatus) {
            MunicipalReport::STATUS_SUBMITTED, MunicipalReport::STATUS_REVIEWED => 'For Review',
            MunicipalReport::STATUS_APPROVED => 'Verified',
            MunicipalReport::STATUS_RETURNED => 'For Clarification',
            default => 'Not Submitted',
        };
    }

    /**
     * Prefers the real mun_id FK (set directly by
     * Lgu\MonthlyReportsController::consolidate() going forward); falls
     * back to a name lookup for any report that predates that FK or was
     * otherwise created without it.
     */
    private function municipalityId(MunicipalReport $objMunicipalReport): ?int
    {
        return $objMunicipalReport->mun_id ?? Municipality::query()->where('mun_name', $objMunicipalReport->mrp_municipality)->value('mun_id');
    }
}
