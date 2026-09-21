<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Renders the Establishment role's Reports page — report types,
 * generation history, and preview data.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

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
            'previewData' => EstablishmentMockData::reportPreviewData($name),
            'filterOptions' => [],
        ]);
    }
}
