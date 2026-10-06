<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Establishment-scoped read model over TourismCatalog/PtoMockData/Arrival data.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Models\Arrival;
use App\Models\Listing;
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
    public static function profile(string $strName): ?array
    {
        return collect(TourismCatalog::listings())->firstWhere('name', $strName);
    }

    /**
     * The establishment's photo gallery, read from the real
     * `tbl_listing_images` table (App\Models\ListingImage) — backs add/
     * remove/set-featured on the Establishment Profile page.
     *
     * @return array<int, array{id: int, path: string, caption: ?string, primary: bool}>
     */
    public static function galleryImages(string $strName): array
    {
        $arrProfile = self::profile($strName);
        if (! $arrProfile) {
            return [];
        }

        return Listing::query()
            ->where('lst_slug', $arrProfile['id'])
            ->firstOrFail()
            ->images
            ->map(fn ($objImage) => [
                'id' => $objImage->lsi_id,
                'path' => $objImage->lsi_path,
                'caption' => $objImage->lsi_caption,
                'primary' => $objImage->lsi_is_primary,
            ])
            ->all();
    }

    /**
     * Individual guest arrival records for this establishment, read from
     * the real `tbl_arrivals` table (App\Models\Arrival, arr_source=staff — i.e.
     * submissions from the front-desk "Record Arrival" wizard). Seeded
     * verbatim from self::seedArrivals() by ArrivalSeeder, so every
     * existing caller of arrivals() (this class' own dashboardSummary(),
     * Establishment\ArrivalsController) keeps working unchanged against
     * real, growing data.
     *
     * @return array<int, array{id: string, date: string, visitorName: ?string, gender: ?string, classification: ?string, remarks: ?string, status: string}>
     */
    public static function arrivals(string $strName): array
    {
        $objListing = collect(TourismCatalog::listings())->firstWhere('name', $strName);

        if (! $objListing) {
            return [];
        }

        return Arrival::query()
            ->whereHas('listing', fn ($objQuery) => $objQuery->where('lst_slug', $objListing['id']))
            ->where('arr_source', 'staff')
            ->orderByDesc('arr_date')
            ->orderByDesc('arr_id')
            ->get()
            ->map(fn ($objArrival) => [
                'id' => 'GR-'.$objArrival->arr_id,
                'date' => $objArrival->arr_date->toDateString(),
                'visitorName' => $objArrival->arr_visitor_name,
                'gender' => $objArrival->arr_gender,
                'classification' => $objArrival->arr_classification,
                'remarks' => $objArrival->arr_remarks,
                'status' => $objArrival->arr_status,
            ])
            ->all();
    }

    /**
     * The original, hand-authored arrival demo rows for Botanika Nature
     * Resort — the only establishment with any — kept here as the single
     * authored source ArrivalSeeder loads into the `tbl_arrivals` table. Not
     * used for reads anymore (see arrivals() above).
     *
     * @return array<int, array{id: string, date: string, visitorName: ?string, gender: string, classification: string, remarks: ?string, status: string}>
     */
    public static function seedArrivals(string $strName): array
    {
        $arrRows = match ($strName) {
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

        return collect($arrRows)->map(fn ($arrRow, $i) => [
            'id' => 'GR-'.(2026080100 - $i),
            'date' => $arrRow[0],
            'visitorName' => $arrRow[1],
            'gender' => $arrRow[2],
            'classification' => $arrRow[3],
            'remarks' => $arrRow[4],
            'status' => $arrRow[5],
        ])->all();
    }

    /**
     * @return array<int, array{label: string, value: string, delta: string, tone: string}>
     */
    public static function dashboardSummary(string $strName): array
    {
        $objArrivals = collect(self::arrivals($strName));
        $objThisMonth = $objArrivals->filter(fn ($arrRow) => str_starts_with($arrRow['date'], '2026-08'));
        $objFeedback = collect(self::feedback($strName));
        $arrSentiment = self::sentimentBreakdown($strName);
        $intTotal = array_sum($arrSentiment);
        $fltPositivePct = $intTotal ? round(($arrSentiment['positive'] / $intTotal) * 100) : null;

        return [
            ['label' => 'Total Tourist Arrivals', 'value' => (string) $objArrivals->count(), 'delta' => 'All recorded visits', 'tone' => 'neutral'],
            ['label' => "This Month's Visitors", 'value' => (string) $objThisMonth->count(), 'delta' => 'August 2026', 'tone' => 'success'],
            ['label' => 'Tourist Feedback', 'value' => (string) $objFeedback->count(), 'delta' => 'All time', 'tone' => 'neutral'],
            ['label' => 'Overall Sentiment', 'value' => $fltPositivePct !== null ? "{$fltPositivePct}% Positive" : '—', 'delta' => $intTotal ? "{$intTotal} entries analyzed" : 'No feedback yet', 'tone' => 'success'],
        ];
    }

    /**
     * Arrival trend, scaled down from the province-wide series by a small
     * deterministic factor so a single establishment doesn't show
     * municipality-sized numbers.
     *
     * @return array<string, array<int, array{label: string, value: int}>>
     */
    public static function arrivalTrend(string $strName): array
    {
        $fltShare = max(0.01, (crc32($strName) % 7 + 3) / 100);

        return collect(PtoMockData::arrivalTrend())
            ->map(fn (array $arrSeries) => collect($arrSeries)
                ->map(fn (array $arrPoint) => ['label' => $arrPoint['label'], 'value' => max(0, (int) round($arrPoint['value'] * $fltShare))])
                ->all())
            ->all();
    }

    /**
     * @return Collection<string, int>
     */
    public static function classificationBreakdown(string $strName)
    {
        return collect(self::arrivals($strName))->countBy('classification');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function feedback(string $strName): array
    {
        return collect(PtoMockData::feedback())
            ->where('subject', $strName)
            ->values()
            ->all();
    }

    /**
     * @return array{positive: int, neutral: int, negative: int}
     */
    public static function sentimentBreakdown(string $strName): array
    {
        $objFeedback = collect(self::feedback($strName));

        return [
            'positive' => $objFeedback->where('sentiment', 'Positive')->count(),
            'neutral' => $objFeedback->where('sentiment', 'Neutral')->count(),
            'negative' => $objFeedback->where('sentiment', 'Negative')->count(),
        ];
    }

    /**
     * @return array<string, array<int, array{label: string, value: int}>>
     */
    public static function sentimentTrend(string $strName): array
    {
        $intOffset = (crc32($strName) % 15) - 7;

        return collect(PtoMockData::sentimentTrend())
            ->map(fn (array $arrSeries) => collect($arrSeries)
                ->map(fn (array $arrPoint) => ['label' => $arrPoint['label'], 'value' => max(0, min(100, $arrPoint['value'] + $intOffset))])
                ->all())
            ->all();
    }

    /**
     * @return array<int, array{type: string, title: string, description: string, icon: string, time: string}>
     */
    public static function recentActivity(string $strName): array
    {
        return collect(PtoMockData::recentActivity())
            ->filter(fn (array $arrActivity) => str_contains($arrActivity['description'], $strName))
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
    public static function reportHistory(string $strName): array
    {
        if (! self::arrivals($strName)) {
            return [];
        }

        return [
            ['name' => "Tourist Arrival Report — {$strName}, July 2026", 'typeKey' => 'arrivals', 'type' => 'Tourist Arrival Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-02', 'generatedBy' => 'Front Desk Account'],
            ['name' => "Tourist Feedback Report — {$strName}, July 2026", 'typeKey' => 'feedback', 'type' => 'Tourist Feedback Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-01', 'generatedBy' => 'Front Desk Account'],
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
    public static function reportPreviewData(string $strName): array
    {
        $arrArrivals = self::arrivals($strName);
        $arrFeedback = self::feedback($strName);
        $arrSentiment = self::sentimentBreakdown($strName);
        $intSentimentTotal = array_sum($arrSentiment);
        $objClassifications = self::classificationBreakdown($strName);
        $intForeignCount = collect($arrArrivals)->where('classification', 'Foreign')->count();

        return [
            'arrivals' => [
                'summary' => [
                    ['label' => 'Total Arrivals', 'value' => (string) count($arrArrivals)],
                    ['label' => 'Domestic Visitors', 'value' => (string) (count($arrArrivals) - $intForeignCount)],
                    ['label' => 'Foreign Visitors', 'value' => (string) $intForeignCount],
                    ['label' => 'Recorded', 'value' => (string) collect($arrArrivals)->where('status', 'Recorded')->count()],
                ],
                'chart' => [
                    'type' => 'bar',
                    'title' => 'Visitor Classification Distribution',
                    'items' => $objClassifications->map(fn ($value, $strLabel) => ['label' => $strLabel, 'value' => $value])->values()->all(),
                ],
                'breakdown' => null,
                'columns' => ['Date', 'Visitor Name', 'Gender', 'Classification', 'Remarks'],
                'rows' => collect($arrArrivals)->map(fn ($arrRow) => [
                    Carbon::parse($arrRow['date'])->format('M j, Y'),
                    $arrRow['visitorName'] ?? 'Guest', $arrRow['gender'], $arrRow['classification'], $arrRow['remarks'] ?? '—',
                ])->all(),
                'filterable' => true,
                'empty' => count($arrArrivals) === 0,
            ],
            'statistics' => [
                'summary' => [
                    ['label' => 'Total Arrivals', 'value' => (string) count($arrArrivals)],
                    ['label' => 'Classifications Tracked', 'value' => (string) $objClassifications->count()],
                ],
                'chart' => [
                    'type' => 'trend',
                    'title' => 'Visitor Trend',
                    'labels' => collect(self::arrivalTrend($strName)['month'])->pluck('label')->all(),
                    'values' => collect(self::arrivalTrend($strName)['month'])->pluck('value')->all(),
                ],
                'breakdown' => null,
                'columns' => ['Classification', 'Visitors'],
                'rows' => $objClassifications->map(fn ($intCount, $strLabel) => [$strLabel, (string) $intCount])->values()->all(),
                'filterable' => false,
                'empty' => count($arrArrivals) === 0,
            ],
            'feedback' => [
                'summary' => [
                    ['label' => 'Feedback Entries', 'value' => (string) count($arrFeedback)],
                    ['label' => 'Positive', 'value' => (string) collect($arrFeedback)->where('sentiment', 'Positive')->count()],
                    ['label' => 'Negative', 'value' => (string) collect($arrFeedback)->where('sentiment', 'Negative')->count()],
                ],
                'chart' => ['type' => 'donut', 'positive' => $arrSentiment['positive'], 'neutral' => $arrSentiment['neutral'], 'negative' => $arrSentiment['negative']],
                'breakdown' => null,
                'columns' => ['Date', 'Sentiment', 'Feedback'],
                'rows' => collect($arrFeedback)->map(fn ($arrRow) => [
                    Carbon::parse($arrRow['date'])->format('M j, Y'),
                    $arrRow['sentiment'], Str::limit($arrRow['text'], 70),
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
}
