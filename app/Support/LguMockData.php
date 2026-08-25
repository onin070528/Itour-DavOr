<?php

namespace App\Support;

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
     * @return array<int, array{key: string, label: string, description: string, icon: string}>
     */
    public static function reportTypes(): array
    {
        return [
            ['key' => 'arrivals', 'label' => 'Tourist Arrival Report', 'description' => 'Arrivals by date, establishment, and visitor classification for your municipality.', 'icon' => 'ti-users'],
            ['key' => 'statistics', 'label' => 'Municipal Tourism Statistics Report', 'description' => 'Visitation trends and establishment comparison within your municipality.', 'icon' => 'ti-chart-line'],
            ['key' => 'destinations', 'label' => 'Destination Performance Report', 'description' => 'Ranked visits and trend for destinations in your municipality.', 'icon' => 'ti-map-pin'],
            ['key' => 'feedback', 'label' => 'Tourist Feedback Report', 'description' => 'Feedback entries with sentiment and polarity for your municipality.', 'icon' => 'ti-message-2'],
            ['key' => 'experience', 'label' => 'Tourist Experience Analytics Report', 'description' => 'Sentiment breakdown and trends for your municipality.', 'icon' => 'ti-heart-handshake'],
        ];
    }

    /**
     * Previously generated reports for this municipality.
     *
     * @return array<int, array{name: string, type: string, range: string, generatedAt: string, status: string}>
     */
    public static function reportHistory(string $municipality): array
    {
        return [
            ['name' => "Tourist Arrival Report — {$municipality}, July 2026", 'type' => 'Tourist Arrival Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-02', 'status' => 'Ready'],
            ['name' => "Municipal Tourism Statistics Report — {$municipality}, Q2 2026", 'type' => 'Municipal Tourism Statistics Report', 'range' => 'Apr 1 – Jun 30, 2026', 'generatedAt' => '2026-07-05', 'status' => 'Ready'],
            ['name' => "Tourist Feedback Report — {$municipality}, July 2026", 'type' => 'Tourist Feedback Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-01', 'status' => 'Ready'],
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
