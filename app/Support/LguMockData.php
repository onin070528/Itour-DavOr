<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Municipality-scoped read model over PtoMockData/TourismCatalog for the LGU dashboard.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Municipality-scoped view over PtoMockData and TourismCatalog.
 *
 * The LGU account is assigned to exactly one municipality, so every method
 * here takes that municipality and returns only the slice of the
 * province-wide mock data that belongs to it — the same underlying data
 * PTO sees, scoped down rather than duplicated.
 */
class LguMockData
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function destinations(string $strMunicipality): array
    {
        return collect(TourismCatalog::listings())
            ->where('category', 'destinations')
            ->where('municipality', $strMunicipality)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function establishments(string $strMunicipality): array
    {
        // `status` now comes straight from the listings table (real
        // publish-workflow state, see App\Services\ListingPublishWorkflow)
        // instead of a hardcoded pending/inactive id list.
        return collect(TourismCatalog::listings())
            ->where('category', '!=', 'destinations')
            ->where('municipality', $strMunicipality)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function arrivals(string $strMunicipality): array
    {
        return collect(PtoMockData::arrivals())
            ->where('municipality', $strMunicipality)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function feedback(string $strMunicipality): array
    {
        $objSubjects = collect(self::destinations($strMunicipality))->pluck('name')
            ->merge(collect(self::establishments($strMunicipality))->pluck('name'));

        return collect(PtoMockData::feedback())
            ->whereIn('subject', $objSubjects)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function destinationPerformance(string $strMunicipality): array
    {
        return collect(PtoMockData::destinationPerformance())
            ->where('municipality', $strMunicipality)
            ->values()
            ->map(fn (array $arrRow, int $intIndex) => [...$arrRow, 'rank' => $intIndex + 1])
            ->all();
    }

    /**
     * Dashboard summary KPI cards, scoped to the municipality.
     *
     * @return array<int, array{label: string, value: string, delta: string, tone: string}>
     */
    public static function dashboardSummary(string $strMunicipality): array
    {
        $objArrivals = collect(self::arrivals($strMunicipality));
        $arrDestinations = self::destinations($strMunicipality);
        $arrEstablishments = self::establishments($strMunicipality);
        $arrFeedback = self::feedback($strMunicipality);

        return [
            ['label' => 'Tourist Arrivals (30 days)', 'value' => number_format($objArrivals->sum('visitors')), 'delta' => $objArrivals->count().' records logged', 'tone' => 'neutral'],
            ['label' => 'Tourism Destinations', 'value' => (string) count($arrDestinations), 'delta' => 'Managed by your office', 'tone' => 'neutral'],
            ['label' => 'Tourism Establishments', 'value' => (string) count($arrEstablishments), 'delta' => collect($arrEstablishments)->where('isPubliclyVisible', true)->count().' published', 'tone' => 'success'],
            ['label' => 'Tourist Feedback', 'value' => (string) count($arrFeedback), 'delta' => 'This period', 'tone' => 'neutral'],
        ];
    }

    /**
     * Arrival trend, scaled down from the province-wide series by this
     * municipality's approximate share of total visits.
     *
     * @return array<string, array<int, array{label: string, value: int}>>
     */
    public static function arrivalTrend(string $strMunicipality): array
    {
        $fltShare = self::municipalityShare($strMunicipality);

        return collect(PtoMockData::arrivalTrend())
            ->map(fn (array $arrSeries) => collect($arrSeries)
                ->map(fn (array $arrPoint) => ['label' => $arrPoint['label'], 'value' => max(1, (int) round($arrPoint['value'] * $fltShare))])
                ->all())
            ->all();
    }

    /**
     * Positive-sentiment share over time. Nudged by a small, deterministic
     * per-municipality offset so every LGU doesn't see an identical trend.
     *
     * @return array<string, array<int, array{label: string, value: int}>>
     */
    public static function sentimentTrend(string $strMunicipality): array
    {
        $intOffset = (crc32($strMunicipality) % 11) - 5;

        return collect(PtoMockData::sentimentTrend())
            ->map(fn (array $arrSeries) => collect($arrSeries)
                ->map(fn (array $arrPoint) => ['label' => $arrPoint['label'], 'value' => max(0, min(100, $arrPoint['value'] + $intOffset))])
                ->all())
            ->all();
    }

    /**
     * @return array{positive: int, neutral: int, negative: int}
     */
    public static function sentimentBreakdown(string $strMunicipality): array
    {
        $objFeedback = collect(self::feedback($strMunicipality));

        return [
            'positive' => $objFeedback->where('sentiment', 'Positive')->count(),
            'neutral' => $objFeedback->where('sentiment', 'Neutral')->count(),
            'negative' => $objFeedback->where('sentiment', 'Negative')->count(),
        ];
    }

    /**
     * Establishment counts grouped by category, for the dashboard's
     * Establishment Overview.
     *
     * @return array<int, array{label: string, count: int}>
     */
    public static function establishmentCategories(string $strMunicipality): array
    {
        return collect(self::establishments($strMunicipality))
            ->groupBy('category')
            ->map(fn ($objGroup, $category) => ['label' => TourismCatalog::categoryLabel($category), 'count' => $objGroup->count()])
            ->values()
            ->all();
    }

    /**
     * Recent activity mentioning an establishment or destination that
     * belongs to this municipality.
     *
     * @return array<int, array{type: string, title: string, description: string, icon: string, time: string}>
     */
    public static function recentActivity(string $strMunicipality): array
    {
        $objNames = collect(self::destinations($strMunicipality))->pluck('name')
            ->merge(collect(self::establishments($strMunicipality))->pluck('name'));

        return collect(PtoMockData::recentActivity())
            ->filter(fn (array $arrActivity) => $objNames->contains(fn ($strName) => str_contains($arrActivity['description'], $strName)))
            ->values()
            ->all();
    }

    /**
     * The five municipality-level report types the LGU can generate.
     *
     * @return array<int, array{key: string, label: string, description: string, icon: string, filters: array<int, string>}>
     */
    public static function reportTypes(): array
    {
        return [
            ['key' => 'arrivals', 'label' => 'Tourist Arrival Report', 'description' => 'Arrivals by date, establishment, and visitor classification for your municipality.', 'icon' => 'ti-users', 'filters' => ['classification', 'gender']],
            ['key' => 'statistics', 'label' => 'Municipal Tourism Statistics Report', 'description' => 'Visitation trends and establishment comparison within your municipality.', 'icon' => 'ti-chart-line', 'filters' => []],
            ['key' => 'destinations', 'label' => 'Destination Performance Report', 'description' => 'Ranked visits and trend for destinations in your municipality.', 'icon' => 'ti-map-pin', 'filters' => ['destination']],
            ['key' => 'feedback', 'label' => 'Tourist Feedback Report', 'description' => 'Feedback entries with sentiment and polarity for your municipality.', 'icon' => 'ti-message-2', 'filters' => ['sentiment']],
            ['key' => 'experience', 'label' => 'Tourist Experience Analytics Report', 'description' => 'Sentiment breakdown and trends for your municipality.', 'icon' => 'ti-heart-handshake', 'filters' => []],
        ];
    }

    /**
     * Previously generated reports for this municipality.
     *
     * @return array<int, array{name: string, typeKey: string, type: string, range: string, generatedAt: string, generatedBy: string}>
     */
    public static function reportHistory(string $strMunicipality): array
    {
        return [
            ['name' => "Tourist Arrival Report — {$strMunicipality}, July 2026", 'typeKey' => 'arrivals', 'type' => 'Tourist Arrival Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-02', 'generatedBy' => 'Arnel Dizon'],
            ['name' => "Municipal Tourism Statistics Report — {$strMunicipality}, Q2 2026", 'typeKey' => 'statistics', 'type' => 'Municipal Tourism Statistics Report', 'range' => 'Apr 1 – Jun 30, 2026', 'generatedAt' => '2026-07-05', 'generatedBy' => 'Arnel Dizon'],
            ['name' => "Tourist Feedback Report — {$strMunicipality}, July 2026", 'typeKey' => 'feedback', 'type' => 'Tourist Feedback Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-01', 'generatedBy' => 'Arnel Dizon'],
        ];
    }

    /**
     * Pre-shaped content for each report type's preview panel, keyed by
     * report-type key. Drives the Reports page's report preview: summary
     * stat cards, an optional chart, an optional breakdown table, and an
     * optional detailed-records table.
     *
     * @return array<string, array{summary: array<int, array{label: string, value: string}>, chart: array<string, mixed>|null, breakdown: array{label: string, columns: array<int, string>, rows: array<int, array<int, string>>}|null, columns: array<int, string>, rows: array<int, array<int, string>>, filterable: bool, empty: bool}>
     */
    public static function reportPreviewData(string $strMunicipality): array
    {
        $arrArrivals = self::arrivals($strMunicipality);
        $arrDestinations = self::destinationPerformance($strMunicipality);
        $arrFeedback = self::feedback($strMunicipality);
        $arrSentiment = self::sentimentBreakdown($strMunicipality);
        $intSentimentTotal = array_sum($arrSentiment);

        $intArrivalsTotal = collect($arrArrivals)->sum('visitors');
        $intForeignTotal = collect($arrArrivals)->where('classification', 'Foreign')->sum('visitors');
        $objClassificationTotals = collect($arrArrivals)->groupBy('classification')
            ->map(fn ($objRows) => $objRows->sum('visitors'));

        // Arrivals are recorded per establishment, not per destination, so the
        // arrivals-report breakdown groups by establishment (self-consistent
        // with the arrivals total above); the statistics-report breakdown
        // uses destination performance instead, a separate real metric.
        $arrEstablishmentBreakdown = collect($arrArrivals)->groupBy('establishment')
            ->map(fn ($objRows, $establishment) => [$establishment, number_format($objRows->sum('visitors')), (string) $objRows->count()])
            ->sortByDesc(fn ($arrRow) => (int) str_replace(',', '', $arrRow[1]))
            ->values()->all();

        $arrDestinationBreakdown = collect($arrDestinations)
            ->map(fn ($arrRow) => [$arrRow['destination'], number_format($arrRow['visits']), ucfirst($arrRow['trend'])])
            ->all();

        return [
            'arrivals' => [
                'summary' => [
                    ['label' => 'Total Arrivals', 'value' => number_format($intArrivalsTotal)],
                    ['label' => 'Domestic Visitors', 'value' => number_format($intArrivalsTotal - $intForeignTotal)],
                    ['label' => 'Foreign Visitors', 'value' => number_format($intForeignTotal)],
                ],
                'chart' => [
                    'type' => 'bar',
                    'title' => 'Visitor Classification Distribution',
                    'items' => $objClassificationTotals->map(fn ($value, $strLabel) => ['label' => $strLabel, 'value' => $value])->values()->all(),
                ],
                'breakdown' => ['label' => 'Establishment', 'columns' => ['Establishment', 'Arrivals', 'Records'], 'rows' => $arrEstablishmentBreakdown],
                'columns' => ['Date', 'Establishment', 'Classification', 'Gender', 'Visitors'],
                'rows' => collect($arrArrivals)->map(fn ($arrRow) => [
                    Carbon::parse($arrRow['date'])->format('M j, Y'),
                    $arrRow['establishment'], $arrRow['classification'], $arrRow['gender'], number_format($arrRow['visitors']),
                ])->all(),
                'filterable' => true,
                'empty' => $intArrivalsTotal === 0,
            ],
            'statistics' => [
                'summary' => [
                    ['label' => 'Total Arrivals', 'value' => number_format($intArrivalsTotal)],
                    ['label' => 'Destinations Tracked', 'value' => (string) count($arrDestinations)],
                ],
                'chart' => [
                    'type' => 'trend',
                    'title' => 'Visitor Trend',
                    'labels' => collect(self::arrivalTrend($strMunicipality)['month'])->pluck('label')->all(),
                    'values' => collect(self::arrivalTrend($strMunicipality)['month'])->pluck('value')->all(),
                ],
                'breakdown' => ['label' => 'Destination', 'columns' => ['Destination', 'Visits', 'Trend'], 'rows' => $arrDestinationBreakdown],
                'columns' => [],
                'rows' => [],
                'filterable' => false,
                'empty' => false,
            ],
            'destinations' => [
                'summary' => [
                    ['label' => 'Destinations Tracked', 'value' => (string) count($arrDestinations)],
                    ['label' => 'Top Destination', 'value' => $arrDestinations[0]['destination'] ?? '—'],
                ],
                'chart' => [
                    'type' => 'bar',
                    'title' => 'Visits per Destination',
                    'items' => collect($arrDestinations)->map(fn ($arrRow) => ['label' => $arrRow['destination'], 'value' => $arrRow['visits']])->all(),
                ],
                'breakdown' => null,
                'columns' => ['#', 'Destination', 'Visits', 'Trend'],
                'rows' => collect($arrDestinations)->map(fn ($arrRow) => [
                    (string) $arrRow['rank'], $arrRow['destination'], number_format($arrRow['visits']), ucfirst($arrRow['trend']),
                ])->all(),
                'filterable' => false,
                'empty' => count($arrDestinations) === 0,
            ],
            'feedback' => [
                'summary' => [
                    ['label' => 'Feedback Entries', 'value' => (string) count($arrFeedback)],
                    ['label' => 'Positive', 'value' => (string) collect($arrFeedback)->where('sentiment', 'Positive')->count()],
                    ['label' => 'Negative', 'value' => (string) collect($arrFeedback)->where('sentiment', 'Negative')->count()],
                ],
                'chart' => ['type' => 'donut', 'positive' => $arrSentiment['positive'], 'neutral' => $arrSentiment['neutral'], 'negative' => $arrSentiment['negative']],
                'breakdown' => null,
                'columns' => ['Date', 'Subject', 'Sentiment', 'Feedback'],
                'rows' => collect($arrFeedback)->map(fn ($arrRow) => [
                    Carbon::parse($arrRow['date'])->format('M j, Y'),
                    $arrRow['subject'], $arrRow['sentiment'], Str::limit($arrRow['text'], 70),
                ])->all(),
                'filterable' => true,
                'empty' => count($arrFeedback) === 0,
            ],
            'experience' => [
                'summary' => [
                    ['label' => 'Feedback Analyzed', 'value' => number_format($intSentimentTotal)],
                    ['label' => 'Positive Share', 'value' => $intSentimentTotal ? round(($arrSentiment['positive'] / $intSentimentTotal) * 100).'%' : '—'],
                    ['label' => 'Negative Entries', 'value' => number_format($arrSentiment['negative'])],
                ],
                'chart' => ['type' => 'donut', 'positive' => $arrSentiment['positive'], 'neutral' => $arrSentiment['neutral'], 'negative' => $arrSentiment['negative']],
                'breakdown' => null,
                'columns' => [],
                'rows' => [],
                'filterable' => false,
                'empty' => $intSentimentTotal === 0,
            ],
        ];
    }

    /**
     * This municipality's approximate share of province-wide visits, used
     * to scale province-wide mock series down to a plausible municipal size.
     */
    private static function municipalityShare(string $strMunicipality): float
    {
        $objComparison = collect(PtoMockData::municipalityComparison());
        $intTotal = $objComparison->sum('visits') ?: 1;
        $intVisits = $objComparison->firstWhere('municipality', $strMunicipality)['visits'] ?? 0;

        return max(0.03, $intVisits / $intTotal);
    }
}
