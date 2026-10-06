<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Lists province-wide tourist feedback and its sentiment
 * analytics for the PTO role.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Support\PtoMockData;
use App\Support\TourismCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends PtoController
{
    /**
     * All Feedback: every tourist feedback entry, searchable and filterable.
     */
    public function index(Request $objRequest): View
    {
        return $this->renderFeedback($objRequest, 'index');
    }

    /**
     * Experience Analytics: sentiment breakdown and trends.
     *
     * Kept as its own route/method so the pre-existing URL still resolves
     * directly (no redirect) — it renders the same merged Tourist Feedback
     * page with the Experience Analytics tab pre-selected.
     */
    public function analytics(Request $objRequest): View
    {
        return $this->renderFeedback($objRequest, 'analytics');
    }

    /**
     * Renders the merged Tourist Feedback page (Reviews / Sentiment
     * Analytics tabs) with the requested tab pre-selected. `municipality`
     * and `category` are derived by matching each entry's free-text
     * `subject` against TourismCatalog — the feedback itself has no rating
     * field yet (only sentiment + polarity), so a Rating filter isn't
     * offered; that needs a real tblfeedbacks table with a rating column
     * before it can be built without fabricating data.
     */
    private function renderFeedback(Request $objRequest, string $strActiveTab): View
    {
        $objListingsBySubject = collect(TourismCatalog::listings())->keyBy('name');

        $objFeedback = collect(PtoMockData::feedback())->map(function (array $arrEntry) use ($objListingsBySubject) {
            $objListing = $objListingsBySubject->get($arrEntry['subject']);

            return [
                ...$arrEntry,
                'municipality' => $objListing['municipality'] ?? null,
                'category' => $objListing ? TourismCatalog::categoryLabel($objListing['category']) : null,
            ];
        });

        $objSentimentByDestination = $objFeedback->groupBy('subject')->map(fn ($objRows) => [
            'positive' => $objRows->where('sentiment', 'Positive')->count(),
            'neutral' => $objRows->where('sentiment', 'Neutral')->count(),
            'negative' => $objRows->where('sentiment', 'Negative')->count(),
        ])->filter(fn ($arrRow) => array_sum($arrRow) > 0);

        // "Common concerns" / "suggested improvements": the text of every
        // Negative/Neutral entry, since that's exactly where a concern or a
        // room-for-improvement remark lives in this lexicon-scored feedback.
        $objConcerns = $objFeedback->whereIn('sentiment', ['Negative', 'Neutral'])
            ->sortBy('polarity')
            ->take(5)
            ->values();

        return $this->renderPto($objRequest, 'pto.feedback.index', 'feedback', 'Tourist Feedback', [
            'activeTab' => $strActiveTab,
            'feedback' => $objFeedback->all(),
            'municipalities' => $objFeedback->pluck('municipality')->filter()->unique()->sort()->values(),
            'categories' => $objFeedback->pluck('category')->filter()->unique()->sort()->values(),
            'sentiment' => PtoMockData::sentimentBreakdown(),
            'sentimentTrend' => PtoMockData::sentimentTrend(),
            'sentimentByDestination' => $objSentimentByDestination,
            'concerns' => $objConcerns,
            'byDestination' => $objFeedback->whereIn('subject', collect(TourismCatalog::featuredDestinations())->pluck('name'))
                ->groupBy('subject')->map->count()->sortDesc()->take(5),
            'byEstablishment' => $objFeedback->whereNotIn('subject', collect(TourismCatalog::featuredDestinations())->pluck('name'))
                ->groupBy('subject')->map->count()->sortDesc()->take(5),
        ]);
    }
}
