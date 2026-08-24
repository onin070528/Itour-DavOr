<?php

namespace App\Http\Controllers\Lgu;

use App\Support\LguMockData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends LguController
{
    /**
     * Reports: generate and review municipality-level tourism reports.
     */
    public function index(Request $request): View
    {
        $municipality = $request->user()->organization_subtitle;

        return $this->renderLgu($request, 'lgu.reports', 'reports', 'Reports', [
            'municipality' => $municipality,
            'reportTypes' => LguMockData::reportTypes(),
            'history' => LguMockData::reportHistory($municipality),
        ]);
    }
}
