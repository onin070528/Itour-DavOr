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
use App\Models\Listing;
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
    public function index(Request $request): View
    {
        $year = (int) ($request->query('year') ?: CarbonImmutable::now()->year);
        $month = (int) ($request->query('month') ?: CarbonImmutable::now()->month);
        $period = CarbonImmutable::create($year, $month, 1);
        $statusFilter = $request->query('status');

        $reportsByMunicipality = MunicipalReport::query()
            ->with('submitter')
            ->whereYear('period_start', $year)
            ->whereMonth('period_start', $month)
            ->whereNotNull('municipality_id')
            ->whereDoesntHave('supersededBy')
            ->get()
            ->keyBy('municipality_id');

        $rows = Municipality::query()->orderBy('name')->get()->map(function (Municipality $municipality) use ($reportsByMunicipality) {
            $report = $reportsByMunicipality->get($municipality->id);

            return [
                'municipality' => $municipality,
                'report' => $report,
                'status' => self::statusLabel($report?->status),
            ];
        });

        // "Reports Requiring Attention" on the Dashboard links here with
        // status=attention, which stands for the three non-Verified statuses.
        if ($statusFilter === 'attention') {
            $rows = $rows->filter(fn (array $row) => in_array($row['status'], self::ATTENTION_STATUSES, true))->values();
        }

        return $this->renderPto($request, 'pto.municipal-reports.index', 'municipalReports', 'LGU Submissions', [
            'rows' => $rows,
            'year' => $year,
            'month' => $month,
            'period' => $period,
            'yearOptions' => TourismAnalytics::yearOptions(),
            'statusFilter' => $statusFilter,
        ]);
    }

    /**
     * Municipal Report detail: full report contents, the establishment
     * breakdown (QR-enabled records only — Listing::isQrEnabled() is the
     * single source of truth), a comparison with the previous period, and
     * the PTO's verify / return-for-clarification actions (hidden once
     * Verified).
     */
    public function show(Request $request, MunicipalReport $municipalReport): View
    {
        [$breakdown, $missingEstablishments] = $this->loadBreakdown($municipalReport);

        $previousReport = $municipalReport->municipality_id
            ? MunicipalReport::query()
                ->where('municipality_id', $municipalReport->municipality_id)
                ->where('period_start', '<', $municipalReport->period_start)
                ->whereDoesntHave('supersededBy')
                ->orderByDesc('period_start')
                ->first()
            : null;

        $comparison = null;
        if ($previousReport) {
            $difference = $municipalReport->total_arrivals - $previousReport->total_arrivals;
            $comparison = [
                'report' => $previousReport,
                'difference' => $difference,
                'percentageChange' => $previousReport->total_arrivals > 0
                    ? round(($difference / $previousReport->total_arrivals) * 100, 1)
                    : null,
            ];
        }

        $history = OperationLog::query()
            ->where('entity_type', 'municipal_report')
            ->where('entity_id', $municipalReport->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return $this->renderPto($request, 'pto.municipal-reports.show', 'municipalReports', 'Municipal Report', [
            'report' => $municipalReport,
            'breakdown' => $breakdown,
            'missingEstablishments' => $missingEstablishments,
            'comparison' => $comparison,
            'history' => $history,
        ]);
    }

    /**
     * Verify. Only a report currently pending review (SUBMITTED/REVIEWED)
     * can be verified — a Not Submitted "report" has no row to bind to this
     * route at all, and an already-Verified or Returned-but-unresolved
     * report must go through the LGU resubmission flow first.
     */
    public function approve(Request $request, MunicipalReport $municipalReport): RedirectResponse
    {
        abort_unless(
            in_array($municipalReport->status, [MunicipalReport::STATUS_SUBMITTED, MunicipalReport::STATUS_REVIEWED], true),
            403,
            'Only a report pending review can be verified.'
        );

        [$breakdown] = $this->loadBreakdown($municipalReport);
        $errors = OfficialReportBuilder::validateColumnSums($this->breakdownToRows($breakdown));
        abort_if($errors !== [], 422, 'This report cannot be verified until its Male/Female, Adults/Children/Seniors, and Local/Foreign columns all add up to the Total for every establishment: '.implode(' ', $errors));

        $before = $municipalReport->getOriginal();

        $municipalReport->update([
            'status' => MunicipalReport::STATUS_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'verification_code' => OfficialReportBuilder::generateVerificationCode(),
        ]);

        // Frozen the moment it's Verified — the Official Report PDF for a
        // Verified report always renders from this snapshot, never from
        // live monthlyArrivalReports (which get reassigned to a brand-new
        // revision row the moment the LGU resubmits — see
        // Lgu\MonthlyReportsController::consolidate()).
        $municipalReport->update([
            'frozen_snapshot' => $this->officialReportData($municipalReport->fresh(['submitter', 'reviewer'])),
        ]);

        OperationLogger::approved($request->user(), 'municipal_report', $municipalReport->id, $this->municipalityId($municipalReport), OperationLogger::diff($before, $municipalReport));

        return back()->with('toast', "{$municipalReport->municipality}'s report was verified.");
    }

    /**
     * Official Report preview — the paper-style view, separate from the
     * dashboard chrome. A Verified report renders its frozen_snapshot; any
     * other status renders live (and therefore still-changeable) data.
     */
    public function officialReport(Request $request, MunicipalReport $municipalReport): View
    {
        $data = $municipalReport->isFrozen() && $municipalReport->frozen_snapshot
            ? $municipalReport->frozen_snapshot
            : $this->officialReportData($municipalReport);

        OperationLogger::exported($request->user(), 'municipal_report', $this->municipalityId($municipalReport), ['action' => 'preview', 'report_id' => $municipalReport->id]);

        return view('pdf.official-report', [
            'report' => $data,
            'preview' => true,
            'pdfUrl' => route('pto.municipalReports.officialReport.pdf', $municipalReport),
            'excelUrl' => route('pto.municipalReports.officialReport.excel', $municipalReport),
        ]);
    }

    public function officialReportPdf(Request $request, MunicipalReport $municipalReport): Response
    {
        $data = $municipalReport->isFrozen() && $municipalReport->frozen_snapshot
            ? $municipalReport->frozen_snapshot
            : $this->officialReportData($municipalReport);

        OperationLogger::exported($request->user(), 'municipal_report', $this->municipalityId($municipalReport), ['action' => 'download_pdf', 'report_id' => $municipalReport->id]);

        $pdf = Pdf::loadView('pdf.official-report', ['report' => $data, 'preview' => false])->setPaper('a4');

        return $pdf->download("{$data['reference_number']}.pdf");
    }

    public function officialReportExcel(Request $request, MunicipalReport $municipalReport)
    {
        $data = $municipalReport->isFrozen() && $municipalReport->frozen_snapshot
            ? $municipalReport->frozen_snapshot
            : $this->officialReportData($municipalReport);

        OperationLogger::exported($request->user(), 'municipal_report', $this->municipalityId($municipalReport), ['action' => 'export_excel', 'report_id' => $municipalReport->id]);

        return Excel::download(new OfficialReportExport($data), "{$data['reference_number']}.xlsx");
    }

    /**
     * @return array{0: Collection, 1: Collection}
     */
    private function loadBreakdown(MunicipalReport $municipalReport): array
    {
        $municipalReport->loadMissing(['submitter', 'reviewer', 'monthlyArrivalReports.listing.categoryRecord', 'monthlyArrivalReports.submitter', 'monthlyArrivalReports.verifier']);

        $breakdown = $municipalReport->monthlyArrivalReports->filter(
            fn ($monthlyReport) => $monthlyReport->listing?->isQrEnabled()
        )->values();

        $missingEstablishments = $municipalReport->municipality_id
            ? Listing::query()
                ->with('categoryRecord')
                ->where('municipality_id', $municipalReport->municipality_id)
                ->get()
                ->filter(fn (Listing $listing) => $listing->isQrEnabled() && ! $breakdown->contains(fn ($r) => $r->listing_id === $listing->id))
                ->values()
            : collect();

        return [$breakdown, $missingEstablishments];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function breakdownToRows(Collection $breakdown): Collection
    {
        return $breakdown->map(fn ($monthlyReport) => [
            'establishment' => $monthlyReport->listing->name,
            'category' => $monthlyReport->listing->categoryRecord?->cat_name ?? 'Uncategorized',
            'source' => $monthlyReport->submission_source->label(),
            'male' => (int) $monthlyReport->party_male,
            'female' => (int) $monthlyReport->party_female,
            'total' => (int) $monthlyReport->total_visitors,
            'adults' => (int) $monthlyReport->party_adults,
            'children' => (int) $monthlyReport->party_children,
            'seniors' => (int) $monthlyReport->party_seniors,
            'local' => (int) $monthlyReport->party_local,
            'foreign' => (int) $monthlyReport->party_foreign,
        ]);
    }

    /**
     * Builds the generic shape resources/views/pdf/official-report.blade.php
     * renders — see App\Support\OfficialReportBuilder.
     *
     * @return array<string, mixed>
     */
    private function officialReportData(MunicipalReport $municipalReport): array
    {
        [$breakdown, $missingEstablishments] = $this->loadBreakdown($municipalReport);
        $rows = $this->breakdownToRows($breakdown);

        $digitalCount = $breakdown->filter(fn ($r) => $r->submission_source->value === 'digital')->count();
        $paperCount = $breakdown->count() - $digitalCount;
        $totalEstablishments = $breakdown->count() + $missingEstablishments->count();

        return [
            'title' => 'LGU Consolidated Tourism Report',
            'letterhead' => [
                'office_name' => $municipalReport->municipality.' Tourism Office',
                'office_subtitle' => 'Local Government Unit, Province of Davao Oriental',
                'address' => 'Davao Oriental, Philippines',
            ],
            'period_label' => $municipalReport->period_start->format('F Y'),
            'reference_number' => sprintf('MRP-%06d', $municipalReport->id),
            'status_label' => self::statusLabel($municipalReport->status),
            'is_draft' => ! $municipalReport->isFrozen(),
            'verified_label' => $municipalReport->isFrozen() && $municipalReport->reviewed_at
                ? 'Verified on '.$municipalReport->reviewed_at->format('F j, Y').' by '.($municipalReport->reviewer->name ?? 'PTO Administrator')
                : null,
            'revision_number' => $municipalReport->revision_number,
            'supersedes_reference' => $municipalReport->supersedes_id ? sprintf('MRP-%06d', $municipalReport->supersedes_id) : null,
            'submission_summary' => "{$breakdown->count()} of {$totalEstablishments} establishments reported ({$digitalCount} digital, {$paperCount} paper)",
            'groups' => OfficialReportBuilder::groupByCategory($rows),
            'grand_total' => OfficialReportBuilder::sumRows($rows),
            'remarks' => $municipalReport->remarks,
            'signatures' => [
                'prepared_by' => [
                    'name' => $municipalReport->submitter->name ?? null,
                    'position' => $municipalReport->submitter?->role?->title(),
                    'date' => $municipalReport->created_at->format('M j, Y'),
                ],
                'reviewed_by' => [
                    'name' => $municipalReport->reviewer->name ?? null,
                    'position' => $municipalReport->reviewer?->role?->title(),
                    'date' => $municipalReport->reviewed_at?->format('M j, Y'),
                ],
                'approved_by' => [
                    'name' => $municipalReport->reviewer->name ?? null,
                    'position' => $municipalReport->reviewer?->role?->title(),
                    'date' => $municipalReport->reviewed_at?->format('M j, Y'),
                ],
            ],
            'verification_code' => $municipalReport->verification_code,
            'generated_at' => now()->format('M j, Y g:i A'),
        ];
    }

    public function return(Request $request, MunicipalReport $municipalReport): RedirectResponse
    {
        abort_if($municipalReport->status === MunicipalReport::STATUS_APPROVED, 403, 'A verified report cannot be returned — ask the LGU to resubmit instead.');

        $data = $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        $before = $municipalReport->getOriginal();

        $municipalReport->update([
            'status' => MunicipalReport::STATUS_RETURNED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'remarks' => $data['remarks'],
        ]);

        OperationLogger::returned($request->user(), 'municipal_report', $municipalReport->id, $data['remarks'], $this->municipalityId($municipalReport), OperationLogger::diff($before, $municipalReport));

        return back()->with('toast', "{$municipalReport->municipality}'s report was returned for clarification.");
    }

    /**
     * Stage 3's four-status vocabulary (Not Submitted / For Review / For
     * Clarification / Verified) — the single place that maps onto it, so
     * the index table, the Dashboard's reporting-status table, and the
     * "Reports Requiring Attention" link never drift apart.
     */
    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            MunicipalReport::STATUS_SUBMITTED, MunicipalReport::STATUS_REVIEWED => 'For Review',
            MunicipalReport::STATUS_APPROVED => 'Verified',
            MunicipalReport::STATUS_RETURNED => 'For Clarification',
            default => 'Not Submitted',
        };
    }

    /**
     * Prefers the real municipality_id FK (set directly by
     * Lgu\MonthlyReportsController::consolidate() going forward); falls
     * back to a name lookup for any report that predates that FK or was
     * otherwise created without it.
     */
    private function municipalityId(MunicipalReport $municipalReport): ?int
    {
        return $municipalReport->municipality_id ?? Municipality::query()->where('name', $municipalReport->municipality)->value('id');
    }
}
