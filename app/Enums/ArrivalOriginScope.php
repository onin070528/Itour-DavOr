<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: For a local/domestic guest on an Arrival row, whether they live
 * within Davao Oriental ("within_province") or elsewhere in the
 * Philippines ("outside_province"). Only `outside_province` pairs with a
 * specific `local_origin_place`.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Enums;

enum ArrivalOriginScope: string
{
    case WithinProvince = 'within_province';
    case OutsideProvince = 'outside_province';

    public function label(): string
    {
        return match ($this) {
            self::WithinProvince => 'Within Davao Oriental',
            self::OutsideProvince => 'Outside Davao Oriental',
        };
    }
}
