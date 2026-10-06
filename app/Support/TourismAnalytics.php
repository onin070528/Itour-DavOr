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
            ->where('mar_status', MonthlyReportStatus::ForReview)
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
