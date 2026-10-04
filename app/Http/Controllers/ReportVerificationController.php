<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Public page to verify an Official Report's verification code —
 * confirms the report number, period, status, and verification date only;
 * never arrival-level data.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Pto\MunicipalReportsController;
use App\Models\MunicipalReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportVerificationController extends Controller
{
    public function show(Request $request): View
    {
        $code = trim((string) $request->query('code'));
        $result = null;
        $notFound = false;

        if ($code !== '') {
            $report = MunicipalReport::query()->where('verification_code', $code)->first();

            if ($report) {
                $result = [
                    'reference_number' => sprintf('MRP-%06d', $report->id),
                    'municipality' => $report->municipality,
                    'period_label' => $report->period_start->format('F Y'),
                    'status_label' => MunicipalReportsController::statusLabel($report->status),
                    'verified_at' => $report->reviewed_at?->format('F j, Y'),
                ];
            } else {
                $notFound = true;
            }
        }

        return view('verify-report', [
            'code' => $code,
            'result' => $result,
            'notFound' => $notFound,
        ]);
    }
}
