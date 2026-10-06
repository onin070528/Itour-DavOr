<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Shared "Verification History" query — every create/encode/verify/
 * correction OperationLog row for one MonthlyArrivalReport, oldest action
 * last. Used by both the LGU (who can act on it) and PTO (read-only trace)
 * report detail pages so they show the exact same audit trail.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Concerns;

use App\Models\MonthlyArrivalReport;
use App\Models\OperationLog;
use Illuminate\Support\Collection;

trait TracksReportHistory
{
    /**
     * @return Collection<int, OperationLog>
     */
    private function reportHistory(MonthlyArrivalReport $objReport): Collection
    {
        return OperationLog::query()
            ->where('opl_entity_type', 'monthly_arrival_report')
            ->where('opl_entity_id', $objReport->mar_id)
            ->with('user')
            ->orderByDesc('opl_created_at')
            ->get();
    }
}
