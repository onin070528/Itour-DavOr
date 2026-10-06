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
    public function show(Request $objRequest): View
    {
        $strCode = trim((string) $objRequest->query('code'));
        $arrResult = null;
        $blnNotFound = false;

        if ($strCode !== '') {
            $objReport = MunicipalReport::query()->where('mrp_verification_code', $strCode)->first();

            if ($objReport) {
                $arrResult = [
                    'reference_number' => sprintf('MRP-%06d', $objReport->mrp_id),
                    'municipality' => $objReport->mrp_municipality,
                    'period_label' => $objReport->mrp_period_start->format('F Y'),
                    'status_label' => MunicipalReportsController::statusLabel($objReport->mrp_status),
                    'verified_at' => $objReport->mrp_reviewed_at?->format('F j, Y'),
                ];
            } else {
                $blnNotFound = true;
            }
        }

        return view('verify-report', [
            'code' => $strCode,
            'result' => $arrResult,
            'notFound' => $blnNotFound,
        ]);
    }
}
