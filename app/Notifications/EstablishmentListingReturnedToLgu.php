<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: In-app notification telling the LGU's users a listing they submitted was returned by PTO,
 * and why.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EstablishmentListingReturnedToLgu extends Notification
{
    use Queueable;

    /**
     * $blnIsChangeRequest: the return concerns held changes to a Published
     * listing (which stays live), not a new destination listing request.
     */
    public function __construct(
        private readonly Listing $objListing,
        private readonly string $strReason,
        private readonly bool $blnIsChangeRequest = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $objNotifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $objNotifiable): array
    {
        $strSubject = $this->blnIsChangeRequest
            ? "PTO returned the changes to \"{$this->objListing->lst_name}\""
            : "PTO returned \"{$this->objListing->lst_name}\"";

        return [
            'listing_id' => $this->objListing->lst_id,
            'listing_name' => $this->objListing->lst_name,
            'reason' => $this->strReason,
            'message' => "{$strSubject}: {$this->strReason}",
            // Relative, so the bell only ever redirects within iTOUR.
            'url' => $this->objListing->lguDetailsPath(),
        ];
    }
}
