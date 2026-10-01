<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Every aggregation query behind the PTO Tourism Monitoring
 * Dashboard — KPIs, arrival trend, LGU comparison, reporting status, period-
 * to-period comparison, and visitor-classification breakdown. All built on
 * real MonthlyArrivalReport/MunicipalReport data (never mock), and every
 * official total only ever counts Verified reports — a missing report is
 * never treated as a zero-arrival one.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Enums\MonthlyReportStatus;
use App\Models\MonthlyArrivalReport;
use App\Models\Municipality;
use App\Models\MunicipalReport;
use App\Models\OperationLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TourismAnalytics
{
    /**
     * @return array{year: int, month: ?int, municipalityId: ?int, listingId: ?int, classification: ?string}
     */
    public static function resolveFilters(Request $request): array
    {
        $year = (int) ($request->query('year') ?: CarbonImmutable::now()->year);

        $month = $request->query('month');
        $month = ($month !== null && $month !== '') ? (int) $month : null;

        $municipalityId = $request->query('municipality_id');
        $municipalityId = ($municipalityId !== null && $municipalityId !== '') ? (int) $municipalityId : null;

        $listingId = $request->query('listing_id');
        $listingId = ($listingId !== null && $listingId !== '') ? (int) $listingId : null;

        $classification = $request->query('classification');
        $classification = in_array($classification, ['local', 'foreign'], true) ? $classification : null;

        return compact('year', 'month', 'municipalityId', 'listingId', 'classification');
    }

    /**
     * Distinct years with any reporting data, newest first — falls back to
     * the current year if the table is empty so the filter bar always has
     * at least one option.
     *
     * @return array<int, int>
     */
    public static function yearOptions(): array
    {
        $years = MonthlyArrivalReport::query()
            ->get(['period_month'])
            ->map(fn (MonthlyArrivalReport $row) => $row->period_month->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $years ?: [CarbonImmutable::now()->year];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array{label: string, value: string, delta: ?string, tone: string}>
     */
    public static function kpis(array $filters, array $comparison): array
    {
        $column = self::arrivalColumn($filters);

        $totalArrivals = (int) self::verifiedReportsQuery($filters)->sum($column);
        $domestic = (int) self::verifiedReportsQuery($filters)->sum('party_local');
        $foreign = (int) self::verifiedReportsQuery($filters)->sum('party_foreign');
        $verifiedReportsCount = (int) self::verifiedReportsQuery($filters)->count();

        $totalMunicipalities = $filters['municipalityId'] ? 1 : Municipality::query()->count();

        $municipalReportRows = MunicipalReport::query()
            ->whereYear('period_start', $filters['year'])
            ->when($filters['month'] ?? null, fn (Builder $q, int $m) => $q->whereMonth('period_start', $m))
            ->when($filters['municipalityId'] ?? null, fn (Builder $q, int $id) => $q->where('municipality_id', $id))
            ->whereNotNull('municipality_id')
            ->get(['municipality_id', 'status']);

        $reportingMunicipalities = $municipalReportRows->where('status', '!=', MunicipalReport::STATUS_RETURNED)->pluck('municipality_id')->unique()->count();
        $forClarificationMunicipalities = $municipalReportRows->where('status', MunicipalReport::STATUS_RETURNED)->pluck('municipality_id')->unique()->count();
        $notSubmittedMunicipalities = max(0, $totalMunicipalities - $reportingMunicipalities - $forClarificationMunicipalities);

        $forReviewCount = (int) MonthlyArrivalReport::query()
            ->where('status', MonthlyReportStatus::ForReview)
            ->whereYear('period_month', $filters['year'])
            ->when($filters['month'] ?? null, fn (Builder $q, int $m) => $q->whereMonth('period_month', $m))
            ->when($filters['municipalityId'] ?? null, fn (Builder $q, int $id) => $q->where('municipality_id', $id))
            ->when($filters['listingId'] ?? null, fn (Builder $q, int $id) => $q->where('listing_id', $id))
            ->count();

        $attentionCount = $forReviewCount + $forClarificationMunicipalities + $notSubmittedMunicipalities;

        $arrivalsDelta = match ($comparison['label']) {
            'Increased' => '+'.number_format(abs($comparison['percentageChange']), 1).'% '.$comparison['periodLabel'],
            'Decreased' => '-'.number_format(abs($comparison['percentageChange']), 1).'% '.$comparison['periodLabel'],
            'Stable' => 'Stable '.$comparison['periodLabel'],
            default => 'No comparison available',
        };
        $arrivalsTone = match ($comparison['label']) {
            'Increased' => 'success',
            'Decreased' => 'danger',
            default => 'neutral',
        };

        return [
            ['label' => 'Total Tourist Arrivals', 'value' => number_format($totalArrivals), 'delta' => $arrivalsDelta, 'tone' => $arrivalsTone],
            ['label' => 'Domestic Visitors', 'value' => number_format($domestic), 'delta' => null, 'tone' => 'neutral'],
            ['label' => 'Foreign Visitors', 'value' => number_format($foreign), 'delta' => null, 'tone' => 'neutral'],
            ['label' => 'Reporting LGUs', 'value' => "{$reportingMunicipalities}/{$totalMunicipalities}", 'delta' => null, 'tone' => $reportingMunicipalities === $totalMunicipalities ? 'success' : 'warning'],
            ['label' => 'Verified Reports', 'value' => number_format($verifiedReportsCount), 'delta' => null, 'tone' => 'success'],
            [
                'label' => 'Reports Requiring Attention',
                'value' => number_format($attentionCount),
                'delta' => $attentionCount ? "{$forReviewCount} For Review · {$forClarificationMunicipalities} For Clarification · {$notSubmittedMunicipalities} Not Submitted" : null,
                'tone' => $attentionCount ? 'warning' : 'success',
            ],
        ];
    }

    /**
     * Shaped for direct reuse with resources/js/dashboard.js's existing
     * initTrendCharts() ([data-trend-chart] + [data-trend-period]) — a
     * generic SVG line-chart renderer already in this app. 'month' = every
     * month of the selected year (the month filter itself is ignored here,
     * since a single month has nothing to trend against); 'year' = one
     * point per year that has any verified data at all.
     *
     * @param  array<string, mixed>  $filters
     * @return array{month: array<int, array{label: string, value: int}>, year: array<int, array{label: string, value: int}>}
     */
    public static function arrivalTrend(array $filters): array
    {
        $column = self::arrivalColumn($filters);

        $yearRows = MonthlyArrivalReport::query()
            ->where('status', MonthlyReportStatus::Verified)
            ->whereYear('period_month', $filters['year'])
            ->when($filters['municipalityId'] ?? null, fn (Builder $q, int $id) => $q->where('municipality_id', $id))
            ->when($filters['listingId'] ?? null, fn (Builder $q, int $id) => $q->where('listing_id', $id))
            ->get(['period_month', $column]);

        $byMonth = $yearRows->groupBy(fn (MonthlyArrivalReport $row) => $row->period_month->month)
            ->map(fn (Collection $rows) => (int) $rows->sum($column));

        $monthSeries = collect(range(1, 12))->map(fn (int $m) => [
            'label' => CarbonImmutable::create($filters['year'], $m, 1)->format('M'),
            'value' => (int) ($byMonth[$m] ?? 0),
        ])->all();

        $allRows = MonthlyArrivalReport::query()
            ->where('status', MonthlyReportStatus::Verified)
            ->when($filters['municipalityId'] ?? null, fn (Builder $q, int $id) => $q->where('municipality_id', $id))
            ->when($filters['listingId'] ?? null, fn (Builder $q, int $id) => $q->where('listing_id', $id))
            ->get(['period_month', $column]);

        $yearSeries = $allRows->groupBy(fn (MonthlyArrivalReport $row) => $row->period_month->year)
            ->sortKeys()
            ->map(fn (Collection $rows, int $year) => ['label' => (string) $year, 'value' => (int) $rows->sum($column)])
            ->values()
            ->all();

        return ['month' => $monthSeries, 'year' => $yearSeries];
    }

    /**
     * Verified arrivals grouped by municipality, each row carrying a
     * percentage-of-max for a plain CSS-width bar — no chart library.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{municipality: Municipality, total: int, percentage: int}>
     */
    public static function municipalityComparison(array $filters): Collection
    {
        $column = self::arrivalColumn($filters);

        $rows = self::verifiedReportsQuery($filters)->get(['municipality_id', $column]);

        $totals = $rows->groupBy('municipality_id')
            ->map(fn (Collection $group) => (int) $group->sum($column));

        $municipalities = Municipality::query()->whereIn('id', $totals->keys())->get()->keyBy('id');
        $max = $totals->max() ?: 1;

        return $totals->map(fn (int $total, int $municipalityId) => [
            'municipality' => $municipalities->get($municipalityId),
            'total' => $total,
            'percentage' => (int) round(($total / $max) * 100),
        ])->filter(fn (array $row) => $row['municipality'] !== null)
            ->sortByDesc('total')
            ->values();
    }

    /**
     * One row per municipality for the selected period — "Not Submitted"
     * is a literal label, never a stored or displayed zero.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{municipality: Municipality, report: ?MunicipalReport, status: string}>
     */
    public static function reportingStatus(array $filters): Collection
    {
        $municipalities = Municipality::query()
            ->when($filters['municipalityId'] ?? null, fn (Builder $q, int $id) => $q->where('id', $id))
            ->orderBy('name')
            ->get();

        $reports = MunicipalReport::query()
            ->whereYear('period_start', $filters['year'])
            ->when($filters['month'] ?? null, fn (Builder $q, int $m) => $q->whereMonth('period_start', $m))
            ->whereNotNull('municipality_id')
            ->get()
            ->keyBy('municipality_id');

        return $municipalities->map(function (Municipality $municipality) use ($reports) {
            $report = $reports->get($municipality->id);

            return [
                'municipality' => $municipality,
                'report' => $report,
                'status' => match ($report?->status) {
                    'SUBMITTED', 'REVIEWED' => 'For Validation',
                    'APPROVED' => 'Validated',
                    'RETURNED' => 'For Clarification',
                    default => 'Not Submitted',
                },
            ];
        });
    }

    /**
     * Current vs previous comparable period (previous month if a month is
     * selected, else previous year). Null-safe: an absent previous period
     * never produces a misleading percentage.
     *
     * @param  array<string, mixed>  $filters
     * @return array{current: int, previous: ?int, difference: ?int, percentageChange: ?float, label: string, periodLabel: string}
     */
    public static function periodComparison(array $filters): array
    {
        $column = self::arrivalColumn($filters);
        $current = (int) self::verifiedReportsQuery($filters)->sum($column);

        if ($filters['month'] ?? null) {
            $previousMonth = CarbonImmutable::create($filters['year'], $filters['month'], 1)->subMonthNoOverflow();
            $previousFilters = array_merge($filters, ['year' => $previousMonth->year, 'month' => $previousMonth->month]);
            $periodLabel = 'vs last month';
        } else {
            $previousFilters = array_merge($filters, ['year' => $filters['year'] - 1, 'month' => null]);
            $periodLabel = 'vs last year';
        }

        $previousExists = self::verifiedReportsQuery($previousFilters)->exists();

        if (! $previousExists) {
            return [
                'current' => $current, 'previous' => null, 'difference' => null,
                'percentageChange' => null, 'label' => 'No comparison available', 'periodLabel' => $periodLabel,
            ];
        }

        $previous = (int) self::verifiedReportsQuery($previousFilters)->sum($column);
        $difference = $current - $previous;
        $percentageChange = $previous > 0 ? round(($difference / $previous) * 100, 1) : null;

        $label = match (true) {
            $previous === 0 && $current === 0 => 'Stable',
            $percentageChange === null => 'No comparison available',
            abs($percentageChange) < 1 => 'Stable',
            $percentageChange > 0 => 'Increased',
            default => 'Decreased',
        };

        return compact('current', 'previous', 'difference', 'percentageChange', 'label', 'periodLabel');
    }

    /**
     * Local/Foreign and Male/Female totals from the same verified rows —
     * only existing party_* columns, no new visitor categories.
     *
     * @param  array<string, mixed>  $filters
     * @return array{local: int, foreign: int, male: int, female: int}
     */
    public static function classificationBreakdown(array $filters): array
    {
        $row = self::verifiedReportsQuery($filters)
            ->selectRaw('SUM(party_local) as local_total, SUM(party_foreign) as foreign_total, SUM(party_male) as male_total, SUM(party_female) as female_total')
            ->first();

        return [
            'local' => (int) ($row->local_total ?? 0),
            'foreign' => (int) ($row->foreign_total ?? 0),
            'male' => (int) ($row->male_total ?? 0),
            'female' => (int) ($row->female_total ?? 0),
        ];
    }

    /**
     * Real reporting events (create/validate/consolidate/approve/return),
     * replacing PtoMockData::recentActivity()'s fabricated feed.
     *
     * @return Collection<int, array{icon: string, title: string, description: string, time: CarbonImmutable}>
     */
    public static function recentActivity(?int $municipalityId, int $limit = 6): Collection
    {
        return OperationLog::query()
            ->whereIn('entity_type', ['monthly_arrival_report', 'municipal_report'])
            ->whereIn('action', ['create', 'validate', 'consolidate', 'approve', 'return'])
            ->when($municipalityId, fn (Builder $q, int $id) => $q->where('municipality_id', $id))
            ->with('user')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (OperationLog $log) => self::describeActivity($log));
    }

    private static function describeActivity(OperationLog $log): array
    {
        $entity = $log->entity_type === 'municipal_report' ? 'LGU consolidated report' : 'establishment report';

        [$icon, $verb] = match ($log->action) {
            'create' => ['ti-plus', 'encoded/submitted'],
            'validate' => ['ti-check', 'verified'],
            'consolidate' => ['ti-report', 'consolidated and submitted to PTO'],
            'approve' => ['ti-circle-check', 'validated'],
            'return' => ['ti-arrow-back-up', 'returned for clarification'],
            default => ['ti-info-circle', $log->action],
        };

        return [
            'icon' => $icon,
            'title' => ucfirst($verb).' an '.$entity,
            'description' => $log->user->name ?? 'Unknown user',
            'time' => $log->created_at,
        ];
    }

    private static function arrivalColumn(array $filters): string
    {
        return match ($filters['classification'] ?? null) {
            'local' => 'party_local',
            'foreign' => 'party_foreign',
            default => 'total_visitors',
        };
    }

    private static function verifiedReportsQuery(array $filters): Builder
    {
        return MonthlyArrivalReport::query()
            ->where('status', MonthlyReportStatus::Verified)
            ->whereYear('period_month', $filters['year'])
            ->when($filters['month'] ?? null, fn (Builder $q, int $m) => $q->whereMonth('period_month', $m))
            ->when($filters['municipalityId'] ?? null, fn (Builder $q, int $id) => $q->where('municipality_id', $id))
            ->when($filters['listingId'] ?? null, fn (Builder $q, int $id) => $q->where('listing_id', $id));
    }
}
