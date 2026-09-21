<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Renders the Establishment role's Tourism Statistics page — visitor
 * trend, classification, and gender breakdown for the account's own listing.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Establishment;

use App\Support\EstablishmentMockData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatisticsController extends EstablishmentController
{
    /**
     * Tourism Statistics: this establishment's own visitor trend and classification.
     */
    public function index(Request $request): View
    {
        $name = $request->user()->organization_name;

        return $this->renderEstablishment($request, 'establishment.statistics', 'statistics', 'Tourism Statistics', [
            'summary' => EstablishmentMockData::dashboardSummary($name),
            'arrivalTrend' => EstablishmentMockData::arrivalTrend($name),
            'classificationBreakdown' => EstablishmentMockData::classificationBreakdown($name),
            'genderBreakdown' => collect(EstablishmentMockData::arrivals($name))->countBy('gender'),
        ]);
    }
}
