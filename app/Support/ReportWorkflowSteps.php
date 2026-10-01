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
     * reached together — $consolidated covers both steps 3 and 4 at once.
     *
     * @return array<int, array{label: string, state: 'done'|'current'|'pending'}>
     */
    public static function compute(int $submittedCount, int $forReviewCount, int $verifiedCount, bool $consolidated): array
    {
        return [
            ['label' => 'Collect reports', 'state' => match (true) {
                $consolidated, $submittedCount > 0 => 'done',
                default => 'current',
            }],
            ['label' => 'Review & verify', 'state' => match (true) {
                $consolidated => 'done',
                $forReviewCount > 0 => 'current',
                $verifiedCount > 0 => 'done',
                default => 'pending',
            }],
            ['label' => 'Consolidate', 'state' => match (true) {
                $consolidated => 'done',
                $verifiedCount > 0 && $forReviewCount === 0 => 'current',
                default => 'pending',
            }],
            ['label' => 'Submit to PTO', 'state' => $consolidated ? 'done' : 'pending'],
        ];
    }
}
