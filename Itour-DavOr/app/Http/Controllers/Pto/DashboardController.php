<?php

namespace App\Http\Controllers\Pto;

use App\Support\PtoMockData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends PtoController
{
    /**
     * The PTO landing page: a province-wide snapshot of tourism activity.
     */
    public function index(Request $request): View
    {
        return $this->renderPto($request, 'pto.dashboard', 'dashboard', 'Dashboard', [
            'summary' => PtoMockData::dashboardSummary(),
            'arrivalTrend' => PtoMockData::arrivalTrend(),
            'destinationPerformance' => array_slice(PtoMockData::destinationPerformance(), 0, 5),
            'sentiment' => PtoMockData::sentimentBreakdown(),
            'recentActivity' => array_slice(PtoMockData::recentActivity(), 0, 6),
        ]);
    }
}
