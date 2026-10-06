<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: LGU's Audit Logs page — read-only, scoped to the account's own
 * municipality. No municipality filter (there's only ever one to show).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Http\Controllers\Concerns\ExportsAuditLogs;
use App\Support\AuditLogQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogsController extends LguController
{
    use ExportsAuditLogs;

    public function index(Request $objRequest): View
    {
        $objRequest->validate(['tab' => ['nullable', Rule::in(['security', 'operation'])]]);
        $strTab = $objRequest->query('tab', 'security');
        $arrFilters = AuditLogQuery::validatedFilters($objRequest);
        $objUser = $objRequest->user();

        return $this->renderLgu($objRequest, 'lgu.audit-logs.index', 'auditLogs', 'Audit Logs', [
            'tab' => $strTab,
            'filters' => $arrFilters,
            'rows' => $strTab === 'security' ? AuditLogQuery::securityLogs($objUser, $arrFilters) : AuditLogQuery::operationLogs($objUser, $arrFilters),
            'securitySummary' => AuditLogQuery::securitySummary($objUser),
            'operationSummary' => AuditLogQuery::operationSummary($objUser),
            'municipalities' => null,
            'subtitle' => "Activity in {$objUser->usr_organization_subtitle}.",
        ]);
    }
}
