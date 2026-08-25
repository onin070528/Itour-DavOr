<?php

namespace App\Http\Controllers\Establishment;

use App\Support\EstablishmentMockData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends EstablishmentController
{
    /**
     * The Establishment landing page: how this establishment is performing.
     */
    public function index(Request $request): View
    {
        $name = $request->user()->organization_name;

        return $this->renderEstablishment($request, 'establishment.dashboard', 'dashboard', 'Dashboard', [
            'summary' => EstablishmentMockData::dashboardSummary($name),
            'arrivalTrend' => EstablishmentMockData::arrivalTrend($name),
            'classificationBreakdown' => EstablishmentMockData::classificationBreakdown($name),
            'sentiment' => EstablishmentMockData::sentimentBreakdown($name),
            'recentActivity' => array_slice(EstablishmentMockData::recentActivity($name), 0, 6),
        ]);
    }
}
