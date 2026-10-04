<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Enumerates tblestablishment_images.img_status and its public-facing labels.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
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
