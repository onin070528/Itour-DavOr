<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Province-wide tourist feedback and Tourist Experience Analytics
 * for the PTO role (Objective 4): the feedback list, the analytics
 * overview, and one destination's or establishment's report.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Http\Controllers\Concerns\ShowsFeedbackAnalytics;
use App\Models\Listing;
use App\Services\FeedbackAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FeedbackController extends PtoController
{
    use ShowsFeedbackAnalytics;

    /**
     * Reviews tab: every feedback entry in the province, with its
     * processing status, original text, and translation.
     */
    public function index(Request $objRequest, FeedbackAnalyticsService $objAnalytics): View
    {
        return $this->renderPto($objRequest, 'pto.feedback.index', 'feedback', 'Tourist Feedback', array_merge(
            ['activeTab' => 'index'],
            $this->feedbackListData($objRequest, $objAnalytics)
        ));
    }

    /**
     * Sentiment Analytics tab: the province-wide overview. Kept as its own
     * route so the existing URL still opens this tab directly.
     */
    public function analytics(Request $objRequest, FeedbackAnalyticsService $objAnalytics): View
    {
        return $this->renderPto($objRequest, 'pto.feedback.index', 'feedback', 'Tourist Feedback', array_merge(
            ['activeTab' => 'analytics'],
            $this->feedbackOverviewData($objRequest, $objAnalytics)
        ));
    }

    /**
     * One listing's report (drill-down from the analytics table).
     */
    public function listing(Request $objRequest, Listing $listing, FeedbackAnalyticsService $objAnalytics): View
    {
        Gate::authorize('viewFeedback', $listing);

        return $this->renderPto($objRequest, 'pto.feedback.listing', 'feedback', 'Tourist Feedback', $this->feedbackListingData($objRequest, $objAnalytics, $listing));
    }
}
