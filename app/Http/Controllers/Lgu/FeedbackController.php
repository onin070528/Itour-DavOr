<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tourist feedback and Tourist Experience Analytics for the LGU
 * role (Objective 4), limited server-side to the account's assigned
 * municipality (mun_id) through Feedback::scopeVisibleTo() and
 * ListingPolicy::viewFeedback().
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Http\Controllers\Concerns\ShowsFeedbackAnalytics;
use App\Models\Listing;
use App\Services\FeedbackAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FeedbackController extends LguController
{
    use ShowsFeedbackAnalytics;

    /**
     * All Feedback: entries for destinations and establishments in this
     * municipality only.
     */
    public function index(Request $objRequest, FeedbackAnalyticsService $objAnalytics): View
    {
        return $this->renderLgu($objRequest, 'lgu.feedback.index', 'feedback.index', 'Tourist Feedback', array_merge(
            ['municipality' => $this->_municipalityName($objRequest)],
            $this->feedbackListData($objRequest, $objAnalytics)
        ));
    }

    /**
     * Experience Analytics: the municipality overview.
     */
    public function analytics(Request $objRequest, FeedbackAnalyticsService $objAnalytics): View
    {
        return $this->renderLgu($objRequest, 'lgu.feedback.analytics', 'feedback.analytics', 'Experience Analytics', array_merge(
            ['municipality' => $this->_municipalityName($objRequest)],
            $this->feedbackOverviewData($objRequest, $objAnalytics)
        ));
    }

    /**
     * One listing's report. A listing in another municipality is denied
     * with 403 and logged (Gate::after -> SecurityLogger::accessDenied()).
     */
    public function listing(Request $objRequest, Listing $listing, FeedbackAnalyticsService $objAnalytics): View
    {
        Gate::authorize('viewFeedback', $listing);

        return $this->renderLgu($objRequest, 'lgu.feedback.listing', 'feedback.analytics', 'Experience Analytics', $this->feedbackListingData($objRequest, $objAnalytics, $listing));
    }

    /**
     * Display name of the assigned municipality (from the mun_id record).
     */
    private function _municipalityName(Request $objRequest): string
    {
        return (string) ($objRequest->user()->municipality?->mun_name ?? $objRequest->user()->usr_organization_subtitle);
    }
}
