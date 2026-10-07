<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: In-app notification telling the LGU's users an establishment submitted its profile package
 * for review.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EstablishmentListingSubmittedToLgu extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Listing $objListing,
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
            'listing_id' => $this->objListing->lst_id,
            'listing_name' => $this->objListing->lst_name,
            'message' => "\"{$this->objListing->lst_name}\" was submitted for your review.",
        ];
    }
}
