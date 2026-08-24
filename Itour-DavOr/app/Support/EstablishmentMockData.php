<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Establishment-scoped view over TourismCatalog and PtoMockData, plus a
 * small set of individual visitor-level arrival records (the establishment
 * records one row per guest/party at the front desk or via QR
 * self-registration — a finer grain than the aggregate counts PTO/LGU see).
 *
 * Every method here takes the establishment's exact name (matches
 * TourismCatalog listing names and PtoMockData's `establishment`/`subject`
 * fields) and returns only that establishment's slice of data.
 */
class EstablishmentMockData
{
    /**
     * The establishment's own tourism directory listing, if it has one.
     */
    public static function profile(string $name): ?array
    {
        return collect(TourismCatalog::listings())->firstWhere('name', $name);
    }

    /**
     * Featured/primary image plus a small photo gallery. Reuses existing
     * itour-images assets — there's no multi-image upload backend yet.
     *
     * @return array<int, array{path: string, caption: string, primary: bool}>
     */
    public static function galleryImages(string $name): array
    {
        $profile = self::profile($name);
        if (! $profile) {
            return [];
        }

        $extras = ['dahican.jpg', 'pujada-bay.jpg', 'sunrise-point.jpg', 'cove.jpg'];
        $extras = array_values(array_diff($extras, [$profile['image']]));

        return [
            ['path' => $profile['image'], 'caption' => 'Featured photo', 'primary' => true],
            ['path' => $extras[0], 'caption' => 'Grounds & surroundings', 'primary' => false],
            ['path' => $extras[1], 'caption' => 'Nearby view', 'primary' => false],
        ];
    }

    /**
     * Individual guest arrival records for this establishment. Only
     * populated for establishments that have demo data — an establishment
     * with no submissions yet correctly sees an empty state.
     *
     * @return array<int, array{id: string, date: string, visitorName: ?string, gender: string, classification: string, remarks: ?string, status: string}>
     */
    public static function arrivals(string $name): array
    {
        $rows = match ($name) {
            'Botanika Nature Resort' => [
                ['2026-08-22', 'Kim Soo-jin', 'Female', 'Foreign', 'Celebrating a birthday', 'Recorded'],
                ['2026-08-22', null, 'Male', 'Domestic (Other Province)', null, 'Recorded'],
                ['2026-08-21', 'Marites A.', 'Female', 'Local (Same Province)', null, 'Recorded'],
                ['2026-08-21', null, 'Male', 'Foreign', 'Group of 2', 'Recorded'],
                ['2026-08-20', 'Marco D.', 'Male', 'Domestic (Other Province)', 'Return guest', 'Recorded'],
                ['2026-08-19', null, 'Female', 'Foreign', null, 'Recorded'],
                ['2026-08-19', 'Anna P.', 'Female', 'Local (Same Province)', null, 'Under Review'],
                ['2026-08-18', null, 'Male', 'Domestic (Other Province)', 'Requested airport transfer', 'Recorded'],
                ['2026-08-17', 'Diego R.', 'Male', 'Foreign', null, 'Recorded'],
                ['2026-08-16', null, 'Female', 'Domestic (Other Province)', null, 'Recorded'],
                ['2026-08-15', 'Front Desk Entry', 'Male', 'Local (Same Province)', 'Walk-in', 'Recorded'],
                ['2026-08-14', null, 'Female', 'Foreign', 'Anniversary stay', 'Recorded'],
            ],
            default => [],
        };

        return collect($rows)->map(fn ($row, $i) => [
            'id' => 'GR-'.(2026080100 - $i),
            'date' => $row[0],
            'visitorName' => $row[1],
            'gender' => $row[2],
            'classification' => $row[3],
            'remarks' => $row[4],
            'status' => $row[5],
        ])->all();
    }

    /**
     * @return array<int, array{label: string, value: string, delta: string, tone: string}>
     */
    public static function dashboardSummary(string $name): array
    {
        $arrivals = collect(self::arrivals($name));
        $thisMonth = $arrivals->filter(fn ($row) => str_starts_with($row['date'], '2026-08'));
        $feedback = collect(self::feedback($name));
        $sentiment = self::sentimentBreakdown($name);
        $total = array_sum($sentiment);
        $positivePct = $total ? round(($sentiment['positive'] / $total) * 100) : null;

        return [
            ['label' => 'Total Tourist Arrivals', 'value' => (string) $arrivals->count(), 'delta' => 'All recorded visits', 'tone' => 'neutral'],
            ['label' => "This Month's Visitors", 'value' => (string) $thisMonth->count(), 'delta' => 'August 2026', 'tone' => 'success'],
            ['label' => 'Tourist Feedback', 'value' => (string) $feedback->count(), 'delta' => 'All time', 'tone' => 'neutral'],
            ['label' => 'Overall Sentiment', 'value' => $positivePct !== null ? "{$positivePct}% Positive" : '—', 'delta' => $total ? "{$total} entries analyzed" : 'No feedback yet', 'tone' => 'success'],
        ];
    }

    /**
     * Arrival trend, scaled down from the province-wide series by a small
     * deterministic factor so a single establishment doesn't show
     * municipality-sized numbers.
     *
     * @return array<string, array<int, array{label: string, value: int}>>
     */
    public static function arrivalTrend(string $name): array
    {
        $share = max(0.01, (crc32($name) % 7 + 3) / 100);

        return collect(PtoMockData::arrivalTrend())
            ->map(fn (array $series) => collect($series)
                ->map(fn (array $point) => ['label' => $point['label'], 'value' => max(0, (int) round($point['value'] * $share))])
                ->all())
            ->all();
    }

    /**
     * @return Collection<string, int>
     */
    public static function classificationBreakdown(string $name)
    {
        return collect(self::arrivals($name))->countBy('classification');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function feedback(string $name): array
    {
        return collect(PtoMockData::feedback())
            ->where('subject', $name)
            ->values()
            ->all();
    }

    /**
     * @return array{positive: int, neutral: int, negative: int}
     */
    public static function sentimentBreakdown(string $name): array
    {
        $feedback = collect(self::feedback($name));

        return [
            'positive' => $feedback->where('sentiment', 'Positive')->count(),
            'neutral' => $feedback->where('sentiment', 'Neutral')->count(),
            'negative' => $feedback->where('sentiment', 'Negative')->count(),
        ];
    }

    /**
     * @return array<string, array<int, array{label: string, value: int}>>
     */
    public static function sentimentTrend(string $name): array
    {
        $offset = (crc32($name) % 15) - 7;

        return collect(PtoMockData::sentimentTrend())
            ->map(fn (array $series) => collect($series)
                ->map(fn (array $point) => ['label' => $point['label'], 'value' => max(0, min(100, $point['value'] + $offset))])
                ->all())
            ->all();
    }

    /**
     * @return array<int, array{type: string, title: string, description: string, icon: string, time: string}>
     */
    public static function recentActivity(string $name): array
    {
        return collect(PtoMockData::recentActivity())
            ->filter(fn (array $activity) => str_contains($activity['description'], $name))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, icon: string}>
     */
    public static function reportTypes(): array
    {
        return [
            ['key' => 'arrivals', 'label' => 'Tourist Arrival Report', 'description' => 'Guest arrivals with classification, gender, and remarks.', 'icon' => 'ti-users'],
            ['key' => 'statistics', 'label' => 'Visitor Statistics Report', 'description' => 'Visitor trend and classification for your establishment.', 'icon' => 'ti-chart-bar'],
            ['key' => 'feedback', 'label' => 'Tourist Feedback Report', 'description' => 'Feedback entries with sentiment and polarity score.', 'icon' => 'ti-message-2'],
            ['key' => 'experience', 'label' => 'Tourist Experience Analytics Report', 'description' => 'Sentiment breakdown and trend for your establishment.', 'icon' => 'ti-heart-handshake'],
        ];
    }

    /**
     * @return array<int, array{name: string, type: string, range: string, generatedAt: string, status: string}>
     */
    public static function reportHistory(string $name): array
    {
        if (! self::arrivals($name)) {
            return [];
        }

        return [
            ['name' => "Tourist Arrival Report — {$name}, July 2026", 'type' => 'Tourist Arrival Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-02', 'status' => 'Ready'],
            ['name' => "Tourist Feedback Report — {$name}, July 2026", 'type' => 'Tourist Feedback Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-01', 'status' => 'Ready'],
        ];
    }
}
