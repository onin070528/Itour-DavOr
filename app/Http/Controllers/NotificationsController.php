<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: In-app notifications behind the dashboard bell — open (mark read
 * and go to the related page) and mark all read. Shared by every role; a
 * user can only ever touch their own notifications.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    public function open(Request $objRequest, string $notificationId): RedirectResponse
    {
        $objNotification = $objRequest->user()->notifications()->findOrFail($notificationId);
        $objNotification->markAsRead();

        $strUrl = $objNotification->data['url'] ?? null;

        // Only ever follow a same-site relative/absolute URL we generated ourselves.
        if (is_string($strUrl) && str_starts_with($strUrl, url('/'))) {
            return redirect()->to($strUrl);
        }

        return back();
    }

    public function readAll(Request $objRequest): RedirectResponse
    {
        $objRequest->user()->unreadNotifications->markAsRead();

        return back();
    }
}
