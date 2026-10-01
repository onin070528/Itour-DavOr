<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: How a MonthlyArrivalReport entered the system — submitted
 * directly by the establishment through iTOUR, or encoded by LGU staff from
 * a physical paper report. Both sources feed the same monthly_arrival_reports
 * table and the same LGU review/verify/consolidate pipeline.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Enums;

enum ReportSubmissionSource: string
{
    case Digital = 'Digital';
    case ManualPaper = 'ManualPaper';

    public function label(): string
    {
        return match ($this) {
            self::Digital => 'Digital Submission',
            self::ManualPaper => 'Manual / Paper',
        };
    }

    /**
     * Tone for <x-dashboard.status-badge>.
     */
    public function badgeTone(): string
    {
        return match ($this) {
            self::Digital => 'info',
            self::ManualPaper => 'neutral',
        };
    }

    /**
     * Tabler icon class for the Tourism Reports table's Source column.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Digital => 'ti-device-laptop',
            self::ManualPaper => 'ti-file-text',
        };
    }
}
