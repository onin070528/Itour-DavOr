<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: PTO review of consolidated municipal tourism reports submitted
 * by LGU Tourism Admins — list, detail, approve, and return-for-revision.
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

use App\Models\Municipality;
use App\Models\MunicipalReport;
use App\Support\OperationLogger;
use App\Support\TourismCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MunicipalReportsController extends PtoController
{
    /**
     * Municipal Reports: every submitted report, filterable client-side by
     * municipality and status (same data-filterable-table pattern as the
     * rest of the PTO dashboard).
     */
    public function index(Request $request): View
    {
        $reports = MunicipalReport::query()
            ->with(['submitter', 'reviewer'])
            ->orderByDesc('period_start')
            ->get();

        // "Monitor which LGUs have submitted" for the current reporting
        // month specifically — the main $reports list below spans every
        // historical period, so a municipality with an old report but
        // nothing yet this month would otherwise look like it's reporting
        // when it isn't.
        $currentMonth = CarbonImmutable::now()->startOfMonth();
        $reportingMunicipalityIds = MunicipalReport::query()
            ->whereDate('period_start', $currentMonth->toDateString())
            ->where('status', '!=', MunicipalReport::STATUS_RETURNED)
            ->whereNotNull('municipality_id')
            ->pluck('municipality_id');
        $notReportingMunicipalities = Municipality::query()
            ->whereNotIn('id', $reportingMunicipalityIds)
            ->orderBy('name')
            ->get();

        return $this->renderPto($request, 'pto.municipal-reports.index', 'municipalReports', 'LGU Reports', [
            'reports' => $reports,
            'municipalities' => TourismCatalog::municipalities(),
            'currentMonth' => $currentMonth,
            'notReportingMunicipalities' => $notReportingMunicipalities,
            'statuses' => [
                MunicipalReport::STATUS_SUBMITTED,
                MunicipalReport::STATUS_REVIEWED,
                MunicipalReport::STATUS_APPROVED,
                MunicipalReport::STATUS_RETURNED,
            ],
        ]);
    }

    /**
     * Municipal Report detail: full report contents plus the PTO's
     * approve / return-for-revision actions (hidden once APPROVED).
     */
    public function show(Request $request, MunicipalReport $municipalReport): View
    {
        $municipalReport->loadMissing(['submitter', 'reviewer', 'monthlyArrivalReports.listing', 'monthlyArrivalReports.submitter', 'monthlyArrivalReports.verifier']);

        return $this->renderPto($request, 'pto.municipal-reports.show', 'municipalReports', 'Municipal Report', [
            'report' => $municipalReport,
        ]);
    }

    public function approve(Request $request, MunicipalReport $municipalReport): RedirectResponse
    {
        abort_if($municipalReport->status === MunicipalReport::STATUS_APPROVED, 403, 'This report has already been approved.');

        $before = $municipalReport->getOriginal();

        $municipalReport->update([
            'status' => MunicipalReport::STATUS_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        OperationLogger::approved($request->user(), 'municipal_report', $municipalReport->id, $this->municipalityId($municipalReport), OperationLogger::diff($before, $municipalReport));

        return back()->with('toast', "{$municipalReport->municipality}'s report was approved.");
    }

    public function return(Request $request, MunicipalReport $municipalReport): RedirectResponse
    {
        abort_if($municipalReport->status === MunicipalReport::STATUS_APPROVED, 403, 'An approved report cannot be returned.');

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

        return back()->with('toast', "{$municipalReport->municipality}'s report was returned for revision.");
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
