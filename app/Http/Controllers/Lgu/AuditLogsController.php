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

    public function index(Request $request): View
    {
        $request->validate(['tab' => ['nullable', Rule::in(['security', 'operation'])]]);
        $tab = $request->query('tab', 'security');
        $filters = AuditLogQuery::validatedFilters($request);
        $user = $request->user();

        return $this->renderLgu($request, 'lgu.audit-logs.index', 'auditLogs', 'Audit Logs', [
            'tab' => $tab,
            'filters' => $filters,
            'rows' => $tab === 'security' ? AuditLogQuery::securityLogs($user, $filters) : AuditLogQuery::operationLogs($user, $filters),
            'securitySummary' => AuditLogQuery::securitySummary($user),
            'operationSummary' => AuditLogQuery::operationSummary($user),
            'municipalities' => null,
            'subtitle' => "Activity in {$user->organization_subtitle}.",
        ]);
    }
}
