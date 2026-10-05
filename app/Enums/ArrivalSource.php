<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: How an Arrival row was recorded — entered by establishment
 * front-desk staff, or submitted by the visitor through the public QR
 * self-check-in form. Mirrors the existing string values already stored on
 * `arrivals.source` ('staff' / 'self_checkin'); introduced as a backed enum
 * so the value set is enforced in code instead of bare string literals.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Enums;

enum ArrivalSource: string
{
    case Staff = 'staff';
    case SelfCheckin = 'self_checkin';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Front Desk',
            self::SelfCheckin => 'QR Self Check-in',
        };
    }
}
