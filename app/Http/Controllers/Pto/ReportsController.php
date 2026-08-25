<?php

namespace App\Http\Controllers\Pto;

use App\Support\PtoMockData;
use App\Support\TourismCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends PtoController
{
    /**
     * Reports: generate and review province-wide tourism reports.
     */
    public function index(Request $request): View
    {
        return $this->renderPto($request, 'pto.reports', 'reports', 'Reports', [
            'reportTypes' => PtoMockData::reportTypes(),
            'history' => PtoMockData::reportHistory(),
            'municipalities' => TourismCatalog::municipalities(),
        ]);
    }
}
