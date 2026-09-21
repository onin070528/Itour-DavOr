<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: PTO account settings page (profile, password, notification
 * preferences), backed by the shared UpdatesAccountSettings trait.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Http\Controllers\Concerns\UpdatesAccountSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends PtoController
{
    use UpdatesAccountSettings;

    /**
     * Settings: profile, account information, and basic preferences.
     */
    public function index(Request $request): View
    {
        return $this->renderPto($request, 'pto.settings', 'settings', 'Settings', [
            'preferences' => $this->notificationPreferencesFor($request->user()->id),
        ]);
    }

    protected function notificationPreferenceDefinitions(): array
    {
        return [
            ['key' => 'email_weekly_summary', 'label' => 'Email me a weekly tourism summary', 'default' => true],
            ['key' => 'email_negative_feedback', 'label' => 'Notify me when tourist feedback is flagged negative', 'default' => true],
            ['key' => 'email_new_establishment', 'label' => 'Notify me when a new establishment registers', 'default' => false],
        ];
    }
}
