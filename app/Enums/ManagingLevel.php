<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Enumerates tbl_listings.lst_managing_level — which office level
 * (LGU or PTO) manages and may edit a destination-only record.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Enums;

/**
 * A destination's managing level is separate from its physical location
 * (mun_id): a destination always lies in one municipality, but some are
 * managed by the Provincial Tourism Office instead of the municipality's
 * LGU. Stored lowercase, like App\Enums\UserRole's values. A record with no
 * level set is LGU-managed (see Listing::managingLevel()) — the behavior
 * every destination had before this column existed.
 */
enum ManagingLevel: string
{
    case Lgu = 'lgu';
    case Pto = 'pto';

    /**
     * The level a destination has when none is stored.
     */
    public static function default(): self
    {
        return self::Lgu;
    } // end default

    public function label(): string
    {
        return match ($this) {
            self::Lgu => 'LGU',
            self::Pto => 'PTO',
        };
    }
}
