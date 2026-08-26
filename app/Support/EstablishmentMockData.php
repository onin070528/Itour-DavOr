<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
     * @return array<int, array{key: string, label: string, description: string, icon: string, filters: array<int, string>}>
     */
    public static function reportTypes(): array
    {
        return [
            ['key' => 'arrivals', 'label' => 'Tourist Arrival Report', 'description' => 'Guest arrivals with classification, gender, and remarks.', 'icon' => 'ti-users', 'filters' => ['classification', 'gender']],
            ['key' => 'statistics', 'label' => 'Visitor Statistics Report', 'description' => 'Visitor trend and classification for your establishment.', 'icon' => 'ti-chart-bar', 'filters' => []],
            ['key' => 'feedback', 'label' => 'Tourist Feedback Report', 'description' => 'Feedback entries with sentiment and polarity score.', 'icon' => 'ti-message-2', 'filters' => ['sentiment']],
            ['key' => 'experience', 'label' => 'Tourist Experience Analytics Report', 'description' => 'Sentiment breakdown and trend for your establishment.', 'icon' => 'ti-heart-handshake', 'filters' => []],
        ];
    }

    /**
     * @return array<int, array{name: string, typeKey: string, type: string, range: string, generatedAt: string, generatedBy: string}>
     */
    public static function reportHistory(string $name): array
    {
        if (! self::arrivals($name)) {
            return [];
        }

        return [
            ['name' => "Tourist Arrival Report — {$name}, July 2026", 'typeKey' => 'arrivals', 'type' => 'Tourist Arrival Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-02', 'generatedBy' => 'Front Desk Account'],
            ['name' => "Tourist Feedback Report — {$name}, July 2026", 'typeKey' => 'feedback', 'type' => 'Tourist Feedback Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-01', 'generatedBy' => 'Front Desk Account'],
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
    public static function reportPreviewData(string $name): array
    {
        $arrivals = self::arrivals($name);
        $feedback = self::feedback($name);
        $sentiment = self::sentimentBreakdown($name);
        $sentimentTotal = array_sum($sentiment);
        $classifications = self::classificationBreakdown($name);
        $foreignCount = collect($arrivals)->where('classification', 'Foreign')->count();

        return [
            'arrivals' => [
                'summary' => [
                    ['label' => 'Total Arrivals', 'value' => (string) count($arrivals)],
                    ['label' => 'Domestic Visitors', 'value' => (string) (count($arrivals) - $foreignCount)],
                    ['label' => 'Foreign Visitors', 'value' => (string) $foreignCount],
                    ['label' => 'Recorded', 'value' => (string) collect($arrivals)->where('status', 'Recorded')->count()],
                ],
                'chart' => [
                    'type' => 'bar',
                    'title' => 'Visitor Classification Distribution',
                    'items' => $classifications->map(fn ($value, $label) => ['label' => $label, 'value' => $value])->values()->all(),
                ],
                'breakdown' => null,
                'columns' => ['Date', 'Visitor Name', 'Gender', 'Classification', 'Remarks'],
                'rows' => collect($arrivals)->map(fn ($row) => [
                    Carbon::parse($row['date'])->format('M j, Y'),
                    $row['visitorName'] ?? 'Guest', $row['gender'], $row['classification'], $row['remarks'] ?? '—',
                ])->all(),
                'filterable' => true,
                'empty' => count($arrivals) === 0,
            ],
            'statistics' => [
                'summary' => [
                    ['label' => 'Total Arrivals', 'value' => (string) count($arrivals)],
                    ['label' => 'Classifications Tracked', 'value' => (string) $classifications->count()],
                ],
                'chart' => [
                    'type' => 'trend',
                    'title' => 'Visitor Trend',
                    'labels' => collect(self::arrivalTrend($name)['month'])->pluck('label')->all(),
                    'values' => collect(self::arrivalTrend($name)['month'])->pluck('value')->all(),
                ],
                'breakdown' => null,
                'columns' => ['Classification', 'Visitors'],
                'rows' => $classifications->map(fn ($count, $label) => [$label, (string) $count])->values()->all(),
                'filterable' => false,
                'empty' => count($arrivals) === 0,
            ],
            'feedback' => [
                'summary' => [
                    ['label' => 'Feedback Entries', 'value' => (string) count($feedback)],
                    ['label' => 'Positive', 'value' => (string) collect($feedback)->where('sentiment', 'Positive')->count()],
                    ['label' => 'Negative', 'value' => (string) collect($feedback)->where('sentiment', 'Negative')->count()],
                ],
                'chart' => ['type' => 'donut', 'positive' => $sentiment['positive'], 'neutral' => $sentiment['neutral'], 'negative' => $sentiment['negative']],
                'breakdown' => null,
                'columns' => ['Date', 'Sentiment', 'Feedback'],
                'rows' => collect($feedback)->map(fn ($row) => [
                    Carbon::parse($row['date'])->format('M j, Y'),
                    $row['sentiment'], Str::limit($row['text'], 70),
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
}
