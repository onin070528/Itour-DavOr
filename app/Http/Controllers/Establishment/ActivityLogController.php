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

use App\Models\SecurityLog;
use App\Support\AuditLogQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityLogController extends EstablishmentController
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('viewAny', SecurityLog::class), 403);
        $request->validate(['tab' => ['nullable', Rule::in(['security', 'operation'])]]);
        $tab = $request->query('tab', 'security');
        $filters = AuditLogQuery::validatedFilters($request);
        $user = $request->user();

        return $this->renderEstablishment($request, 'establishment.audit-logs.index', 'activityLog', 'Activity', [
            'tab' => $tab,
            'filters' => $filters,
            'rows' => $tab === 'security' ? AuditLogQuery::securityLogs($user, $filters) : AuditLogQuery::operationLogs($user, $filters),
            'securitySummary' => null,
            'operationSummary' => null,
            'municipalities' => null,
            'subtitle' => 'Your login history and establishment activity.',
        ]);
    }
}
