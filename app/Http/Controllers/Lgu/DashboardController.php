<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Renders the LGU dashboard, a municipality-scoped snapshot of
 * tourism activity (arrivals, destinations, establishments, sentiment).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Services\FeedbackAnalyticsService;
use App\Support\LguMockData;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class DashboardController extends LguController
{
    /**
     * The LGU landing page: a snapshot of tourism activity in the user's
     * assigned municipality.
     */
    public function index(Request $objRequest, FeedbackAnalyticsService $objAnalytics): View
    {
        $objMunicipality = $objRequest->user()->usr_organization_subtitle;
        // Feedback numbers are real (Objective 4), scoped to this LGU's mun_id.
        $objFeedback = $objAnalytics->scopedQuery($objRequest->user());

        return $this->renderLgu($objRequest, 'lgu.dashboard', 'dashboard', 'Dashboard', [
            'summary' => $objAnalytics->withFeedbackCards(LguMockData::dashboardSummary($objMunicipality), $objFeedback),
            'arrivalTrend' => LguMockData::arrivalTrend($objMunicipality),
            'topDestinations' => array_slice(LguMockData::destinationPerformance($objMunicipality), 0, 5),
            'establishmentCategories' => LguMockData::establishmentCategories($objMunicipality),
            'establishmentCount' => count(LguMockData::establishments($objMunicipality)),
            'sentiment' => Arr::only($objAnalytics->sentimentSummary($objFeedback), ['positive', 'neutral', 'negative']),
            'recentActivity' => array_slice(LguMockData::recentActivity($objMunicipality), 0, 6),
        ]);
    }
}
