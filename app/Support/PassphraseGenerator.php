<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Generates a readable temporary passphrase for newly created accounts.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class PassphraseGenerator
{
    private const WORDS = [
        'Beach', 'Coral', 'Tour', 'Falls', 'Reef', 'Dawn', 'Trek', 'Surf',
        'Isle', 'Cove', 'Lake', 'Peak', 'Wave', 'Palm', 'Tide',
    ];

    /**
     * Three random, non-repeating tourism words plus the current year,
     * hyphen-joined (e.g. "Coral-Beach-Tour-2026"). The passphrase is
     * never stored anywhere in plain text, so collision detection can't
     * use the database — a 2-second cache flag keyed on the exact
     * combination catches the only real case (two accounts generated in
     * the same second with the same three words) and appends a random
     * suffix when that happens.
     */
    public static function generate(): string
    {
        $arrWords = (array) array_rand(array_flip(self::WORDS), 3);
        $strBase = implode('-', $arrWords).'-'.date('Y');
        $strCacheKey = 'passphrase_issued:'.$strBase;

        if (! Cache::add($strCacheKey, true, 2)) {
            return $strBase.'-'.random_int(10, 99);
        }

        return $strBase;
    }
}
