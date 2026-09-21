<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: PTO review of consolidated municipal tourism reports submitted
 * by LGU Tourism Admins — list, detail, approve, and return-for-revision.
 *
 * There is no LGU-facing submission UI yet; App\Models\MunicipalReport rows
 * currently only come from Database\Seeders\MunicipalReportSeeder. Building
 * the LGU submission flow is a separate, larger piece of work — this
 * controller only covers the PTO side of reviewing rows that already exist.
 *
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Models\MunicipalReport;
use App\Support\TourismCatalog;
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

        return $this->renderPto($request, 'pto.municipal-reports.index', 'municipalReports', 'Municipal Reports', [
            'reports' => $reports,
            'municipalities' => TourismCatalog::municipalities(),
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
        $municipalReport->loadMissing(['submitter', 'reviewer']);

        return $this->renderPto($request, 'pto.municipal-reports.show', 'municipalReports', 'Municipal Report', [
            'report' => $municipalReport,
        ]);
    }

    public function approve(Request $request, MunicipalReport $municipalReport): RedirectResponse
    {
        abort_if($municipalReport->status === MunicipalReport::STATUS_APPROVED, 403, 'This report has already been approved.');

        $municipalReport->update([
            'status' => MunicipalReport::STATUS_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('toast', "{$municipalReport->municipality}'s report was approved.");
    }

    public function return(Request $request, MunicipalReport $municipalReport): RedirectResponse
    {
        abort_if($municipalReport->status === MunicipalReport::STATUS_APPROVED, 403, 'An approved report cannot be returned.');

        $data = $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        $municipalReport->update([
            'status' => MunicipalReport::STATUS_RETURNED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'remarks' => $data['remarks'],
        ]);

        return back()->with('toast', "{$municipalReport->municipality}'s report was returned for revision.");
    }
}
