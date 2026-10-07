<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Renders the Establishment role's tourist Feedback list and
 * Experience Analytics (sentiment breakdown and trend).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Establishment;

use App\Support\EstablishmentMockData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends EstablishmentController
{
    /**
     * All Feedback: tourist feedback left about this establishment. Read-only.
     */
    public function index(Request $objRequest): View
    {
        $strName = $objRequest->user()->usr_organization_name;

        return $this->renderEstablishment($objRequest, 'establishment.feedback.index', 'feedback.index', 'Tourist Feedback', [
            'feedback' => EstablishmentMockData::feedback($strName),
        ]);
    }

    /**
     * Experience Analytics: sentiment breakdown and trend for this establishment.
     */
    public function analytics(Request $objRequest): View
    {
        $strName = $objRequest->user()->usr_organization_name;

        return $this->renderEstablishment($objRequest, 'establishment.feedback.analytics', 'feedback.analytics', 'Experience Analytics', [
            'sentiment' => EstablishmentMockData::sentimentBreakdown($strName),
            'sentimentTrend' => EstablishmentMockData::sentimentTrend($strName),
            'feedback' => EstablishmentMockData::feedback($strName),
        ]);
    }
}
