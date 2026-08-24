<?php

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
        return $this->renderPto($request, 'pto.monitoring.arrivals', 'monitoring.arrivals', 'Tourist Arrivals', [
            'arrivals' => PtoMockData::arrivals(),
            'municipalities' => TourismCatalog::municipalities(),
            'establishments' => PtoMockData::establishmentDirectory(),
        ]);
    }

    /**
     * Visitation Statistics: province-wide trend and municipality comparison.
     */
    public function statistics(Request $request): View
    {
        return $this->renderPto($request, 'pto.monitoring.statistics', 'monitoring.statistics', 'Visitation Statistics', [
            'arrivalTrend' => PtoMockData::arrivalTrend(),
            'municipalityComparison' => PtoMockData::municipalityComparison(),
            'summary' => PtoMockData::dashboardSummary(),
        ]);
    }

    /**
     * Destination Performance: destinations ranked by tourist visits.
     */
    public function destinations(Request $request): View
    {
        return $this->renderPto($request, 'pto.monitoring.destinations', 'monitoring.destinations', 'Destination Performance', [
            'performance' => PtoMockData::destinationPerformance(),
            'municipalities' => TourismCatalog::municipalities(),
        ]);
    }
}
