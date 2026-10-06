<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Establishment's Activity Log page — read-only, scoped to the
 * account's own login history and its own establishment's operations.
 * No IP/browser columns, no summary cards, no export (see resources/views).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Establishment;

use App\Support\AuditLogQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityLogController extends EstablishmentController
{
    public function index(Request $objRequest): View
    {
        $objRequest->validate(['tab' => ['nullable', Rule::in(['security', 'operation'])]]);
        $strTab = $objRequest->query('tab', 'security');
        $arrFilters = AuditLogQuery::validatedFilters($objRequest);
        $objUser = $objRequest->user();

        return $this->renderEstablishment($objRequest, 'establishment.audit-logs.index', 'activityLog', 'Activity', [
            'tab' => $strTab,
            'filters' => $arrFilters,
            'rows' => $strTab === 'security' ? AuditLogQuery::securityLogs($objUser, $arrFilters) : AuditLogQuery::operationLogs($objUser, $arrFilters),
            'securitySummary' => null,
            'operationSummary' => null,
            'municipalities' => null,
            'subtitle' => 'Your login history and establishment activity.',
        ]);
    }
}
