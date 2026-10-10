<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Lexicon-based sentiment class of an analyzed tourist feedback
 * row (tbl_feedbacks.fbk_sentiment): S > 0 Positive, S = 0 Neutral,
 * S < 0 Negative, where S = (P - N) / T.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Enums;

enum SentimentClassification: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';

    public function label(): string
    {
        return match ($this) {
            self::Positive => 'Positive',
            self::Neutral => 'Neutral',
            self::Negative => 'Negative',
        };
    }

    /**
     * Tone for <x-dashboard.status-badge>, matching the existing
     * Positive/Neutral/Negative colors on the feedback pages.
     */
    public function badgeTone(): string
    {
        return match ($this) {
            self::Positive => 'success',
            self::Neutral => 'warning',
            self::Negative => 'danger',
        };
    }
}
