<?php

namespace App\Http\Controllers\Establishment;

use App\Support\EstablishmentMockData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends EstablishmentController
{
    /**
     * Reports: generate and review establishment-level tourism reports.
     */
    public function index(Request $request): View
    {
        $name = $request->user()->organization_name;

        return $this->renderEstablishment($request, 'establishment.reports', 'reports', 'Reports', [
            'reportTypes' => EstablishmentMockData::reportTypes(),
            'history' => EstablishmentMockData::reportHistory($name),
        ]);
    }
}
