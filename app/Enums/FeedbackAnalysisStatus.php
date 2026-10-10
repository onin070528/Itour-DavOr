<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Processing status of one tourist feedback row
 * (tbl_feedbacks.fbk_status). Every submission is stored as Pending, then
 * becomes Analyzed (translated and scored) or Failed (translation or
 * processing error, no sentiment stored). Rejected is set by the system
 * for honeypot hits or text that is empty after normalization. Only
 * Analyzed rows count in official analytics.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Enums;

enum FeedbackAnalysisStatus: string
{
    case Pending = 'pending';
    case Analyzed = 'analyzed';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Analyzed => 'Analyzed',
            self::Failed => 'Failed',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Tone for <x-dashboard.status-badge>.
     */
    public function badgeTone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Analyzed => 'success',
            self::Failed => 'danger',
            self::Rejected => 'warning',
        };
    }

    /**
     * Whether a row with this status is counted in official analytics.
     */
    public function isCountedInAnalytics(): bool
    {
        return $this === self::Analyzed;
    } // end isCountedInAnalytics
}
