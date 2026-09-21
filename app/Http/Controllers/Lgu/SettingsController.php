<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: LGU account settings page (profile, password, notification
 * preferences), backed by the shared UpdatesAccountSettings trait.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Http\Controllers\Concerns\UpdatesAccountSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends LguController
{
    use UpdatesAccountSettings;

    /**
     * Settings: profile, account information, assigned municipality, and preferences.
     */
    public function index(Request $request): View
    {
        return $this->renderLgu($request, 'lgu.settings', 'settings', 'Settings', [
            'preferences' => $this->notificationPreferencesFor($request->user()->id),
        ]);
    }

    protected function notificationPreferenceDefinitions(): array
    {
        return [
            ['key' => 'email_weekly_summary', 'label' => 'Email me a weekly municipal tourism summary', 'default' => true],
            ['key' => 'email_negative_feedback', 'label' => 'Notify me when tourist feedback is flagged negative', 'default' => true],
            ['key' => 'email_new_establishment', 'label' => 'Notify me when a new establishment registers in my municipality', 'default' => false],
        ];
    }
}
