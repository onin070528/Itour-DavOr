<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: In-app notification telling an uploader their photo is now Live.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
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
            'listing_name' => $this->objImage->listing->lst_name,
            'message' => "Your photo for {$this->objImage->listing->lst_name} is now live.",
        ];
    }
}
