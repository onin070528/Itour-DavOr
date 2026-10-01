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
use App\Http\Controllers\Concerns\TracksReportHistory;
use App\Models\Listing;
use App\Models\MonthlyArrivalReport;
use App\Models\Municipality;
use App\Models\MunicipalReport;
use App\Support\ReportWorkflowSteps;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MonthlyReportsController extends PtoController
{
    use TracksReportHistory;

    public function index(Request $request): View
    {
        $municipalities = Municipality::query()->orderBy('name')->get();
        $month = $this->resolvePeriod($request);
        $municipality = $this->resolveMunicipality($request, $municipalities);

        $establishments = Listing::query()
            ->where('municipality_id', $municipality?->id)
            ->where('category', '!=', 'destinations')
            ->orderBy('name')
            ->get();

        $reportsByListing = MonthlyArrivalReport::query()
            ->where('municipality_id', $municipality?->id)
            ->forPeriod($month)
            ->with(['submitter', 'verifier'])
            ->get()
            ->keyBy('listing_id');

        $statusPriority = ['Not Submitted' => 0, 'For Review' => 1, 'Verified' => 2];

        $rows = $establishments->map(function (Listing $listing) use ($reportsByListing) {
            $report = $reportsByListing->get($listing->id);

            return [
                'listing' => $listing,
                'report' => $report,
                'status' => $report?->status->label() ?? 'Not Submitted',
            ];
        })->sortBy(fn (array $row) => $statusPriority[$row['status']])->values();

        $missingCount = $rows->whereNull('report')->count();
        $forReviewCount = $rows->filter(fn (array $row) => $row['report']?->status === MonthlyReportStatus::ForReview)->count();
        $verifiedCount = $rows->filter(fn (array $row) => $row['report']?->status === MonthlyReportStatus::Verified)->count();
        $submittedCount = $forReviewCount + $verifiedCount;

        $alreadyConsolidated = $municipality && MunicipalReport::query()
            ->where('municipality_id', $municipality->id)
            ->whereDate('period_start', $month->toDateString())
            ->where('status', '!=', MunicipalReport::STATUS_RETURNED)
            ->exists();

        return $this->renderPto($request, 'pto.monthly-reports.index', 'monthlyReports', 'Tourism Reports', [
            'rows' => $rows,
            'month' => $month,
            'monthOptions' => $this->recentMonthOptions(),
            'municipalities' => $municipalities,
            'municipality' => $municipality,
            'missingCount' => $missingCount,
            'forReviewCount' => $forReviewCount,
            'verifiedCount' => $verifiedCount,
            'submittedCount' => $submittedCount,
            'alreadyConsolidated' => $alreadyConsolidated,
            'steps' => ReportWorkflowSteps::compute($submittedCount, $forReviewCount, $verifiedCount, $alreadyConsolidated),
        ]);
    }

    /**
     * Read-only mirror of Lgu\MonthlyReportsController::show() — same
     * detail, no Verify action.
     */
    public function show(Request $request, MonthlyArrivalReport $monthlyArrivalReport): View
    {
        $monthlyArrivalReport->loadMissing(['listing', 'submitter', 'verifier', 'arrivals']);

        return $this->renderPto($request, 'pto.monthly-reports.show', 'monthlyReports', 'Monthly Report', [
            'report' => $monthlyArrivalReport,
            'history' => $this->reportHistory($monthlyArrivalReport),
        ]);
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

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function recentMonthOptions(): Collection
    {
        return collect(range(0, 11))
            ->map(fn (int $i) => CarbonImmutable::now()->subMonthsNoOverflow($i)->startOfMonth());
    }
}
