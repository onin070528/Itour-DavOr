<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Lists tourist feedback and its sentiment analytics, scoped to
 * the LGU account's assigned municipality.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Support\LguMockData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends LguController
{
    /**
     * All Feedback: entries for destinations/establishments in this municipality only.
     */
    public function index(Request $objRequest): View
    {
        $objMunicipality = $objRequest->user()->usr_organization_subtitle;

        return $this->renderLgu($objRequest, 'lgu.feedback.index', 'feedback.index', 'Tourist Feedback', [
            'municipality' => $objMunicipality,
            'feedback' => LguMockData::feedback($objMunicipality),
        ]);
    }

    /**
     * Experience Analytics: municipality-level sentiment breakdown and trends.
     */
    public function analytics(Request $objRequest): View
    {
        $objMunicipality = $objRequest->user()->usr_organization_subtitle;
        $objFeedback = collect(LguMockData::feedback($objMunicipality));
        $objDestinationNames = collect(LguMockData::destinations($objMunicipality))->pluck('name');

        return $this->renderLgu($objRequest, 'lgu.feedback.analytics', 'feedback.analytics', 'Experience Analytics', [
            'municipality' => $objMunicipality,
            'sentiment' => LguMockData::sentimentBreakdown($objMunicipality),
            'sentimentTrend' => LguMockData::sentimentTrend($objMunicipality),
            'byDestination' => $objFeedback->whereIn('subject', $objDestinationNames)->groupBy('subject')->map->count()->sortDesc()->take(5),
            'byEstablishment' => $objFeedback->whereNotIn('subject', $objDestinationNames)->groupBy('subject')->map->count()->sortDesc()->take(5),
        ]);
    }
}
