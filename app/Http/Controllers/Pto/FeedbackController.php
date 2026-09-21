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
    public function index(Request $request): View
    {
        return $this->renderFeedback($request, 'index');
    }

    /**
     * Experience Analytics: sentiment breakdown and trends.
     *
     * Kept as its own route/method so the pre-existing URL still resolves
     * directly (no redirect) — it renders the same merged Tourist Feedback
     * page with the Experience Analytics tab pre-selected.
     */
    public function analytics(Request $request): View
    {
        return $this->renderFeedback($request, 'analytics');
    }

    /**
     * Renders the merged Tourist Feedback page (All Feedback / Experience
     * Analytics tabs) with the requested tab pre-selected.
     */
    private function renderFeedback(Request $request, string $activeTab): View
    {
        $feedback = collect(PtoMockData::feedback());

        return $this->renderPto($request, 'pto.feedback.index', 'feedback', 'Tourist Feedback', [
            'activeTab' => $activeTab,
            'feedback' => $feedback->all(),
            'sentiment' => PtoMockData::sentimentBreakdown(),
            'sentimentTrend' => PtoMockData::sentimentTrend(),
            'byDestination' => $feedback->whereIn('subject', collect(TourismCatalog::featuredDestinations())->pluck('name'))
                ->groupBy('subject')->map->count()->sortDesc()->take(5),
            'byEstablishment' => $feedback->whereNotIn('subject', collect(TourismCatalog::featuredDestinations())->pluck('name'))
                ->groupBy('subject')->map->count()->sortDesc()->take(5),
        ]);
    }
}
