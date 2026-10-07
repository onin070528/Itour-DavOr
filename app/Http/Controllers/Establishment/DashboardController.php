<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Renders the Establishment role's landing dashboard — performance
 * summary, arrival trend, classification breakdown, and recent activity.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Establishment;

use App\Support\EstablishmentMockData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends EstablishmentController
{
    /**
     * The Establishment landing page: how this establishment is performing.
     */
    public function index(Request $objRequest): View
    {
        $strName = $objRequest->user()->usr_organization_name;

        return $this->renderEstablishment($objRequest, 'establishment.dashboard', 'dashboard', 'Dashboard', [
            'summary' => EstablishmentMockData::dashboardSummary($strName),
            'arrivalTrend' => EstablishmentMockData::arrivalTrend($strName),
            'classificationBreakdown' => EstablishmentMockData::classificationBreakdown($strName),
            'sentiment' => EstablishmentMockData::sentimentBreakdown($strName),
            'recentActivity' => array_slice(EstablishmentMockData::recentActivity($strName), 0, 6),
        ]);
    }
}
