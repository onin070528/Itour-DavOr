<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: In-app notification telling an uploader the outcome of one approve-all/return-all decision
 * on their photos.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * One of these per uploader per batch decision (not one per photo) — e.g.
 * "3 photos approved." or "1 photo returned: blurry." See
 * App\Services\EstablishmentImageReviewer::approveBatch()/returnBatch().
 */
class EstablishmentImageBatchDecided extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Listing $objListing,
        private readonly string $strOutcomeMessage,
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
            'listing_name' => $this->objListing->lst_name,
            'message' => "{$this->objListing->lst_name}: {$this->strOutcomeMessage}",
        ];
    }
}
