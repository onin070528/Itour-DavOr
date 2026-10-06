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

use App\Support\AuditLogQuery;
use App\Support\OperationLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsAuditLogs
{
    public function export(Request $objRequest): StreamedResponse
    {
        $objRequest->validate(['tab' => ['required', Rule::in(['security', 'operation'])]]);
        $strTab = $objRequest->query('tab');
        $arrFilters = AuditLogQuery::validatedFilters($objRequest);
        $objUser = Auth::user();

        $objRows = $strTab === 'security'
            ? AuditLogQuery::securityLogsForExport($objUser, $arrFilters)
            : AuditLogQuery::operationLogsForExport($objUser, $arrFilters);

        OperationLogger::exported($objUser, $strTab === 'security' ? 'security_log' : 'operation_log', $objUser->mun_id, [
            'row_count' => $objRows->count(),
            'filters' => array_filter($arrFilters),
        ]);

        $strFilename = ($strTab === 'security' ? 'security-logs' : 'operation-logs').'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($strTab, $objRows) {
            $objHandle = fopen('php://output', 'w');

            if ($strTab === 'security') {
                fputcsv($objHandle, ['Date & Time (Asia/Manila)', 'User', 'Attempted Email', 'Event', 'Municipality', 'IP Address', 'Details']);

                foreach ($objRows as $objRow) {
                    fputcsv($objHandle, [
                        $objRow->sec_created_at->timezone('Asia/Manila')->format('Y-m-d H:i'),
                        $objRow->user?->usr_name ?? '—',
                        $objRow->sec_attempted_email ?? '',
                        $objRow->sec_event_type,
                        $objRow->municipality?->mun_name ?? '',
                        $objRow->sec_ip_address ?? '',
                        $objRow->sec_details ? json_encode($objRow->sec_details) : '',
                    ]);
                }
            } else {
                fputcsv($objHandle, ['Date & Time (Asia/Manila)', 'User', 'Role', 'Action', 'Entity Type', 'Entity ID', 'Municipality', 'Establishment', 'Reason', 'Old Values', 'New Values']);

                foreach ($objRows as $objRow) {
                    fputcsv($objHandle, [
                        $objRow->opl_created_at->timezone('Asia/Manila')->format('Y-m-d H:i'),
                        $objRow->user?->usr_name ?? '—',
                        $objRow->opl_user_role,
                        $objRow->opl_action,
                        $objRow->opl_entity_type,
                        $objRow->opl_entity_id,
                        $objRow->municipality?->mun_name ?? '',
                        $objRow->establishment?->lst_name ?? '',
                        $objRow->opl_reason ?? '',
                        $objRow->opl_old_values ? json_encode($objRow->opl_old_values) : '',
                        $objRow->opl_new_values ? json_encode($objRow->opl_new_values) : '',
                    ]);
                }
            }

            fclose($objHandle);
        }, $strFilename, ['Content-Type' => 'text/csv']);
    }
}
