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
    public static function resolveFilters(Request $objRequest): array
    {
        $year = (int) ($objRequest->query('year') ?: CarbonImmutable::now()->year);

        $month = $objRequest->query('month');
        $month = ($month !== null && $month !== '') ? (int) $month : null;

        $municipalityId = $objRequest->query('municipality_id');
        $municipalityId = ($municipalityId !== null && $municipalityId !== '') ? (int) $municipalityId : null;

        $listingId = $objRequest->query('listing_id');
        $listingId = ($listingId !== null && $listingId !== '') ? (int) $listingId : null;

        $classification = $objRequest->query('classification');
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
        $arrYears = MonthlyArrivalReport::query()
            ->get(['mar_period_month'])
            ->map(fn (MonthlyArrivalReport $objRow) => $objRow->mar_period_month->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $arrYears ?: [CarbonImmutable::now()->year];
    }

    /**
     * @param  array<string, mixed>  $arrFilters
     * @return array<int, array{label: string, value: string, delta: ?string, tone: string}>
     */
    public static function kpis(array $arrFilters, array $arrComparison): array
    {
        $strColumn = self::arrivalColumn($arrFilters);

        $intTotalArrivals = (int) self::verifiedReportsQuery($arrFilters)->sum($strColumn);
        $intDomestic = (int) self::verifiedReportsQuery($arrFilters)->sum('mar_party_local');
        $intForeign = (int) self::verifiedReportsQuery($arrFilters)->sum('mar_party_foreign');
        $intVerifiedReportsCount = (int) self::verifiedReportsQuery($arrFilters)->count();

        $intTotalMunicipalities = $arrFilters['municipalityId'] ? 1 : Municipality::query()->count();

        $objMunicipalReportRows = MunicipalReport::query()
            ->whereYear('mrp_period_start', $arrFilters['year'])
            ->when($arrFilters['month'] ?? null, fn (Builder $objQuery, int $m) => $objQuery->whereMonth('mrp_period_start', $m))
            ->when($arrFilters['municipalityId'] ?? null, fn (Builder $objQuery, int $intId) => $objQuery->where('mun_id', $intId))
            ->whereNotNull('mun_id')
            ->whereDoesntHave('supersededBy')
            ->get(['mrp_id', 'mun_id', 'mrp_status']);

        $intReportingMunicipalities = $objMunicipalReportRows->where('mrp_status', '!=', MunicipalReport::STATUS_RETURNED)->pluck('mun_id')->unique()->count();
        $intForClarificationMunicipalities = $objMunicipalReportRows->where('mrp_status', MunicipalReport::STATUS_RETURNED)->pluck('mun_id')->unique()->count();
        $notSubmittedMunicipalities = max(0, $intTotalMunicipalities - $intReportingMunicipalities - $intForClarificationMunicipalities);

        $intForReviewCount = (int) MonthlyArrivalReport::query()
            ->whereIn('mar_status', MonthlyReportStatus::awaitingReview())
            ->whereYear('mar_period_month', $arrFilters['year'])
            ->when($arrFilters['month'] ?? null, fn (Builder $objQuery, int $m) => $objQuery->whereMonth('mar_period_month', $m))
            ->when($arrFilters['municipalityId'] ?? null, fn (Builder $objQuery, int $intId) => $objQuery->where('mun_id', $intId))
            ->when($arrFilters['listingId'] ?? null, fn (Builder $objQuery, int $intId) => $objQuery->where('lst_id', $intId))
            ->count();

        $intAttentionCount = $intForReviewCount + $intForClarificationMunicipalities + $notSubmittedMunicipalities;

        $strArrivalsDelta = match ($arrComparison['label']) {
            'Increased' => '+'.number_format(abs($arrComparison['percentageChange']), 1).'% '.$arrComparison['periodLabel'],
            'Decreased' => '-'.number_format(abs($arrComparison['percentageChange']), 1).'% '.$arrComparison['periodLabel'],
            'Stable' => 'Stable '.$arrComparison['periodLabel'],
            default => 'No comparison available',
        };
        $strArrivalsTone = match ($arrComparison['label']) {
            'Increased' => 'success',
            'Decreased' => 'danger',
            default => 'neutral',
        };

        return [
            ['label' => 'Total Tourist Arrivals', 'value' => number_format($intTotalArrivals), 'delta' => $strArrivalsDelta, 'tone' => $strArrivalsTone],
            ['label' => 'Local Visitors', 'value' => number_format($intDomestic), 'delta' => null, 'tone' => 'neutral'],
            ['label' => 'Foreign Visitors', 'value' => number_format($intForeign), 'delta' => null, 'tone' => 'neutral'],
            ['label' => 'Reporting LGUs', 'value' => "{$intReportingMunicipalities}/{$intTotalMunicipalities}", 'delta' => null, 'tone' => $intReportingMunicipalities === $intTotalMunicipalities ? 'success' : 'warning'],
            ['label' => 'Verified Reports', 'value' => number_format($intVerifiedReportsCount), 'delta' => null, 'tone' => 'success'],
            [
                'label' => 'Reports Requiring Attention',
                'value' => number_format($intAttentionCount),
                'delta' => $intAttentionCount ? "{$intForReviewCount} For Review · {$intForClarificationMunicipalities} For Clarification · {$notSubmittedMunicipalities} Not Submitted" : null,
                'tone' => $intAttentionCount ? 'warning' : 'success',
                'href' => route('pto.municipalReports.index', [
                    'status' => 'attention',
                    'year' => $arrFilters['year'],
                    'month' => $arrFilters['month'],
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
     * @param  array<string, mixed>  $arrFilters
     * @return array{month: array<int, array{label: string, value: int}>, year: array<int, array{label: string, value: int}>}
     */
    public static function arrivalTrend(array $arrFilters): array
    {
        $strColumn = self::arrivalColumn($arrFilters);

        $objYearRows = MonthlyArrivalReport::query()
            ->where('mar_status', MonthlyReportStatus::Verified)
            ->whereYear('mar_period_month', $arrFilters['year'])
            ->when($arrFilters['municipalityId'] ?? null, fn (Builder $objQuery, int $intId) => $objQuery->where('mun_id', $intId))
            ->when($arrFilters['listingId'] ?? null, fn (Builder $objQuery, int $intId) => $objQuery->where('lst_id', $intId))
            ->get(['mar_period_month', $strColumn]);

        $objByMonth = $objYearRows->groupBy(fn (MonthlyArrivalReport $objRow) => $objRow->mar_period_month->month)
            ->map(fn (Collection $objRows) => (int) $objRows->sum($strColumn));

        $arrMonthSeries = collect(range(1, 12))->map(fn (int $m) => [
            'label' => CarbonImmutable::create($arrFilters['year'], $m, 1)->format('M'),
            'value' => (int) ($objByMonth[$m] ?? 0),
        ])->all();

        $objAllRows = MonthlyArrivalReport::query()
            ->where('mar_status', MonthlyReportStatus::Verified)
            ->when($arrFilters['municipalityId'] ?? null, fn (Builder $objQuery, int $intId) => $objQuery->where('mun_id', $intId))
            ->when($arrFilters['listingId'] ?? null, fn (Builder $objQuery, int $intId) => $objQuery->where('lst_id', $intId))
            ->get(['mar_period_month', $strColumn]);

        $arrYearSeries = $objAllRows->groupBy(fn (MonthlyArrivalReport $objRow) => $objRow->mar_period_month->year)
            ->sortKeys()
            ->map(fn (Collection $objRows, int $intYear) => ['label' => (string) $intYear, 'value' => (int) $objRows->sum($strColumn)])
            ->values()
            ->all();

        return ['month' => $arrMonthSeries, 'year' => $arrYearSeries];
    }

    /**
     * Verified arrivals grouped by municipality, each row carrying a
     * percentage-of-max for a plain CSS-width bar — no chart library.
     *
     * @param  array<string, mixed>  $arrFilters
     * @return Collection<int, array{municipality: Municipality, total: int, percentage: int}>
     */
    public static function municipalityComparison(array $arrFilters): Collection
    {
        $strColumn = self::arrivalColumn($arrFilters);

        $objRows = self::verifiedReportsQuery($arrFilters)->get(['mun_id', $strColumn]);

        $objTotals = $objRows->groupBy('mun_id')
            ->map(fn (Collection $objGroup) => (int) $objGroup->sum($strColumn));

        $objMunicipalities = Municipality::query()->whereIn('mun_id', $objTotals->keys())->get()->keyBy('mun_id');
        $intMax = $objTotals->max() ?: 1;

        return $objTotals->map(fn (int $intTotal, int $intMunicipalityId) => [
            'municipality' => $objMunicipalities->get($intMunicipalityId),
            'total' => $intTotal,
            'percentage' => (int) round(($intTotal / $intMax) * 100),
        ])->filter(fn (array $arrRow) => $arrRow['municipality'] !== null)
            ->sortByDesc('total')
            ->values();
    }

    /**
     * One row per municipality for the selected period — "Not Submitted"
     * is a literal label, never a stored or displayed zero.
     *
     * @param  array<string, mixed>  $arrFilters
     * @return Collection<int, array{municipality: Municipality, report: ?MunicipalReport, status: string}>
     */
    public static function reportingStatus(array $arrFilters): Collection
    {
        $objMunicipalities = Municipality::query()
            ->when($arrFilters['municipalityId'] ?? null, fn (Builder $objQuery, int $intId) => $objQuery->where('mun_id', $intId))
            ->orderBy('mun_name')
            ->get();

        $objReports = MunicipalReport::query()
            ->whereYear('mrp_period_start', $arrFilters['year'])
            ->when($arrFilters['month'] ?? null, fn (Builder $objQuery, int $m) => $objQuery->whereMonth('mrp_period_start', $m))
            ->whereNotNull('mun_id')
            ->whereDoesntHave('supersededBy')
            ->get()
            ->keyBy('mun_id');

        return $objMunicipalities->map(function (Municipality $objMunicipality) use ($objReports) {
            $objReport = $objReports->get($objMunicipality->mun_id);

            return [
                'municipality' => $objMunicipality,
                'report' => $objReport,
                'status' => match ($objReport?->mrp_status) {
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
     * @param  array<string, mixed>  $arrFilters
     * @return array{current: int, previous: ?int, difference: ?int, percentageChange: ?float, label: string, periodLabel: string}
     */
    public static function periodComparison(array $arrFilters): array
    {
        $strColumn = self::arrivalColumn($arrFilters);
        $current = (int) self::verifiedReportsQuery($arrFilters)->sum($strColumn);

        if ($arrFilters['month'] ?? null) {
            $dtmPreviousMonth = CarbonImmutable::create($arrFilters['year'], $arrFilters['month'], 1)->subMonthNoOverflow();
            $arrPreviousFilters = array_merge($arrFilters, ['year' => $dtmPreviousMonth->year, 'month' => $dtmPreviousMonth->month]);
            $periodLabel = 'vs last month';
        } else {
            $arrPreviousFilters = array_merge($arrFilters, ['year' => $arrFilters['year'] - 1, 'month' => null]);
            $periodLabel = 'vs last year';
        }

        $blnPreviousExists = self::verifiedReportsQuery($arrPreviousFilters)->exists();

        if (! $blnPreviousExists) {
            return [
                'current' => $current, 'previous' => null, 'difference' => null,
                'percentageChange' => null, 'label' => 'No comparison available', 'periodLabel' => $periodLabel,
            ];
        }

        $previous = (int) self::verifiedReportsQuery($arrPreviousFilters)->sum($strColumn);
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
     * @param  array<string, mixed>  $arrFilters
     * @return array{local: int, foreign: int, male: int, female: int}
     */
    public static function classificationBreakdown(array $arrFilters): array
    {
        $objRow = self::verifiedReportsQuery($arrFilters)
            ->selectRaw('SUM(mar_party_local) as local_total, SUM(mar_party_foreign) as foreign_total, SUM(mar_party_male) as male_total, SUM(mar_party_female) as female_total')
            ->first();

        return [
            'local' => (int) ($objRow->local_total ?? 0),
            'foreign' => (int) ($objRow->foreign_total ?? 0),
            'male' => (int) ($objRow->male_total ?? 0),
            'female' => (int) ($objRow->female_total ?? 0),
        ];
    }

    /**
     * Real reporting events (create/validate/consolidate/approve/return),
     * replacing PtoMockData::recentActivity()'s fabricated feed.
     *
     * @return Collection<int, array{icon: string, title: string, description: string, time: CarbonImmutable}>
     */
    public static function recentActivity(?int $intMunicipalityId, int $intLimit = 6): Collection
    {
        return OperationLog::query()
            ->whereIn('opl_entity_type', ['monthly_arrival_report', 'municipal_report'])
            ->whereIn('opl_action', ['create', 'validate', 'consolidate', 'approve', 'return', 'reopen'])
            ->when($intMunicipalityId, fn (Builder $objQuery, int $intId) => $objQuery->where('mun_id', $intId))
            ->with('user')
            ->orderByDesc('opl_created_at')
            ->limit($intLimit)
            ->get()
            ->map(fn (OperationLog $objLog) => self::describeActivity($objLog));
    }

    private static function describeActivity(OperationLog $objLog): array
    {
        $strEntity = $objLog->opl_entity_type === 'municipal_report' ? 'LGU consolidated report' : 'establishment report';

        [$strIcon, $strVerb] = match ($objLog->opl_action) {
            'create' => ['ti-plus', 'encoded/submitted'],
            'validate' => ['ti-check', 'verified'],
            'consolidate' => ['ti-report', 'consolidated and submitted to PTO'],
            'approve' => ['ti-circle-check', 'verified'],
            'return' => ['ti-arrow-back-up', 'returned for clarification'],
            'reopen' => ['ti-lock-open', 'reopened a verified report with a new submission'],
            default => ['ti-info-circle', $objLog->opl_action],
        };

        return [
            'icon' => $strIcon,
            'title' => ucfirst($strVerb).' an '.$strEntity,
            'description' => $objLog->user->usr_name ?? 'Unknown user',
            'time' => $objLog->opl_created_at,
        ];
    }

    /**
     * One entry per calendar month of $arrFilters['year'] for the given
     * scope (municipalityId and/or listingId), from Verified reports only. A
     * month with no Verified report has hasData = false and is shown as "No
     * report" — never as a zero-arrival month. Each month also carries its
     * change vs the previous calendar month (December of the previous year
     * for January) when both months have verified data.
     *
     * @param  array<string, mixed>  $arrFilters
     * @return Collection<int, array{month: int, label: string, shortLabel: string, hasData: bool, total: int, reportCount: int, change: string, changePercent: ?float}>
     */
    public static function monthlyRecords(array $arrFilters): Collection
    {
        $intYear = (int) $arrFilters['year'];
        $dtStart = CarbonImmutable::create($intYear - 1, 12, 1);
        $dtEnd = CarbonImmutable::create($intYear, 12, 31);

        $objRows = self::_verifiedScopeQuery($arrFilters)
            ->whereDate('mar_period_month', '>=', $dtStart->toDateString())
            ->whereDate('mar_period_month', '<=', $dtEnd->toDateString())
            ->get(['mar_period_month', 'mar_total_visitors']);

        $arrTotals = $objRows
            ->groupBy(fn (MonthlyArrivalReport $objRow) => $objRow->mar_period_month->format('Y-m'))
            ->map(fn (Collection $objMonthRows) => ['total' => (int) $objMonthRows->sum('mar_total_visitors'), 'count' => $objMonthRows->count()])
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
            ->whereDate('mrp_period_start', '>=', CarbonImmutable::create($intYear - 1, 12, 1)->toDateString())
            ->whereDate('mrp_period_start', '<=', CarbonImmutable::create($intYear, 12, 31)->toDateString())
            ->get(['mrp_id', 'mrp_period_start', 'mrp_total_arrivals'])
            ->toBase()
            ->groupBy(fn (MunicipalReport $objReport) => $objReport->mrp_period_start->format('Y-m'))
            ->map(fn (Collection $objMonthReports) => ['total' => (int) $objMonthReports->sum('mrp_total_arrivals'), 'count' => $objMonthReports->count()])
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
            ->whereYear('mrp_period_start', $intYear)
            ->when($intMonth, fn (Builder $objQuery, int $m) => $objQuery->whereMonth('mrp_period_start', $m))
            ->pluck('mrp_id');

        $objRow = MonthlyArrivalReport::query()
            ->whereIn('mrp_id', $objApprovedIds)
            ->selectRaw('
                COALESCE(SUM(mar_party_male), 0) as male_total, COALESCE(SUM(mar_party_female), 0) as female_total,
                COALESCE(SUM(mar_party_adults), 0) as adults_total, COALESCE(SUM(mar_party_children), 0) as children_total,
                COALESCE(SUM(mar_party_seniors), 0) as seniors_total, COALESCE(SUM(mar_party_local), 0) as local_total,
                COALESCE(SUM(mar_party_foreign), 0) as foreign_total, COALESCE(SUM(mar_total_visitors), 0) as visitors_total
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
            ->where('mrp_status', MunicipalReport::STATUS_APPROVED)
            ->whereNotNull('mun_id')
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
     * @param  array<string, mixed>  $arrFilters
     * @return array<string, int>
     */
    public static function visitorBreakdown(array $arrFilters): array
    {
        $objRow = self::verifiedReportsQuery($arrFilters)
            ->selectRaw('
                COALESCE(SUM(mar_party_male), 0) as male_total, COALESCE(SUM(mar_party_female), 0) as female_total,
                COALESCE(SUM(mar_party_adults), 0) as adults_total, COALESCE(SUM(mar_party_children), 0) as children_total,
                COALESCE(SUM(mar_party_seniors), 0) as seniors_total, COALESCE(SUM(mar_party_local), 0) as local_total,
                COALESCE(SUM(mar_party_foreign), 0) as foreign_total, COALESCE(SUM(mar_total_visitors), 0) as visitors_total
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
     * @param  array<string, mixed>  $arrFilters
     * @return Collection<int, array{name: string, category: string, total: int, reportCount: int}>
     */
    public static function establishmentBreakdown(array $arrFilters): Collection
    {
        return self::verifiedReportsQuery($arrFilters)
            ->with('listing.categoryRecord')
            ->get(['lst_id', 'mar_total_visitors'])
            ->groupBy('lst_id')
            ->map(fn (Collection $objReports) => [
                'name' => $objReports->first()->listing?->lst_name ?? 'Unknown establishment',
                'category' => $objReports->first()->listing?->categoryRecord?->cat_name ?? 'Uncategorized',
                'total' => (int) $objReports->sum('mar_total_visitors'),
                'reportCount' => $objReports->count(),
            ])
            ->sortByDesc('total')
            ->values();
    } // end establishmentBreakdown

    /**
     * Verified arrivals grouped by establishment category (the stand-in for
     * "by destination" — destinations do not report arrivals).
     *
     * @param  array<string, mixed>  $arrFilters
     * @return Collection<int, array{category: string, total: int, establishments: int}>
     */
    public static function categoryBreakdown(array $arrFilters): Collection
    {
        return self::establishmentBreakdown($arrFilters)
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
            ->when($intMunicipalityId, fn (Builder $objQuery, int $id) => $objQuery->where('mun_id', $id))
            ->when($intListingId, fn (Builder $objQuery, int $id) => $objQuery->where('lst_id', $id))
            ->get(['mar_period_month'])
            // toBase(): with no rows, map() would keep an Eloquent
            // collection, and pushing a plain year into it breaks unique().
            ->toBase()
            ->map(fn (MonthlyArrivalReport $objRow) => $objRow->mar_period_month->year)
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
     * @param  array<string, mixed>  $arrFilters
     */
    private static function _verifiedScopeQuery(array $arrFilters): Builder
    {
        return MonthlyArrivalReport::query()
            ->where('mar_status', MonthlyReportStatus::Verified)
            ->when($arrFilters['municipalityId'] ?? null, fn (Builder $objQuery, int $id) => $objQuery->where('mun_id', $id))
            ->when($arrFilters['listingId'] ?? null, fn (Builder $objQuery, int $id) => $objQuery->where('lst_id', $id));
    } // end _verifiedScopeQuery

    private static function arrivalColumn(array $arrFilters): string
    {
        return match ($arrFilters['classification'] ?? null) {
            'local' => 'mar_party_local',
            'foreign' => 'mar_party_foreign',
            default => 'mar_total_visitors',
        };
    }

    private static function verifiedReportsQuery(array $arrFilters): Builder
    {
        return MonthlyArrivalReport::query()
            ->where('mar_status', MonthlyReportStatus::Verified)
            ->whereYear('mar_period_month', $arrFilters['year'])
            ->when($arrFilters['month'] ?? null, fn (Builder $objQuery, int $m) => $objQuery->whereMonth('mar_period_month', $m))
            ->when($arrFilters['municipalityId'] ?? null, fn (Builder $objQuery, int $intId) => $objQuery->where('mun_id', $intId))
            ->when($arrFilters['listingId'] ?? null, fn (Builder $objQuery, int $intId) => $objQuery->where('lst_id', $intId));
    }
}
