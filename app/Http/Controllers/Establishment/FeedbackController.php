<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tourist feedback and Tourist Experience Analytics for the
 * Establishment role (Objective 4) — only the account's own linked listing
 * (tbl_users.lst_id), enforced server-side by Feedback::scopeVisibleTo().
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Establishment;

use App\Http\Controllers\Concerns\ShowsFeedbackAnalytics;
use App\Services\FeedbackAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends EstablishmentController
{
    use ShowsFeedbackAnalytics;

    /**
     * All Feedback: tourist feedback left about this establishment. Read-only.
     */
    public function index(Request $objRequest, FeedbackAnalyticsService $objAnalytics): View
    {
        return $this->renderEstablishment($objRequest, 'establishment.feedback.index', 'feedback.index', 'Tourist Feedback', array_merge(
            ['ownListing' => $objRequest->user()->establishment],
            $this->feedbackListData($objRequest, $objAnalytics)
        ));
    }

    /**
     * Experience Analytics: this establishment's report. An account with no
     * linked listing gets an empty report (its scope matches nothing).
     */
    public function analytics(Request $objRequest, FeedbackAnalyticsService $objAnalytics): View
    {
        $objListing = $objRequest->user()->establishment;
        $arrPeriod = $objAnalytics->resolvePeriod($objRequest);
        $objQuery = $objAnalytics->filtered($objAnalytics->scopedQuery($objRequest->user()), $arrPeriod);

        return $this->renderEstablishment($objRequest, 'establishment.feedback.analytics', 'feedback.analytics', 'Experience Analytics', [
            'ownListing' => $objListing,
            'listingStatus' => $objListing !== null ? $objAnalytics->listingStatusLabel($objListing) : null,
            'period' => $arrPeriod,
            'report' => $objAnalytics->listingReport($objQuery, $arrPeriod),
        ]);
    }
}
