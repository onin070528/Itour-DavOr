<?php

namespace App\Http\Controllers\Lgu;

use App\Support\LguMockData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends LguController
{
    /**
     * The LGU landing page: a snapshot of tourism activity in the user's
     * assigned municipality.
     */
    public function index(Request $request): View
    {
        $municipality = $request->user()->organization_subtitle;

        return $this->renderLgu($request, 'lgu.dashboard', 'dashboard', 'Dashboard', [
            'summary' => LguMockData::dashboardSummary($municipality),
            'arrivalTrend' => LguMockData::arrivalTrend($municipality),
            'topDestinations' => array_slice(LguMockData::destinationPerformance($municipality), 0, 5),
            'establishmentCategories' => LguMockData::establishmentCategories($municipality),
            'establishmentCount' => count(LguMockData::establishments($municipality)),
            'sentiment' => LguMockData::sentimentBreakdown($municipality),
            'recentActivity' => array_slice(LguMockData::recentActivity($municipality), 0, 6),
        ]);
    }
}
