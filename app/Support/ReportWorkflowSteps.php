<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Computes the 4-step Collect → Review & Verify → Consolidate →
 * Submit to PTO progress shown on both the LGU and (read-only) PTO Tourism
 * Reports pages for a given municipality + period, from the same counts
 * both controllers already compute for their KPI cards.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

class ReportWorkflowSteps
{
    /**
     * Consolidate and Submit to PTO happen as one action in this app
     * (Lgu\MonthlyReportsController::consolidate()), so they're always
     * reached together — $blnConsolidated covers both steps 3 and 4 at once.
     *
     * @return array<int, array{label: string, state: 'done'|'current'|'pending'}>
     */
    public static function compute(int $intSubmittedCount, int $intForReviewCount, int $intVerifiedCount, bool $blnConsolidated): array
    {
        return [
            ['label' => 'Collect reports', 'state' => match (true) {
                $blnConsolidated, $intSubmittedCount > 0 => 'done',
                default => 'current',
            }],
            ['label' => 'Review & verify', 'state' => match (true) {
                $blnConsolidated => 'done',
                $intForReviewCount > 0 => 'current',
                $intVerifiedCount > 0 => 'done',
                default => 'pending',
            }],
            ['label' => 'Consolidate', 'state' => match (true) {
                $blnConsolidated => 'done',
                $intVerifiedCount > 0 && $intForReviewCount === 0 => 'current',
                default => 'pending',
            }],
            ['label' => 'Submit to PTO', 'state' => $blnConsolidated ? 'done' : 'pending'],
        ];
    }
}
