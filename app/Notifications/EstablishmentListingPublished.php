<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : In-app notification telling an establishment its listing is now live.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EstablishmentListingPublished extends Notification
{
    use Queueable;

    public function __construct(private readonly Listing $objListing) {}

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
            'message' => "Your listing \"{$this->objListing->name}\" is now live.",
        ];
    }
}
