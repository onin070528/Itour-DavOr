<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: PTO's Audit Logs page — read-only, province-wide, both tabs
 * (security + operation), with the municipality filter PTO alone gets.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Http\Controllers\Concerns\ExportsAuditLogs;
use App\Models\Municipality;
use App\Support\AuditLogQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogsController extends PtoController
{
    use ExportsAuditLogs;

    public function index(Request $objRequest): View
    {
        $objRequest->validate(['tab' => ['nullable', Rule::in(['security', 'operation'])]]);
        $strTab = $objRequest->query('tab', 'security');
        $arrFilters = AuditLogQuery::validatedFilters($objRequest);
        $objUser = $objRequest->user();

        return $this->renderPto($objRequest, 'pto.audit-logs.index', 'auditLogs', 'Audit Logs', [
            'tab' => $strTab,
            'filters' => $arrFilters,
            'rows' => $strTab === 'security' ? AuditLogQuery::securityLogs($objUser, $arrFilters) : AuditLogQuery::operationLogs($objUser, $arrFilters),
            'securitySummary' => AuditLogQuery::securitySummary($objUser),
            'operationSummary' => AuditLogQuery::operationSummary($objUser),
            'municipalities' => Municipality::query()->orderBy('mun_name')->get(['mun_id', 'mun_name']),
            'subtitle' => 'Security events and system operations across the province.',
        ]);
    }
}
