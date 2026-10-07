<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Review status of a MonthlyArrivalReport. "Not Submitted" is
 * deliberately NOT a case here — it is never a stored value. It is the
 * absence of a monthly_arrival_reports row for a given establishment and
 * period, computed by the LGU dashboard, so a missing report can never be
 * mistaken for (or silently treated as) a verified zero-arrival one.
 *
 * Workflow (CLAUDE.md 2.5): Draft -> Submitted (sent to the LGU, not yet
 * opened) -> ForReview (LGU started reviewing) -> Verified, with
 * ForReview -> ForCorrection (returned with remarks) -> Submitted on
 * resubmission. Rows submitted before the Submitted status existed simply
 * stay ForReview, which is still a valid in-review state.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Enums;

enum MonthlyReportStatus: string
{
    case Draft = 'Draft';
    case Submitted = 'Submitted';
    case ForReview = 'ForReview';
    case ForCorrection = 'ForCorrection';
    case Verified = 'Verified';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::ForReview => 'For Review',
            self::ForCorrection => 'For Correction',
            self::Verified => 'Verified',
        };
    }

    /**
     * Tone for <x-dashboard.status-badge>.
     */
    public function badgeTone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Submitted => 'info',
            self::ForReview => 'warning',
            self::ForCorrection => 'danger',
            self::Verified => 'success',
        };
    }

    /**
     * Whether the report's owner (the establishment, or the LGU for a
     * paper report it encoded) may still change and submit it.
     */
    public function isEditableByOwner(): bool
    {
        return $this === self::Draft || $this === self::ForCorrection;
    } // end isEditableByOwner

    /**
     * Whether the report has been sent to the LGU and is still waiting on
     * an LGU decision (not yet opened, being reviewed, or returned and not
     * yet resubmitted) — i.e. it blocks municipal consolidation.
     */
    public function isPendingLguAction(): bool
    {
        return $this === self::Submitted || $this === self::ForReview || $this === self::ForCorrection;
    } // end isPendingLguAction

    /**
     * @return array<int, self> The statuses the LGU still has to act on
     *                          before a report is verified (awaiting review).
     */
    public static function awaitingReview(): array
    {
        return [self::Submitted, self::ForReview];
    } // end awaitingReview
}
