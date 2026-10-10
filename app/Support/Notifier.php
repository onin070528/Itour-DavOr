<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Finds the right recipients (PTO, an LGU's users, a listing's
 * establishment account) and sends them a SystemNotice for the bell.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\SystemNotice;

class Notifier
{
    /**
     * @param  array<string, mixed>  $arrExtra
     */
    public static function toLgu(?int $intMunicipalityId, string $strKind, string $strMessage, ?string $strUrl = null, string $strIcon = 'ti-bell', array $arrExtra = []): void
    {
        if ($intMunicipalityId === null) {
            return;
        }

        User::query()
            ->where('usr_role', UserRole::Lgu)
            ->where('mun_id', $intMunicipalityId)
            ->get()
            ->each(fn (User $objUser) => $objUser->notify(new SystemNotice($strKind, $strMessage, $strUrl, $strIcon, $arrExtra)));
    }

    /**
     * @param  array<string, mixed>  $arrExtra
     */
    public static function toPto(string $strKind, string $strMessage, ?string $strUrl = null, string $strIcon = 'ti-bell', array $arrExtra = []): void
    {
        User::query()
            ->where('usr_role', UserRole::PtoAdministrator)
            ->get()
            ->each(fn (User $objUser) => $objUser->notify(new SystemNotice($strKind, $strMessage, $strUrl, $strIcon, $arrExtra)));
    }

    /**
     * @param  array<string, mixed>  $arrExtra
     */
    public static function toEstablishment(Listing $objListing, string $strKind, string $strMessage, ?string $strUrl = null, string $strIcon = 'ti-bell', array $arrExtra = []): void
    {
        $objListing->establishmentUser?->notify(new SystemNotice($strKind, $strMessage, $strUrl, $strIcon, $arrExtra));
    }
}
