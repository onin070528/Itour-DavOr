<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tourist experience analytics for authorized personnel
 * (Objective 4, Phase 5): processing-status counts, sentiment distribution,
 * monthly sentiment trend, common concerns, and per-listing summaries with
 * the predefined recommendations — all aggregated in the database from the
 * stored lexicon-based results. Nothing here re-scores feedback.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Services;

use App\Enums\FeedbackAnalysisStatus;
use App\Enums\SentimentClassification;
use App\Models\Feedback;
use App\Models\Listing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rules shared by every analytics screen:
 *  - The caller's scope always starts from scopedQuery() —
 *    Feedback::scopeVisibleTo() for the signed-in user (PTO: province;
 *    LGU: its municipality; Establishment: its own listing). Request input
 *    never widens it.
 *  - Sentiment numbers, trends, concerns, and recommendations count
 *    ANALYZED feedback only. Pending, failed, and rejected rows are only
 *    ever shown as processing-status counts.
 *  - The reporting period filters the submission time (fbk_created_at, in
 *    the application timezone, Asia/Manila).
 *  - Suggested improvements come only from FeedbackRecommendationService
 *    (negative-dominant listing with the configured minimum sample).
 */
class FeedbackAnalyticsService
{
    /** @var array<string, string> */
    public const PERIODS = [
        'all' => 'All Time',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'this_year' => 'This Year',
        'custom' => 'Custom Range',
    ];

    /** @var array<string, string> */
    public const LISTING_TYPES = [
        'all' => 'Destinations & establishments',
        'destinations' => 'Destinations',
        'establishments' => 'Establishments',
    ];

    /** Months shown by the sentiment trend. */
    private const TREND_MONTHS = 12;

    public function __construct(private readonly FeedbackRecommendationService $objRecommendations) {}

    /**
     * The feedback the user may see; every analytics query starts here.
     */
    public function scopedQuery(User $objUser): Builder
    {
        return Feedback::query()->visibleTo($objUser);
    }

    /**
     * Reads ?period= (and ?from= / ?to= for a custom range, Y-m-d). An
     * unknown period means All Time; an invalid custom range falls back to
     * All Time with an error message, never to a guessed range.
     *
     * @return array{key: string, label: string, from: ?CarbonImmutable, to: ?CarbonImmutable, fromInput: string, toInput: string, error: ?string}
     */
    public function resolvePeriod(Request $objRequest): array
    {
        $strKey = (string) $objRequest->query('period', 'all');
        $strKey = array_key_exists($strKey, self::PERIODS) ? $strKey : 'all';
        $objNow = CarbonImmutable::now();
        $strFromInput = (string) $objRequest->query('from', '');
        $strToInput = (string) $objRequest->query('to', '');
        $arrPeriod = ['key' => $strKey, 'label' => self::PERIODS[$strKey], 'from' => null, 'to' => null, 'fromInput' => $strFromInput, 'toInput' => $strToInput, 'error' => null];

        switch ($strKey) {
            case 'this_month':
                [$arrPeriod['from'], $arrPeriod['to']] = [$objNow->startOfMonth(), $objNow->endOfMonth()];
                break;
            case 'last_month':
                $objLastMonth = $objNow->subMonthNoOverflow();
                [$arrPeriod['from'], $arrPeriod['to']] = [$objLastMonth->startOfMonth(), $objLastMonth->endOfMonth()];
                break;
            case 'this_year':
                [$arrPeriod['from'], $arrPeriod['to']] = [$objNow->startOfYear(), $objNow->endOfYear()];
                break;
            case 'custom':
                // Until both dates are given nothing is filtered (All Time);
                // an invalid range says so instead of guessing one.
                $objFrom = $this->_parseDate($strFromInput);
                $objTo = $this->_parseDate($strToInput);
                $blnHasNoDates = $strFromInput === '' && $strToInput === '';
                $blnIsValidRange = $objFrom !== null && $objTo !== null && $objFrom->lessThanOrEqualTo($objTo);
                $arrPeriod['label'] = self::PERIODS['all'];

                if ($blnIsValidRange) {
                    [$arrPeriod['from'], $arrPeriod['to']] = [$objFrom->startOfDay(), $objTo->endOfDay()];
                    $arrPeriod['label'] = $objFrom->format('M j, Y').' – '.$objTo->format('M j, Y');
                } elseif (! $blnHasNoDates) {
                    $arrPeriod['error'] = 'Choose a valid start and end date (the start cannot be after the end). Showing all time instead.';
                }
                break;
            default:
                break;
        } // end switch period

        return $arrPeriod;
    }

    /**
     * Applies the period, and the optional listing type and listing.
     *
     * @param  array{from: ?CarbonImmutable, to: ?CarbonImmutable}  $arrPeriod
     */
    public function filtered(Builder $objBase, array $arrPeriod, string $strType = 'all', ?Listing $objListing = null): Builder
    {
        $objQuery = clone $objBase;

        if ($arrPeriod['from'] !== null && $arrPeriod['to'] !== null) {
            $objQuery->whereBetween('fbk_created_at', [$arrPeriod['from']->toDateTimeString(), $arrPeriod['to']->toDateTimeString()]);
        }

        if ($strType === 'destinations') {
            $objQuery->forDestinations();
        } elseif ($strType === 'establishments') {
            $objQuery->forEstablishments();
        }

        if ($objListing !== null) {
            $objQuery->where('lst_id', $objListing->lst_id);
        }

        return $objQuery;
    }

    /**
     * Rows per processing status; only 'analyzed' feeds the sentiment numbers.
     *
     * @return array{pending: int, analyzed: int, failed: int, rejected: int, total: int}
     */
    public function statusCounts(Builder $objQuery): array
    {
        $arrCounts = (clone $objQuery)->toBase()
            ->selectRaw('fbk_status, COUNT(*) AS status_count')
            ->groupBy('fbk_status')
            ->pluck('status_count', 'fbk_status');
        $arrResult = [];

        foreach (FeedbackAnalysisStatus::cases() as $objStatus) {
            $arrResult[$objStatus->value] = (int) ($arrCounts[$objStatus->value] ?? 0);
        }

        $arrResult['total'] = array_sum($arrResult);

        return $arrResult;
    }

    /**
     * Positive / neutral / negative counts, shares, and the average score
     * of analyzed feedback (null when there is none).
     *
     * @return array{positive: int, neutral: int, negative: int, analyzed: int, positive_pct: int, neutral_pct: int, negative_pct: int, average_score: ?float}
     */
    public function sentimentSummary(Builder $objQuery): array
    {
        $objRow = (clone $objQuery)->analyzed()->toBase()
            ->selectRaw(
                'COUNT(*) AS analyzed_count, '
                .'SUM(CASE WHEN fbk_sentiment = ? THEN 1 ELSE 0 END) AS positive_count, '
                .'SUM(CASE WHEN fbk_sentiment = ? THEN 1 ELSE 0 END) AS neutral_count, '
                .'SUM(CASE WHEN fbk_sentiment = ? THEN 1 ELSE 0 END) AS negative_count, '
                .'AVG(fbk_sentiment_score) AS average_score',
                [SentimentClassification::Positive->value, SentimentClassification::Neutral->value, SentimentClassification::Negative->value]
            )
            ->first();
        $intAnalyzed = (int) ($objRow->analyzed_count ?? 0);

        return $this->_sentimentShape(
            (int) ($objRow->positive_count ?? 0),
            (int) ($objRow->neutral_count ?? 0),
            (int) ($objRow->negative_count ?? 0),
            $intAnalyzed > 0 && $objRow->average_score !== null ? (float) $objRow->average_score : null
        );
    }

    /**
     * Monthly sentiment counts for up to the last 12 months of the period
     * (ending at the period end, or this month). A month with no analyzed
     * feedback has total 0 and is shown as "No feedback", never as zero
     * satisfaction.
     *
     * @param  array{from: ?CarbonImmutable, to: ?CarbonImmutable}  $arrPeriod
     * @return array<int, array{month: string, label: string, total: int, positive: int, neutral: int, negative: int, positive_pct: int}>
     */
    public function monthlyTrend(Builder $objQuery, array $arrPeriod): array
    {
        $strMonthSql = $this->_monthExpression();
        $colRows = (clone $objQuery)->analyzed()->toBase()
            ->selectRaw(
                "{$strMonthSql} AS month_key, COUNT(*) AS total_count, "
                .'SUM(CASE WHEN fbk_sentiment = ? THEN 1 ELSE 0 END) AS positive_count, '
                .'SUM(CASE WHEN fbk_sentiment = ? THEN 1 ELSE 0 END) AS neutral_count, '
                .'SUM(CASE WHEN fbk_sentiment = ? THEN 1 ELSE 0 END) AS negative_count',
                [SentimentClassification::Positive->value, SentimentClassification::Neutral->value, SentimentClassification::Negative->value]
            )
            ->groupByRaw($strMonthSql)
            ->get()
            ->keyBy('month_key');

        $objCurrentMonth = CarbonImmutable::now()->startOfMonth();
        $objEnd = $arrPeriod['to'] !== null ? $arrPeriod['to']->startOfMonth() : $objCurrentMonth;
        $objEnd = $objEnd->greaterThan($objCurrentMonth) ? $objCurrentMonth : $objEnd;
        $objStart = $objEnd->subMonthsNoOverflow(self::TREND_MONTHS - 1);

        if ($arrPeriod['from'] !== null && $arrPeriod['from']->startOfMonth()->greaterThan($objStart)) {
            $objStart = $arrPeriod['from']->startOfMonth();
        }

        $arrTrend = [];

        // Summary comment: one entry per calendar month, oldest first.
        for ($objMonth = $objStart; $objMonth->lessThanOrEqualTo($objEnd); $objMonth = $objMonth->addMonthNoOverflow()) {
            $objRow = $colRows->get($objMonth->format('Y-m'));
            $intTotal = (int) ($objRow->total_count ?? 0);
            $intPositive = (int) ($objRow->positive_count ?? 0);

            $arrTrend[] = [
                'month' => $objMonth->format('Y-m'),
                'label' => $objMonth->format('M Y'),
                'total' => $intTotal,
                'positive' => $intPositive,
                'neutral' => (int) ($objRow->neutral_count ?? 0),
                'negative' => (int) ($objRow->negative_count ?? 0),
                'positive_pct' => $intTotal > 0 ? (int) round($intPositive / $intTotal * 100) : 0,
            ];
        } // end for each month

        return $arrTrend;
    }

    /**
     * Common Concerns for the whole scope: issue categories ranked by the
     * number of analyzed (negative) feedback rows that mention them.
     *
     * @return array<int, array{category: string, count: int}>
     */
    public function commonConcerns(Builder $objQuery): array
    {
        $arrCounts = $this->_issueCountsQuery($objQuery)
            ->selectRaw('tbl_feedback_issues.fbi_issue_category AS category, COUNT(*) AS issue_count')
            ->groupBy('tbl_feedback_issues.fbi_issue_category')
            ->pluck('issue_count', 'category')
            ->map(fn ($mixCount) => (int) $mixCount)
            ->all();

        return $this->objRecommendations->rankConcerns($arrCounts);
    }

    /**
     * One summary row per listing with feedback in scope: type, category,
     * municipality, current public status, status counts, sentiment, average
     * score, top concern, and suggested improvements (only when negative
     * feedback dominates with the minimum sample). Most analyzed first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listingSummaries(Builder $objQuery): Collection
    {
        $colRows = (clone $objQuery)->toBase()
            ->selectRaw(
                'lst_id, COUNT(*) AS feedback_count, '
                .'SUM(CASE WHEN fbk_status = ? THEN 1 ELSE 0 END) AS analyzed_count, '
                .'SUM(CASE WHEN fbk_status = ? AND fbk_sentiment = ? THEN 1 ELSE 0 END) AS positive_count, '
                .'SUM(CASE WHEN fbk_status = ? AND fbk_sentiment = ? THEN 1 ELSE 0 END) AS neutral_count, '
                .'SUM(CASE WHEN fbk_status = ? AND fbk_sentiment = ? THEN 1 ELSE 0 END) AS negative_count, '
                .'AVG(CASE WHEN fbk_status = ? THEN fbk_sentiment_score END) AS average_score',
                [
                    FeedbackAnalysisStatus::Analyzed->value,
                    FeedbackAnalysisStatus::Analyzed->value, SentimentClassification::Positive->value,
                    FeedbackAnalysisStatus::Analyzed->value, SentimentClassification::Neutral->value,
                    FeedbackAnalysisStatus::Analyzed->value, SentimentClassification::Negative->value,
                    FeedbackAnalysisStatus::Analyzed->value,
                ]
            )
            ->groupBy('lst_id')
            ->get();

        if ($colRows->isEmpty()) {
            return collect();
        }

        $colListings = Listing::query()
            ->with('categoryRecord')
            ->whereIn('lst_id', $colRows->pluck('lst_id'))
            ->get(['lst_id', 'lst_slug', 'lst_name', 'lst_category', 'cat_id', 'lst_type', 'lst_municipality', 'lst_status'])
            ->keyBy('lst_id');
        $arrIssueCounts = $this->_issueCountsByListing($objQuery);

        return $colRows
            ->map(function (object $objRow) use ($colListings, $arrIssueCounts) {
                $objListing = $colListings->get($objRow->lst_id);
                $intAnalyzed = (int) $objRow->analyzed_count;
                $arrSentiment = $this->_sentimentShape(
                    (int) $objRow->positive_count,
                    (int) $objRow->neutral_count,
                    (int) $objRow->negative_count,
                    $intAnalyzed > 0 && $objRow->average_score !== null ? (float) $objRow->average_score : null
                );

                return array_merge([
                    'listing' => $objListing,
                    'name' => $objListing?->lst_name ?? 'Unknown listing',
                    'type' => $objListing?->isDestinationOnly() ? 'Destination' : 'Establishment',
                    'category' => $objListing?->categoryName() ?? '',
                    'municipality' => (string) ($objListing?->lst_municipality ?? ''),
                    'listing_status' => $objListing !== null ? $this->listingStatusLabel($objListing) : '',
                    'feedback_count' => (int) $objRow->feedback_count,
                    'sentiment' => $arrSentiment,
                ], $this->_recommendationFields($arrSentiment, $arrIssueCounts[$objRow->lst_id] ?? []));
            })
            ->sortBy([['sentiment.analyzed', 'desc'], ['name', 'asc']])
            ->values();
    }

    /**
     * Everything the single-listing report shows (the PTO/LGU drill-down
     * and the establishment owner's own analytics).
     *
     * @param  array{from: ?CarbonImmutable, to: ?CarbonImmutable}  $arrPeriod
     * @return array<string, mixed>
     */
    public function listingReport(Builder $objQuery, array $arrPeriod): array
    {
        $arrSentiment = $this->sentimentSummary($objQuery);
        $arrIssueCounts = [];

        foreach ($this->commonConcerns($objQuery) as $arrConcern) {
            $arrIssueCounts[$arrConcern['category']] = $arrConcern['count'];
        }

        return array_merge([
            'status' => $this->statusCounts($objQuery),
            'sentiment' => $arrSentiment,
            'trend' => $this->monthlyTrend($objQuery, $arrPeriod),
        ], $this->_recommendationFields($arrSentiment, $arrIssueCounts));
    }

    /**
     * Feedback entries, newest first, for the authorized feedback lists.
     * Optional filters: status and sentiment (validated against the enums).
     */
    public function feedbackEntries(Builder $objQuery, ?string $strStatus, ?string $strSentiment, int $intPerPage = 12): LengthAwarePaginator
    {
        $objEntries = (clone $objQuery)
            ->with(['listing:lst_id,lst_slug,lst_name,lst_category,cat_id,lst_municipality', 'listing.categoryRecord', 'issues'])
            ->orderByDesc('fbk_created_at')
            ->orderByDesc('fbk_id');

        if (FeedbackAnalysisStatus::tryFrom((string) $strStatus) !== null) {
            $objEntries->where('fbk_status', $strStatus);
        }

        if (SentimentClassification::tryFrom((string) $strSentiment) !== null) {
            $objEntries->analyzed()->where('fbk_sentiment', $strSentiment);
        }

        return $objEntries->paginate($intPerPage)->withQueryString();
    }

    /**
     * Dashboard KPI cards with real feedback numbers, replacing the
     * matching sample cards ('Tourist Feedback', 'Overall Sentiment') in a
     * dashboard's card list; other cards are left untouched.
     *
     * @param  array<int, array{label: string, value: string, delta: string, tone: string}>  $arrCards
     * @return array<int, array{label: string, value: string, delta: string, tone: string}>
     */
    public function withFeedbackCards(array $arrCards, Builder $objQuery): array
    {
        $arrStatus = $this->statusCounts($objQuery);
        $arrSentiment = $this->sentimentSummary($objQuery);
        $intAwaiting = $arrStatus['pending'] + $arrStatus['failed'];
        $arrReal = [
            'Tourist Feedback' => [
                'label' => 'Tourist Feedback',
                'value' => number_format($arrSentiment['analyzed']),
                'delta' => $intAwaiting > 0 ? number_format($intAwaiting).' awaiting analysis' : 'Analyzed, all time',
                'tone' => 'neutral',
            ],
            'Overall Sentiment' => [
                'label' => 'Overall Sentiment',
                'value' => $arrSentiment['analyzed'] > 0 ? $arrSentiment['positive_pct'].'% Positive' : '—',
                'delta' => $arrSentiment['analyzed'] > 0 ? number_format($arrSentiment['analyzed']).' analyzed' : 'No analyzed feedback yet',
                'tone' => 'neutral',
            ],
        ];

        return array_map(fn (array $arrCard) => $arrReal[$arrCard['label']] ?? $arrCard, $arrCards);
    }

    /**
     * The listing's current public status in plain words.
     */
    public function listingStatusLabel(Listing $objListing): string
    {
        if ($objListing->isPubliclyVisible()) {
            return 'Published';
        }

        return match ($objListing->lst_status) {
            'DRAFT' => 'Draft',
            'FOR_LGU_REVIEW', 'FOR_PTO_REVIEW' => 'Pending review',
            Listing::STATUS_FOR_CORRECTION => 'For correction',
            'UNPUBLISHED' => 'Unpublished',
            'Suspended' => 'Suspended',
            'Archived' => 'Archived',
            default => (string) $objListing->lst_status,
        };
    }

    /**
     * Counts, shares (whole percent), and average score in one shape.
     *
     * @return array{positive: int, neutral: int, negative: int, analyzed: int, positive_pct: int, neutral_pct: int, negative_pct: int, average_score: ?float}
     */
    private function _sentimentShape(int $intPositive, int $intNeutral, int $intNegative, ?float $fltAverage): array
    {
        $intAnalyzed = $intPositive + $intNeutral + $intNegative;
        $fnShare = fn (int $intCount) => $intAnalyzed > 0 ? (int) round($intCount / $intAnalyzed * 100) : 0;

        return [
            'positive' => $intPositive,
            'neutral' => $intNeutral,
            'negative' => $intNegative,
            'analyzed' => $intAnalyzed,
            'positive_pct' => $fnShare($intPositive),
            'neutral_pct' => $fnShare($intNeutral),
            'negative_pct' => $fnShare($intNegative),
            'average_score' => $fltAverage !== null ? round($fltAverage, 4) : null,
        ];
    }

    /**
     * Concerns and the predefined recommendations for one listing, with the
     * reason improvements are or are not shown.
     *
     * @param  array{positive: int, neutral: int, negative: int, analyzed: int}  $arrSentiment
     * @param  array<string, int>  $arrIssueCounts
     * @return array{concerns: array<int, array{category: string, count: int}>, top_concern: ?string, improvements: array<int, string>, has_minimum_sample: bool, is_negative_dominant: bool}
     */
    private function _recommendationFields(array $arrSentiment, array $arrIssueCounts): array
    {
        $arrSummary = $this->objRecommendations->summarize($arrSentiment['positive'], $arrSentiment['neutral'], $arrSentiment['negative'], $arrIssueCounts);

        return [
            'concerns' => $arrSummary['common_concerns'],
            'top_concern' => $arrSummary['common_concerns'][0]['category'] ?? null,
            'improvements' => $arrSummary['suggested_improvements'],
            'has_minimum_sample' => $arrSentiment['analyzed'] >= (int) config('tourist_feedback.minimum_sample'),
            'is_negative_dominant' => $arrSummary['is_negative_dominant'],
        ];
    }

    /**
     * Issue rows of the analyzed feedback in scope, joined to their feedback.
     */
    private function _issueCountsQuery(Builder $objQuery): QueryBuilder
    {
        $objAnalyzedIds = (clone $objQuery)->analyzed()->select('fbk_id');

        return DB::table('tbl_feedback_issues')
            ->join('tbl_feedbacks', 'tbl_feedbacks.fbk_id', '=', 'tbl_feedback_issues.fbk_id')
            ->whereIn('tbl_feedback_issues.fbk_id', $objAnalyzedIds);
    }

    /**
     * Issue counts per listing: lst_id => [category => feedback rows].
     *
     * @return array<int, array<string, int>>
     */
    private function _issueCountsByListing(Builder $objQuery): array
    {
        $colRows = $this->_issueCountsQuery($objQuery)
            ->selectRaw('tbl_feedbacks.lst_id AS lst_id, tbl_feedback_issues.fbi_issue_category AS category, COUNT(*) AS issue_count')
            ->groupBy('tbl_feedbacks.lst_id', 'tbl_feedback_issues.fbi_issue_category')
            ->get();
        $arrCounts = [];

        foreach ($colRows as $objRow) {
            $arrCounts[(int) $objRow->lst_id][$objRow->category] = (int) $objRow->issue_count;
        }

        return $arrCounts;
    }

    /**
     * SQL for the year-month of the submission time ('YYYY-MM'); the stored
     * timestamps are already in the application timezone.
     */
    private function _monthExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR(fbk_created_at, 'YYYY-MM')",
            default => "STRFTIME('%Y-%m', fbk_created_at)",
        };
    }

    /**
     * A strict Y-m-d date, or null.
     */
    private function _parseDate(string $strDate): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $strDate) !== 1) {
            return null;
        }

        [$intYear, $intMonth, $intDay] = array_map('intval', explode('-', $strDate));

        return checkdate($intMonth, $intDay, $intYear) ? CarbonImmutable::create($intYear, $intMonth, $intDay) : null;
    }
}
