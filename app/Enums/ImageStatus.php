<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Enumerates tbl_establishment_images.img_status and its public-facing labels.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Enums;

enum ImageStatus: string
{
    case Pending = 'PENDING';
    case Published = 'PUBLISHED';
    case Rejected = 'REJECTED';
    case Archived = 'ARCHIVED';

    /**
     * The label shown to users — deliberately different wording from the
     * stored value (e.g. REJECTED reads as "Returned," since the uploader
     * can correct and resubmit, it isn't a final rejection).
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for approval',
            self::Published => 'Live',
            self::Rejected => 'Returned',
            self::Archived => 'Archived',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Published => 'success',
            self::Rejected => 'danger',
            self::Archived => 'neutral',
        };
    }
}
