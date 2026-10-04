<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : In-app notification telling the LGU's users a listing they submitted was returned by PTO, and why.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EstablishmentListingReturnedToLgu extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Listing $objListing,
        private readonly string $strReason,
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
        return [
            'listing_id' => $this->objListing->id,
            'listing_name' => $this->objListing->name,
            'reason' => $this->strReason,
            'message' => "PTO returned \"{$this->objListing->name}\": {$this->strReason}",
        ];
    }
}
