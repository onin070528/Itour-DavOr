<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Municipality-scoped arrival log, visitation statistics, and
 * destination performance views for the LGU role.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Support\LguMockData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringController extends LguController
{
    /**
     * Tourist Arrivals: a searchable, filterable log scoped to this municipality.
     */
    public function arrivals(Request $request): View
    {
        $municipality = $request->user()->organization_subtitle;

        return $this->renderLgu($request, 'lgu.monitoring.arrivals', 'monitoring.arrivals', 'Tourist Arrivals', [
            'municipality' => $municipality,
            'arrivals' => LguMockData::arrivals($municipality),
            'establishments' => LguMockData::establishments($municipality),
        ]);
    }

    /**
     * Visitation Statistics: municipality-level trend and establishment comparison.
     */
    public function statistics(Request $request): View
    {
        $municipality = $request->user()->organization_subtitle;
        $arrivals = collect(LguMockData::arrivals($municipality));

        return $this->renderLgu($request, 'lgu.monitoring.statistics', 'monitoring.statistics', 'Visitation Statistics', [
            'municipality' => $municipality,
            'arrivalTrend' => LguMockData::arrivalTrend($municipality),
            'summary' => LguMockData::dashboardSummary($municipality),
            'classificationBreakdown' => $arrivals->groupBy('classification')->map->sum('visitors'),
            'establishmentComparison' => $arrivals->groupBy('establishment')->map->sum('visitors')->sortDesc(),
        ]);
    }

    /**
     * Destination Performance: destinations in this municipality, ranked by visits.
     */
    public function destinations(Request $request): View
    {
        $municipality = $request->user()->organization_subtitle;

        return $this->renderLgu($request, 'lgu.monitoring.destinations', 'monitoring.destinations', 'Destination Performance', [
            'municipality' => $municipality,
            'performance' => LguMockData::destinationPerformance($municipality),
        ]);
    }
}
