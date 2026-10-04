<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : In-app notification telling an uploader their photo is now Live.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Notifications;

use App\Models\EstablishmentImage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EstablishmentImageApproved extends Notification
{
    use Queueable;

    public function __construct(private readonly EstablishmentImage $objImage) {}

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
            'image_id' => $this->objImage->img_id,
            'listing_name' => $this->objImage->listing->name,
            'message' => "Your photo for {$this->objImage->listing->name} is now live.",
        ];
    }
}
