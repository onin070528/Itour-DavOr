<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Report generation and history for LGU-level municipal tourism
 * reports.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

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
            'previewData' => LguMockData::reportPreviewData($municipality),
            'filterOptions' => [
                'destination' => collect(LguMockData::destinationPerformance($municipality))->pluck('destination')->all(),
            ],
        ]);
    }
}
