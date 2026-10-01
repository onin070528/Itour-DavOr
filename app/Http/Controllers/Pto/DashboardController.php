<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Renders the PTO Tourism Monitoring Dashboard — KPIs, arrival
 * trend, LGU visitation statistics, reporting status, and visitor
 * classification, aggregated from real Verified MonthlyArrivalReport /
 * MunicipalReport data via App\Support\TourismAnalytics. PTO watches and
 * validates this data; it never re-encodes it.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Models\Listing;
use App\Models\Municipality;
use App\Support\TourismAnalytics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends PtoController
{
    /**
     * The PTO landing page: the province-wide Tourism Monitoring Dashboard.
     */
    public function index(Request $request): View
    {
        $filters = TourismAnalytics::resolveFilters($request);
        $comparison = TourismAnalytics::periodComparison($filters);

        $municipalities = Municipality::query()->orderBy('name')->get();
        $establishments = Listing::query()
            ->where('category', '!=', 'destinations')
            ->when($filters['municipalityId'], fn ($q, $id) => $q->where('municipality_id', $id))
            ->orderBy('name')
            ->get();

        return $this->renderPto($request, 'pto.dashboard', 'dashboard', 'Tourism Monitoring Dashboard', [
            'filters' => $filters,
            'yearOptions' => TourismAnalytics::yearOptions(),
            'municipalities' => $municipalities,
            'establishments' => $establishments,
            'kpis' => TourismAnalytics::kpis($filters, $comparison),
            'arrivalTrend' => TourismAnalytics::arrivalTrend($filters),
            'municipalityComparison' => TourismAnalytics::municipalityComparison($filters),
            'reportingStatus' => TourismAnalytics::reportingStatus($filters),
            'classification' => TourismAnalytics::classificationBreakdown($filters),
            'recentActivity' => TourismAnalytics::recentActivity($filters['municipalityId']),
        ]);
    }
}
