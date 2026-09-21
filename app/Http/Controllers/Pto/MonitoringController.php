<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Province-wide arrival log, visitation statistics, and
 * destination performance views for the PTO role.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Support\PtoMockData;
use App\Support\TourismCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringController extends PtoController
{
    /**
     * Tourist Arrivals: a searchable, filterable log of arrival records.
     */
    public function arrivals(Request $request): View
    {
        return $this->renderMonitoring($request, 'arrivals');
    }

    /**
     * Visitation Statistics: province-wide trend and municipality comparison.
     *
     * Kept as its own route/method so the pre-existing URL still resolves
     * directly (no redirect) — it renders the same merged Tourism
     * Monitoring page with the Statistics tab pre-selected.
     */
    public function statistics(Request $request): View
    {
        return $this->renderMonitoring($request, 'statistics');
    }

    /**
     * Destination Performance: destinations ranked by tourist visits.
     *
     * Kept as its own route/method for the same reason as statistics()
     * above — renders the merged page with the Destinations tab selected.
     */
    public function destinations(Request $request): View
    {
        return $this->renderMonitoring($request, 'destinations');
    }

    /**
     * Renders the merged Tourism Monitoring page (Arrivals / Statistics /
     * Destination Performance tabs) with the requested tab pre-selected.
     */
    private function renderMonitoring(Request $request, string $activeTab): View
    {
        return $this->renderPto($request, 'pto.monitoring.arrivals', 'monitoring.arrivals', 'Tourism Monitoring', [
            'activeTab' => $activeTab,
            'arrivals' => PtoMockData::arrivals(),
            'municipalities' => TourismCatalog::municipalities(),
            'establishments' => PtoMockData::establishmentDirectory(),
            'arrivalTrend' => PtoMockData::arrivalTrend(),
            'municipalityComparison' => PtoMockData::municipalityComparison(),
            'summary' => PtoMockData::dashboardSummary(),
            'performance' => PtoMockData::destinationPerformance(),
        ]);
    }
}
