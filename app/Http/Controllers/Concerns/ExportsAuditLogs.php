<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Shared "Export Excel" (CSV) handling for the PTO and LGU Audit
 * Logs pages — Establishment doesn't get export at all (no route, no
 * button). No spreadsheet library is installed, so this streams a real,
 * correctly-scoped .csv file rather than a .xlsx; the "Export PDF" button
 * instead reuses this app's existing print-to-PDF pattern (see
 * resources/views/components/dashboard/report-workspace.blade.php's
 * #report-print-button) client-side, with no matching server route.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Concerns;

use App\Models\OperationLog;
use App\Models\SecurityLog;
use App\Support\AuditLogQuery;
use App\Support\OperationLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsAuditLogs
{
    public function export(Request $request): StreamedResponse
    {
        $request->validate(['tab' => ['required', Rule::in(['security', 'operation'])]]);
        $tab = $request->query('tab');
        $filters = AuditLogQuery::validatedFilters($request);
        $user = Auth::user();

        abort_unless($user->can('export', $tab === 'security' ? SecurityLog::class : OperationLog::class), 403);

        $rows = $tab === 'security'
            ? AuditLogQuery::securityLogsForExport($user, $filters)
            : AuditLogQuery::operationLogsForExport($user, $filters);

        OperationLogger::exported($user, $tab === 'security' ? 'security_log' : 'operation_log', $user->municipality_id, [
            'row_count' => $rows->count(),
            'filters' => array_filter($filters),
        ]);

        $filename = ($tab === 'security' ? 'security-logs' : 'operation-logs').'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($tab, $rows) {
            $handle = fopen('php://output', 'w');

            if ($tab === 'security') {
                fputcsv($handle, ['Date & Time (Asia/Manila)', 'User', 'Attempted Email', 'Event', 'Municipality', 'IP Address', 'Details']);

                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->created_at->timezone('Asia/Manila')->format('Y-m-d H:i'),
                        $row->user?->name ?? '—',
                        $row->attempted_email ?? '',
                        $row->event_type,
                        $row->municipality?->name ?? '',
                        $row->ip_address ?? '',
                        $row->details ? json_encode($row->details) : '',
                    ]);
                }
            } else {
                fputcsv($handle, ['Date & Time (Asia/Manila)', 'User', 'Role', 'Action', 'Entity Type', 'Entity ID', 'Municipality', 'Establishment', 'Reason', 'Old Values', 'New Values']);

                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->created_at->timezone('Asia/Manila')->format('Y-m-d H:i'),
                        $row->user?->name ?? '—',
                        $row->user_role,
                        $row->action,
                        $row->entity_type,
                        $row->entity_id,
                        $row->municipality?->name ?? '',
                        $row->establishment?->name ?? '',
                        $row->reason ?? '',
                        $row->old_values ? json_encode($row->old_values) : '',
                        $row->new_values ? json_encode($row->new_values) : '',
                    ]);
                }
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
