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
use App\Models\SecurityLog;
use App\Support\AuditLogQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogsController extends PtoController
{
    use ExportsAuditLogs;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('viewAny', SecurityLog::class), 403);
        $request->validate(['tab' => ['nullable', Rule::in(['security', 'operation'])]]);
        $tab = $request->query('tab', 'security');
        $filters = AuditLogQuery::validatedFilters($request);
        $user = $request->user();

        return $this->renderPto($request, 'pto.audit-logs.index', 'auditLogs', 'Audit Logs', [
            'tab' => $tab,
            'filters' => $filters,
            'rows' => $tab === 'security' ? AuditLogQuery::securityLogs($user, $filters) : AuditLogQuery::operationLogs($user, $filters),
            'securitySummary' => AuditLogQuery::securitySummary($user),
            'operationSummary' => AuditLogQuery::operationSummary($user),
            'municipalities' => Municipality::query()->orderBy('name')->get(['id', 'name']),
            'subtitle' => 'Security events and system operations across the province.',
        ]);
    }
}
