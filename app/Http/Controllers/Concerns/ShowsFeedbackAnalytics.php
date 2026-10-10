<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Shared data for the role feedback pages (Objective 4, Phase 5):
 * the feedback list, the analytics overview, and one listing's report. The
 * scope always comes from the signed-in user (FeedbackAnalyticsService::
 * scopedQuery()), never from request input.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Concerns;

use App\Enums\FeedbackAnalysisStatus;
use App\Enums\SentimentClassification;
use App\Models\Listing;
use App\Services\FeedbackAnalyticsService;
use Illuminate\Http\Request;

trait ShowsFeedbackAnalytics
{
    /**
     * The feedback list: status counts and paginated entries, filtered by
     * period, listing type, status, and sentiment.
     *
     * @return array<string, mixed>
     */
    protected function feedbackListData(Request $objRequest, FeedbackAnalyticsService $objAnalytics, ?Listing $objListing = null): array
    {
        $arrFilters = $this->_feedbackFilters($objRequest, $objAnalytics);
        $objQuery = $objAnalytics->filtered($objAnalytics->scopedQuery($objRequest->user()), $arrFilters['period'], $arrFilters['type'], $objListing);

        return array_merge($arrFilters, [
            'statusCounts' => $objAnalytics->statusCounts($objQuery),
            'entries' => $objAnalytics->feedbackEntries($objQuery, $arrFilters['status'], $arrFilters['sentimentFilter']),
        ]);
    }

    /**
     * The analytics overview for the user's whole scope.
     *
     * @return array<string, mixed>
     */
    protected function feedbackOverviewData(Request $objRequest, FeedbackAnalyticsService $objAnalytics): array
    {
        $arrFilters = $this->_feedbackFilters($objRequest, $objAnalytics);
        $objQuery = $objAnalytics->filtered($objAnalytics->scopedQuery($objRequest->user()), $arrFilters['period'], $arrFilters['type']);

        return array_merge($arrFilters, [
            'statusCounts' => $objAnalytics->statusCounts($objQuery),
            'sentiment' => $objAnalytics->sentimentSummary($objQuery),
            'trend' => $objAnalytics->monthlyTrend($objQuery, $arrFilters['period']),
            'concerns' => $objAnalytics->commonConcerns($objQuery),
            'listingSummaries' => $objAnalytics->listingSummaries($objQuery),
        ]);
    }

    /**
     * One listing's report and its entries. The caller must already have
     * authorized the listing (ListingPolicy::viewFeedback()).
     *
     * @return array<string, mixed>
     */
    protected function feedbackListingData(Request $objRequest, FeedbackAnalyticsService $objAnalytics, Listing $objListing): array
    {
        $arrFilters = $this->_feedbackFilters($objRequest, $objAnalytics);
        $objQuery = $objAnalytics->filtered($objAnalytics->scopedQuery($objRequest->user()), $arrFilters['period'], 'all', $objListing);

        return array_merge($arrFilters, [
            'listing' => $objListing,
            'listingStatus' => $objAnalytics->listingStatusLabel($objListing),
            'report' => $objAnalytics->listingReport($objQuery, $arrFilters['period']),
            'entries' => $objAnalytics->feedbackEntries($objQuery, $arrFilters['status'], $arrFilters['sentimentFilter']),
        ]);
    }

    /**
     * Filter values, each checked against its allowed list.
     *
     * @return array{period: array<string, mixed>, type: string, status: ?string, sentimentFilter: ?string}
     */
    private function _feedbackFilters(Request $objRequest, FeedbackAnalyticsService $objAnalytics): array
    {
        $strType = (string) $objRequest->query('type', 'all');
        $strStatus = (string) $objRequest->query('status', '');
        $strSentiment = (string) $objRequest->query('sentiment', '');

        return [
            'period' => $objAnalytics->resolvePeriod($objRequest),
            'type' => array_key_exists($strType, FeedbackAnalyticsService::LISTING_TYPES) ? $strType : 'all',
            'status' => FeedbackAnalysisStatus::tryFrom($strStatus)?->value,
            'sentimentFilter' => SentimentClassification::tryFrom($strSentiment)?->value,
        ];
    }
}
