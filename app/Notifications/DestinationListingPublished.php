<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : In-app notification telling the LGU's users the PTO approved and published a destination listing (or its changes).
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DestinationListingPublished extends Notification
{
    use Queueable;

    /**
     * $blnIsChangeRequest: the PTO approved held changes to an already
     * Published listing, rather than publishing a new request.
     */
    public function __construct(
        private readonly Listing $objListing,
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
        return [
            'listing_id' => $this->objListing->id,
            'listing_name' => $this->objListing->name,
            'message' => $this->blnIsChangeRequest
                ? "PTO approved the changes to \"{$this->objListing->name}\". They are now live."
                : "PTO approved and published \"{$this->objListing->name}\" as a tourist destination.",
            // Relative, so the bell only ever redirects within iTOUR.
            'url' => $this->objListing->lguDetailsPath(),
        ];
    }
}
