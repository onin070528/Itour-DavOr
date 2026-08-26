<?php

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
    public static function destinations(string $municipality): array
    {
        return collect(TourismCatalog::listings())
            ->where('category', 'destinations')
            ->where('municipality', $municipality)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function establishments(string $municipality): array
    {
        // Mirrors DirectoryController@establishments' mock accreditation status.
        $pending = ['dahican-surf-guides', 'delicacies-hub'];
        $inactive = ['tourist-transport-terminal'];

        return collect(TourismCatalog::listings())
            ->where('category', '!=', 'destinations')
            ->where('municipality', $municipality)
            ->values()
            ->map(function (array $listing) use ($pending, $inactive) {
                $listing['status'] = match (true) {
                    in_array($listing['id'], $pending, true) => 'Pending Review',
                    in_array($listing['id'], $inactive, true) => 'Inactive',
                    default => 'Active',
                };

                return $listing;
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function arrivals(string $municipality): array
    {
        return collect(PtoMockData::arrivals())
            ->where('municipality', $municipality)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function feedback(string $municipality): array
    {
        $subjects = collect(self::destinations($municipality))->pluck('name')
            ->merge(collect(self::establishments($municipality))->pluck('name'));

        return collect(PtoMockData::feedback())
            ->whereIn('subject', $subjects)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function destinationPerformance(string $municipality): array
    {
        return collect(PtoMockData::destinationPerformance())
            ->where('municipality', $municipality)
            ->values()
            ->map(fn (array $row, int $i) => [...$row, 'rank' => $i + 1])
            ->all();
    }

    /**
     * Dashboard summary KPI cards, scoped to the municipality.
     *
     * @return array<int, array{label: string, value: string, delta: string, tone: string}>
     */
    public static function dashboardSummary(string $municipality): array
    {
        $arrivals = collect(self::arrivals($municipality));
        $destinations = self::destinations($municipality);
        $establishments = self::establishments($municipality);
        $feedback = self::feedback($municipality);

        return [
            ['label' => 'Tourist Arrivals (30 days)', 'value' => number_format($arrivals->sum('visitors')), 'delta' => $arrivals->count().' records logged', 'tone' => 'neutral'],
            ['label' => 'Tourism Destinations', 'value' => (string) count($destinations), 'delta' => 'Managed by your office', 'tone' => 'neutral'],
            ['label' => 'Tourism Establishments', 'value' => (string) count($establishments), 'delta' => collect($establishments)->where('status', 'Active')->count().' active', 'tone' => 'success'],
            ['label' => 'Tourist Feedback', 'value' => (string) count($feedback), 'delta' => 'This period', 'tone' => 'neutral'],
        ];
    }

    /**
     * Arrival trend, scaled down from the province-wide series by this
     * municipality's approximate share of total visits.
     *
     * @return array<string, array<int, array{label: string, value: int}>>
     */
    public static function arrivalTrend(string $municipality): array
    {
        $share = self::municipalityShare($municipality);

        return collect(PtoMockData::arrivalTrend())
            ->map(fn (array $series) => collect($series)
                ->map(fn (array $point) => ['label' => $point['label'], 'value' => max(1, (int) round($point['value'] * $share))])
                ->all())
            ->all();
    }

    /**
     * Positive-sentiment share over time. Nudged by a small, deterministic
     * per-municipality offset so every LGU doesn't see an identical trend.
     *
     * @return array<string, array<int, array{label: string, value: int}>>
     */
    public static function sentimentTrend(string $municipality): array
    {
        $offset = (crc32($municipality) % 11) - 5;

        return collect(PtoMockData::sentimentTrend())
            ->map(fn (array $series) => collect($series)
                ->map(fn (array $point) => ['label' => $point['label'], 'value' => max(0, min(100, $point['value'] + $offset))])
                ->all())
            ->all();
    }

    /**
     * @return array{positive: int, neutral: int, negative: int}
     */
    public static function sentimentBreakdown(string $municipality): array
    {
        $feedback = collect(self::feedback($municipality));

        return [
            'positive' => $feedback->where('sentiment', 'Positive')->count(),
            'neutral' => $feedback->where('sentiment', 'Neutral')->count(),
            'negative' => $feedback->where('sentiment', 'Negative')->count(),
        ];
    }

    /**
     * Establishment counts grouped by category, for the dashboard's
     * Establishment Overview.
     *
     * @return array<int, array{label: string, count: int}>
     */
    public static function establishmentCategories(string $municipality): array
    {
        return collect(self::establishments($municipality))
            ->groupBy('category')
            ->map(fn ($group, $category) => ['label' => TourismCatalog::categoryLabel($category), 'count' => $group->count()])
            ->values()
            ->all();
    }

    /**
     * Recent activity mentioning an establishment or destination that
     * belongs to this municipality.
     *
     * @return array<int, array{type: string, title: string, description: string, icon: string, time: string}>
     */
    public static function recentActivity(string $municipality): array
    {
        $names = collect(self::destinations($municipality))->pluck('name')
            ->merge(collect(self::establishments($municipality))->pluck('name'));

        return collect(PtoMockData::recentActivity())
            ->filter(fn (array $activity) => $names->contains(fn ($name) => str_contains($activity['description'], $name)))
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
    public static function reportHistory(string $municipality): array
    {
        return [
            ['name' => "Tourist Arrival Report — {$municipality}, July 2026", 'typeKey' => 'arrivals', 'type' => 'Tourist Arrival Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-02', 'generatedBy' => 'Arnel Dizon'],
            ['name' => "Municipal Tourism Statistics Report — {$municipality}, Q2 2026", 'typeKey' => 'statistics', 'type' => 'Municipal Tourism Statistics Report', 'range' => 'Apr 1 – Jun 30, 2026', 'generatedAt' => '2026-07-05', 'generatedBy' => 'Arnel Dizon'],
            ['name' => "Tourist Feedback Report — {$municipality}, July 2026", 'typeKey' => 'feedback', 'type' => 'Tourist Feedback Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-01', 'generatedBy' => 'Arnel Dizon'],
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
    public static function reportPreviewData(string $municipality): array
    {
        $arrivals = self::arrivals($municipality);
        $destinations = self::destinationPerformance($municipality);
        $feedback = self::feedback($municipality);
        $sentiment = self::sentimentBreakdown($municipality);
        $sentimentTotal = array_sum($sentiment);

        $arrivalsTotal = collect($arrivals)->sum('visitors');
        $foreignTotal = collect($arrivals)->where('classification', 'Foreign')->sum('visitors');
        $classificationTotals = collect($arrivals)->groupBy('classification')
            ->map(fn ($rows) => $rows->sum('visitors'));

        // Arrivals are recorded per establishment, not per destination, so the
        // arrivals-report breakdown groups by establishment (self-consistent
        // with the arrivals total above); the statistics-report breakdown
        // uses destination performance instead, a separate real metric.
        $establishmentBreakdown = collect($arrivals)->groupBy('establishment')
            ->map(fn ($rows, $establishment) => [$establishment, number_format($rows->sum('visitors')), (string) $rows->count()])
            ->sortByDesc(fn ($row) => (int) str_replace(',', '', $row[1]))
            ->values()->all();

        $destinationBreakdown = collect($destinations)
            ->map(fn ($row) => [$row['destination'], number_format($row['visits']), ucfirst($row['trend'])])
            ->all();

        return [
            'arrivals' => [
                'summary' => [
                    ['label' => 'Total Arrivals', 'value' => number_format($arrivalsTotal)],
                    ['label' => 'Domestic Visitors', 'value' => number_format($arrivalsTotal - $foreignTotal)],
                    ['label' => 'Foreign Visitors', 'value' => number_format($foreignTotal)],
                ],
                'chart' => [
                    'type' => 'bar',
                    'title' => 'Visitor Classification Distribution',
                    'items' => $classificationTotals->map(fn ($value, $label) => ['label' => $label, 'value' => $value])->values()->all(),
                ],
                'breakdown' => ['label' => 'Establishment', 'columns' => ['Establishment', 'Arrivals', 'Records'], 'rows' => $establishmentBreakdown],
                'columns' => ['Date', 'Establishment', 'Classification', 'Gender', 'Visitors'],
                'rows' => collect($arrivals)->map(fn ($row) => [
                    Carbon::parse($row['date'])->format('M j, Y'),
                    $row['establishment'], $row['classification'], $row['gender'], number_format($row['visitors']),
                ])->all(),
                'filterable' => true,
                'empty' => $arrivalsTotal === 0,
            ],
            'statistics' => [
                'summary' => [
                    ['label' => 'Total Arrivals', 'value' => number_format($arrivalsTotal)],
                    ['label' => 'Destinations Tracked', 'value' => (string) count($destinations)],
                ],
                'chart' => [
                    'type' => 'trend',
                    'title' => 'Visitor Trend',
                    'labels' => collect(self::arrivalTrend($municipality)['month'])->pluck('label')->all(),
                    'values' => collect(self::arrivalTrend($municipality)['month'])->pluck('value')->all(),
                ],
                'breakdown' => ['label' => 'Destination', 'columns' => ['Destination', 'Visits', 'Trend'], 'rows' => $destinationBreakdown],
                'columns' => [],
                'rows' => [],
                'filterable' => false,
                'empty' => false,
            ],
            'destinations' => [
                'summary' => [
                    ['label' => 'Destinations Tracked', 'value' => (string) count($destinations)],
                    ['label' => 'Top Destination', 'value' => $destinations[0]['destination'] ?? '—'],
                ],
                'chart' => [
                    'type' => 'bar',
                    'title' => 'Visits per Destination',
                    'items' => collect($destinations)->map(fn ($row) => ['label' => $row['destination'], 'value' => $row['visits']])->all(),
                ],
                'breakdown' => null,
                'columns' => ['#', 'Destination', 'Visits', 'Trend'],
                'rows' => collect($destinations)->map(fn ($row) => [
                    (string) $row['rank'], $row['destination'], number_format($row['visits']), ucfirst($row['trend']),
                ])->all(),
                'filterable' => false,
                'empty' => count($destinations) === 0,
            ],
            'feedback' => [
                'summary' => [
                    ['label' => 'Feedback Entries', 'value' => (string) count($feedback)],
                    ['label' => 'Positive', 'value' => (string) collect($feedback)->where('sentiment', 'Positive')->count()],
                    ['label' => 'Negative', 'value' => (string) collect($feedback)->where('sentiment', 'Negative')->count()],
                ],
                'chart' => ['type' => 'donut', 'positive' => $sentiment['positive'], 'neutral' => $sentiment['neutral'], 'negative' => $sentiment['negative']],
                'breakdown' => null,
                'columns' => ['Date', 'Subject', 'Sentiment', 'Feedback'],
                'rows' => collect($feedback)->map(fn ($row) => [
                    Carbon::parse($row['date'])->format('M j, Y'),
                    $row['subject'], $row['sentiment'], Str::limit($row['text'], 70),
                ])->all(),
                'filterable' => true,
                'empty' => count($feedback) === 0,
            ],
            'experience' => [
                'summary' => [
                    ['label' => 'Feedback Analyzed', 'value' => number_format($sentimentTotal)],
                    ['label' => 'Positive Share', 'value' => $sentimentTotal ? round(($sentiment['positive'] / $sentimentTotal) * 100).'%' : '—'],
                    ['label' => 'Negative Entries', 'value' => number_format($sentiment['negative'])],
                ],
                'chart' => ['type' => 'donut', 'positive' => $sentiment['positive'], 'neutral' => $sentiment['neutral'], 'negative' => $sentiment['negative']],
                'breakdown' => null,
                'columns' => [],
                'rows' => [],
                'filterable' => false,
                'empty' => $sentimentTotal === 0,
            ],
        ];
    }

    /**
     * This municipality's approximate share of province-wide visits, used
     * to scale province-wide mock series down to a plausible municipal size.
     */
    private static function municipalityShare(string $municipality): float
    {
        $comparison = collect(PtoMockData::municipalityComparison());
        $total = $comparison->sum('visits') ?: 1;
        $visits = $comparison->firstWhere('municipality', $municipality)['visits'] ?? 0;

        return max(0.03, $visits / $total);
    }
}
