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
            ->whereDoesntHave('supersededBy')
            ->get(['id', 'municipality_id', 'status']);

        $reportingMunicipalities = $municipalReportRows->where('status', '!=', MunicipalReport::STATUS_RETURNED)->pluck('municipality_id')->unique()->count();
        $forClarificationMunicipalities = $municipalReportRows->where('status', MunicipalReport::STATUS_RETURNED)->pluck('municipality_id')->unique()->count();
        $notSubmittedMunicipalities = max(0, $totalMunicipalities - $reportingMunicipalities - $forClarificationMunicipalities);

        $forReviewCount = (int) MonthlyArrivalReport::query()
            ->whereIn('status', MonthlyReportStatus::awaitingReview())
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
            ['label' => 'Local Visitors', 'value' => number_format($domestic), 'delta' => null, 'tone' => 'neutral'],
            ['label' => 'Foreign Visitors', 'value' => number_format($foreign), 'delta' => null, 'tone' => 'neutral'],
            ['label' => 'Reporting LGUs', 'value' => "{$reportingMunicipalities}/{$totalMunicipalities}", 'delta' => null, 'tone' => $reportingMunicipalities === $totalMunicipalities ? 'success' : 'warning'],
            ['label' => 'Verified Reports', 'value' => number_format($verifiedReportsCount), 'delta' => null, 'tone' => 'success'],
            [
                'label' => 'Reports Requiring Attention',
                'value' => number_format($attentionCount),
                'delta' => $attentionCount ? "{$forReviewCount} For Review · {$forClarificationMunicipalities} For Clarification · {$notSubmittedMunicipalities} Not Submitted" : null,
                'tone' => $attentionCount ? 'warning' : 'success',
                'href' => route('pto.municipalReports.index', [
                    'status' => 'attention',
                    'year' => $filters['year'],
                    'month' => $filters['month'],
                ]),
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
            ->whereDoesntHave('supersededBy')
            ->get()
            ->keyBy('municipality_id');

        return $municipalities->map(function (Municipality $municipality) use ($reports) {
            $report = $reports->get($municipality->id);

            return [
                'municipality' => $municipality,
                'report' => $report,
                'status' => match ($report?->status) {
                    'SUBMITTED', 'REVIEWED' => 'For Review',
                    'APPROVED' => 'Verified',
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
            ->whereIn('action', ['create', 'validate', 'consolidate', 'approve', 'return', 'reopen'])
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
            'approve' => ['ti-circle-check', 'verified'],
            'return' => ['ti-arrow-back-up', 'returned for clarification'],
            'reopen' => ['ti-lock-open', 'reopened a verified report with a new submission'],
            default => ['ti-info-circle', $log->action],
        };

        return [
            'icon' => $icon,
            'title' => ucfirst($verb).' an '.$entity,
            'description' => $log->user->name ?? 'Unknown user',
            'time' => $log->created_at,
        ];
    }

    /**
     * One entry per calendar month of $filters['year'] for the given scope
     * (municipalityId and/or listingId), from Verified reports only. A month
     * with no Verified report has hasData = false and is shown as "No
     * report" — never as a zero-arrival month. Each month also carries its
     * change vs the previous calendar month (December of the previous year
     * for January) when both months have verified data.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{month: int, label: string, shortLabel: string, hasData: bool, total: int, reportCount: int, change: string, changePercent: ?float}>
     */
    public static function monthlyRecords(array $filters): Collection
    {
        $intYear = (int) $filters['year'];
        $dtStart = CarbonImmutable::create($intYear - 1, 12, 1);
        $dtEnd = CarbonImmutable::create($intYear, 12, 31);

        $objRows = self::_verifiedScopeQuery($filters)
            ->whereDate('period_month', '>=', $dtStart->toDateString())
            ->whereDate('period_month', '<=', $dtEnd->toDateString())
            ->get(['period_month', 'total_visitors']);

        $arrTotals = $objRows
            ->groupBy(fn (MonthlyArrivalReport $objRow) => $objRow->period_month->format('Y-m'))
            ->map(fn (Collection $objMonthRows) => ['total' => (int) $objMonthRows->sum('total_visitors'), 'count' => $objMonthRows->count()])
            ->all();

        return self::_buildMonthlyRecords($intYear, $arrTotals);
    } // end monthlyRecords

    /**
     * Province-wide equivalent of monthlyRecords() for PTO Provincial
     * Reports: one entry per month of $intYear from municipal reports the
     * PTO has verified (APPROVED, latest revision) only — the official
     * provincial figures. A month with no PTO-verified municipal report has
     * hasData = false. reportCount = number of verified LGU reports.
     *
     * @return Collection<int, array{month: int, label: string, shortLabel: string, hasData: bool, total: int, reportCount: int, change: string, changePercent: ?float}>
     */
    public static function provincialMonthlyRecords(int $intYear): Collection
    {
        $arrTotals = self::_approvedMunicipalReportsQuery()
            ->whereDate('period_start', '>=', CarbonImmutable::create($intYear - 1, 12, 1)->toDateString())
            ->whereDate('period_start', '<=', CarbonImmutable::create($intYear, 12, 31)->toDateString())
            ->get(['id', 'period_start', 'total_arrivals'])
            ->toBase()
            ->groupBy(fn (MunicipalReport $objReport) => $objReport->period_start->format('Y-m'))
            ->map(fn (Collection $objMonthReports) => ['total' => (int) $objMonthReports->sum('total_arrivals'), 'count' => $objMonthReports->count()])
            ->all();

        return self::_buildMonthlyRecords($intYear, $arrTotals);
    } // end provincialMonthlyRecords

    /**
     * Visitor classifications behind the PTO-verified municipal reports of
     * a year (or one month of it): the establishment reports each verified
     * municipal report was consolidated from.
     *
     * @return array<string, int>
     */
    public static function provincialVisitorBreakdown(int $intYear, ?int $intMonth = null): array
    {
        $objApprovedIds = self::_approvedMunicipalReportsQuery()
            ->whereYear('period_start', $intYear)
            ->when($intMonth, fn (Builder $q, int $m) => $q->whereMonth('period_start', $m))
            ->pluck('id');

        $objRow = MonthlyArrivalReport::query()
            ->whereIn('municipal_report_id', $objApprovedIds)
            ->selectRaw('
                COALESCE(SUM(party_male), 0) as male_total, COALESCE(SUM(party_female), 0) as female_total,
                COALESCE(SUM(party_adults), 0) as adults_total, COALESCE(SUM(party_children), 0) as children_total,
                COALESCE(SUM(party_seniors), 0) as seniors_total, COALESCE(SUM(party_local), 0) as local_total,
                COALESCE(SUM(party_foreign), 0) as foreign_total, COALESCE(SUM(total_visitors), 0) as visitors_total
            ')
            ->first();

        return [
            'male' => (int) $objRow->male_total,
            'female' => (int) $objRow->female_total,
            'adults' => (int) $objRow->adults_total,
            'children' => (int) $objRow->children_total,
            'seniors' => (int) $objRow->seniors_total,
            'local' => (int) $objRow->local_total,
            'foreign' => (int) $objRow->foreign_total,
            'total' => (int) $objRow->visitors_total,
        ];
    } // end provincialVisitorBreakdown

    /**
     * PTO-verified (APPROVED), latest-revision municipal reports.
     */
    private static function _approvedMunicipalReportsQuery(): Builder
    {
        return MunicipalReport::query()
            ->where('status', MunicipalReport::STATUS_APPROVED)
            ->whereNotNull('municipality_id')
            ->whereDoesntHave('supersededBy');
    } // end _approvedMunicipalReportsQuery

    /**
     * Shared month-record builder: 12 entries for $intYear from
     * ['Y-m' => ['total' => int, 'count' => int]] (December of the previous
     * year may be included, only to compare January against it).
     *
     * @param  array<string, array{total: int, count: int}>  $arrTotals
     * @return Collection<int, array<string, mixed>>
     */
    private static function _buildMonthlyRecords(int $intYear, array $arrTotals): Collection
    {
        $strPreviousKey = CarbonImmutable::create($intYear - 1, 12, 1)->format('Y-m');
        $intPreviousTotal = isset($arrTotals[$strPreviousKey]) ? $arrTotals[$strPreviousKey]['total'] : null;
        $arrRecords = [];

        foreach (range(1, 12) as $intMonth) {
            $dtMonth = CarbonImmutable::create($intYear, $intMonth, 1);
            $arrMonth = $arrTotals[$dtMonth->format('Y-m')] ?? null;
            $blnHasData = $arrMonth !== null;
            $intTotal = $blnHasData ? $arrMonth['total'] : 0;

            [$strChange, $fltPercent] = $blnHasData
                ? self::changeLabel($intPreviousTotal, $intTotal)
                : ['No Comparison Available', null];

            $arrRecords[] = [
                'month' => $intMonth,
                'label' => $dtMonth->format('F Y'),
                'shortLabel' => $dtMonth->format('M'),
                'hasData' => $blnHasData,
                'total' => $intTotal,
                'reportCount' => $blnHasData ? $arrMonth['count'] : 0,
                'change' => $strChange,
                'changePercent' => $fltPercent,
            ];

            $intPreviousTotal = $blnHasData ? $intTotal : null;
        } // end foreach month

        return collect($arrRecords);
    } // end _buildMonthlyRecords

    /**
     * Year totals from monthlyRecords(): overall total, highest / lowest
     * month and average per reported month — computed only over months
     * that actually have verified data.
     *
     * @param  Collection<int, array<string, mixed>>  $objRecords
     * @return array{total: int, monthsWithData: int, highest: ?array<string, mixed>, lowest: ?array<string, mixed>, average: ?int}
     */
    public static function yearSummary(Collection $objRecords): array
    {
        $objWithData = $objRecords->where('hasData', true);
        $intMonthsWithData = $objWithData->count();

        return [
            'total' => (int) $objWithData->sum('total'),
            'monthsWithData' => $intMonthsWithData,
            'highest' => $intMonthsWithData > 0 ? $objWithData->sortByDesc('total')->first() : null,
            'lowest' => $intMonthsWithData > 0 ? $objWithData->sortBy('total')->first() : null,
            'average' => $intMonthsWithData > 0 ? (int) round($objWithData->sum('total') / $intMonthsWithData) : null,
        ];
    } // end yearSummary

    /**
     * Q1–Q4 totals derived from monthlyRecords(), with how many of the
     * quarter's three months have verified data (a quarter with fewer is
     * marked incomplete, not padded with zeros).
     *
     * @param  Collection<int, array<string, mixed>>  $objRecords
     * @return Collection<int, array{quarter: string, months: string, total: int, monthsWithData: int}>
     */
    public static function quarterlySummary(Collection $objRecords): Collection
    {
        $arrMonthNames = ['Jan – Mar', 'Apr – Jun', 'Jul – Sep', 'Oct – Dec'];

        return collect(range(1, 4))->map(function (int $intQuarter) use ($objRecords, $arrMonthNames) {
            $objQuarterMonths = $objRecords->whereBetween('month', [($intQuarter - 1) * 3 + 1, $intQuarter * 3])->where('hasData', true);

            return [
                'quarter' => "Q{$intQuarter}",
                'months' => $arrMonthNames[$intQuarter - 1],
                'total' => (int) $objQuarterMonths->sum('total'),
                'monthsWithData' => $objQuarterMonths->count(),
            ];
        });
    } // end quarterlySummary

    /**
     * Same-period year comparison: the selected year's reported months vs
     * the same calendar months of the previous year, so a year still in
     * progress is never compared against a full previous year. "No
     * Comparison Available" when the previous year has no verified data
     * for those months.
     *
     * @param  Collection<int, array<string, mixed>>  $objCurrent  monthlyRecords() of the selected year.
     * @param  Collection<int, array<string, mixed>>  $objPrevious  monthlyRecords() of the year before.
     * @return array{label: string, percent: ?float, current: int, previous: ?int, periodLabel: ?string}
     */
    public static function yearComparison(Collection $objCurrent, Collection $objPrevious, int $intYear): array
    {
        $objCurrentMonths = $objCurrent->where('hasData', true);

        if ($objCurrentMonths->isEmpty()) {
            return ['label' => 'No Comparison Available', 'percent' => null, 'current' => 0, 'previous' => null, 'periodLabel' => null];
        }

        $arrMonths = $objCurrentMonths->pluck('month')->all();
        $objPreviousMonths = $objPrevious->where('hasData', true)->whereIn('month', $arrMonths);
        $intCurrent = (int) $objCurrentMonths->sum('total');

        $strRange = count($arrMonths) === 12
            ? 'full year'
            : CarbonImmutable::create($intYear, min($arrMonths), 1)->format('M').' – '.CarbonImmutable::create($intYear, max($arrMonths), 1)->format('M');

        if ($objPreviousMonths->isEmpty()) {
            return ['label' => 'No Comparison Available', 'percent' => null, 'current' => $intCurrent, 'previous' => null, 'periodLabel' => $strRange];
        }

        $intPrevious = (int) $objPreviousMonths->sum('total');
        [$strLabel, $fltPercent] = self::changeLabel($intPrevious, $intCurrent);

        return ['label' => $strLabel, 'percent' => $fltPercent, 'current' => $intCurrent, 'previous' => $intPrevious, 'periodLabel' => $strRange];
    } // end yearComparison

    /**
     * Increased / Decreased / Stable (within 1%, the dashboard's rule) /
     * No Comparison Available, plus the percentage change when defined.
     *
     * @return array{0: string, 1: ?float}
     */
    public static function changeLabel(?int $intPrevious, int $intCurrent): array
    {
        if ($intPrevious === null) {
            return ['No Comparison Available', null];
        }

        if ($intPrevious === 0) {
            return [$intCurrent === 0 ? 'Stable' : 'Increased', null];
        }

        $fltPercent = round((($intCurrent - $intPrevious) / $intPrevious) * 100, 1);

        $strLabel = match (true) {
            abs($fltPercent) < 1 => 'Stable',
            $fltPercent > 0 => 'Increased',
            default => 'Decreased',
        };

        return [$strLabel, $fltPercent];
    } // end changeLabel

    /**
     * Verified totals for every visitor classification the system collects
     * (gender, age group, origin) — no new categories.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public static function visitorBreakdown(array $filters): array
    {
        $objRow = self::verifiedReportsQuery($filters)
            ->selectRaw('
                COALESCE(SUM(party_male), 0) as male_total, COALESCE(SUM(party_female), 0) as female_total,
                COALESCE(SUM(party_adults), 0) as adults_total, COALESCE(SUM(party_children), 0) as children_total,
                COALESCE(SUM(party_seniors), 0) as seniors_total, COALESCE(SUM(party_local), 0) as local_total,
                COALESCE(SUM(party_foreign), 0) as foreign_total, COALESCE(SUM(total_visitors), 0) as visitors_total
            ')
            ->first();

        return [
            'male' => (int) $objRow->male_total,
            'female' => (int) $objRow->female_total,
            'adults' => (int) $objRow->adults_total,
            'children' => (int) $objRow->children_total,
            'seniors' => (int) $objRow->seniors_total,
            'local' => (int) $objRow->local_total,
            'foreign' => (int) $objRow->foreign_total,
            'total' => (int) $objRow->visitors_total,
        ];
    } // end visitorBreakdown

    /**
     * Verified arrivals per establishment (highest first), with how many
     * verified monthly reports each contributed.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{name: string, category: string, total: int, reportCount: int}>
     */
    public static function establishmentBreakdown(array $filters): Collection
    {
        return self::verifiedReportsQuery($filters)
            ->with('listing.categoryRecord')
            ->get(['listing_id', 'total_visitors'])
            ->groupBy('listing_id')
            ->map(fn (Collection $objReports) => [
                'name' => $objReports->first()->listing?->name ?? 'Unknown establishment',
                'category' => $objReports->first()->listing?->categoryRecord?->cat_name ?? 'Uncategorized',
                'total' => (int) $objReports->sum('total_visitors'),
                'reportCount' => $objReports->count(),
            ])
            ->sortByDesc('total')
            ->values();
    } // end establishmentBreakdown

    /**
     * Verified arrivals grouped by establishment category (the stand-in for
     * "by destination" — destinations do not report arrivals).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{category: string, total: int, establishments: int}>
     */
    public static function categoryBreakdown(array $filters): Collection
    {
        return self::establishmentBreakdown($filters)
            ->groupBy('category')
            ->map(fn (Collection $objGroup, string $strCategory) => [
                'category' => $strCategory,
                'total' => (int) $objGroup->sum('total'),
                'establishments' => $objGroup->count(),
            ])
            ->sortByDesc('total')
            ->values();
    } // end categoryBreakdown

    /**
     * Years that have any report in the given scope, newest first, always
     * including the current year.
     *
     * @return array<int, int>
     */
    public static function scopedYearOptions(?int $intMunicipalityId, ?int $intListingId = null): array
    {
        return MonthlyArrivalReport::query()
            ->when($intMunicipalityId, fn (Builder $q, int $id) => $q->where('municipality_id', $id))
            ->when($intListingId, fn (Builder $q, int $id) => $q->where('listing_id', $id))
            ->get(['period_month'])
            // toBase(): with no rows, map() would keep an Eloquent
            // collection, and pushing a plain year into it breaks unique().
            ->toBase()
            ->map(fn (MonthlyArrivalReport $objRow) => $objRow->period_month->year)
            ->push(CarbonImmutable::now()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    } // end scopedYearOptions

    /**
     * Verified reports for a municipality/establishment scope, with no
     * year/month constraint (callers add their own date range).
     *
     * @param  array<string, mixed>  $filters
     */
    private static function _verifiedScopeQuery(array $filters): Builder
    {
        return MonthlyArrivalReport::query()
            ->where('status', MonthlyReportStatus::Verified)
            ->when($filters['municipalityId'] ?? null, fn (Builder $q, int $id) => $q->where('municipality_id', $id))
            ->when($filters['listingId'] ?? null, fn (Builder $q, int $id) => $q->where('listing_id', $id));
    } // end _verifiedScopeQuery

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
