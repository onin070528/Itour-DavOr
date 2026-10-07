<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : The dashboard notification bell — open one notification (marks it read) or mark all as read.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Laravel database notifications (the `notifications` table). Every
 * lookup goes through the signed-in user's own notifications() relation,
 * so another user's notification id is simply not found (404).
 */
class NotificationsController extends Controller
{
    /**
     * Marks the notification read and goes to the page it is about. Only
     * a relative iTOUR path stored by the notification itself is followed
     * — never an external URL — otherwise the user stays where they were.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $objNotification = $request->user()->notifications()->findOrFail($notification);
        $objNotification->markAsRead();

        $strUrl = (string) ($objNotification->data['url'] ?? '');
        $blnIsInternalPath = str_starts_with($strUrl, '/') && ! str_starts_with($strUrl, '//') && ! str_contains($strUrl, '\\');

        return $blnIsInternalPath ? redirect($strUrl) : back();
    } // end open

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    } // end markAllRead
}
