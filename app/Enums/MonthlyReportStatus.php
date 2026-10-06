<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Review status of a MonthlyArrivalReport. "Not Submitted" is
 * deliberately NOT a case here — it is never a stored value. It is the
 * absence of a monthly_arrival_reports row for a given establishment and
 * period, computed by the LGU dashboard, so a missing report can never be
 * mistaken for (or silently treated as) a verified zero-arrival one.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Enums;

enum MonthlyReportStatus: string
{
    case ForReview = 'ForReview';
    case Verified = 'Verified';

    public function label(): string
    {
        return match ($this) {
            self::ForReview => 'For Review',
            self::Verified => 'Verified',
        };
    }

    /**
     * Tone for <x-dashboard.status-badge>.
     */
    public function badgeTone(): string
    {
        return match ($this) {
            self::ForReview => 'warning',
            self::Verified => 'success',
        };
    }
}
