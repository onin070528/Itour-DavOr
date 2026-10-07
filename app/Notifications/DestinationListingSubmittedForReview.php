<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : In-app notification telling PTO users an LGU submitted a destination listing (or changes to one) for review.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DestinationListingSubmittedForReview extends Notification
{
    use Queueable;

    /**
     * $blnIsChangeRequest: changes to an already Published listing, rather
     * than a new "Request to feature as tourist destination".
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
        $strSubject = $this->blnIsChangeRequest ? 'Changes to a Published Listing for Review' : 'New Destination Listing for Review';

        return [
            'listing_id' => $this->objListing->lst_id,
            'listing_name' => $this->objListing->lst_name,
            'municipality_id' => $this->objListing->mun_id,
            'message' => "{$strSubject}: {$this->objListing->lst_name} submitted by {$this->objListing->lst_municipality} LGU",
            // Relative, so the bell only ever redirects within iTOUR.
            'url' => route('pto.destinationReviews.show', $this->objListing, false),
        ];
    }
}
