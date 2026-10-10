<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Generic in-app (bell) notification — a short message, an icon,
 * and an optional page to open when it is clicked.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SystemNotice extends Notification
{
    use Queueable;

    /**
     * @param  string  $strKind  Machine key used to de-duplicate (e.g. "report-reminder").
     * @param  array<string, mixed>  $arrExtra  Extra data stored alongside (e.g. a dedupe key).
     */
    public function __construct(
        private readonly string $strKind,
        private readonly string $strMessage,
        private readonly ?string $strUrl = null,
        private readonly string $strIcon = 'ti-bell',
        private readonly array $arrExtra = [],
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
        return array_merge($this->arrExtra, [
            'kind' => $this->strKind,
            'message' => $this->strMessage,
            'url' => $this->strUrl,
            'icon' => $this->strIcon,
        ]);
    }
}
