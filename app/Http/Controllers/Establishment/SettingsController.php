<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Renders the Establishment role's Settings page and supplies its
 * notification-preference definitions (profile/password handling is shared
 * via UpdatesAccountSettings).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Establishment;

use App\Http\Controllers\Concerns\UpdatesAccountSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends EstablishmentController
{
    use UpdatesAccountSettings;

    /**
     * Settings: account profile, contact information, and preferences.
     */
    public function index(Request $request): View
    {
        return $this->renderEstablishment($request, 'establishment.settings', 'settings', 'Settings', [
            'preferences' => $this->notificationPreferencesFor($request->user()->id),
        ]);
    }

    protected function notificationPreferenceDefinitions(): array
    {
        return [
            ['key' => 'email_new_arrival', 'label' => 'Email me when a new tourist arrival is recorded', 'default' => false],
            ['key' => 'email_new_feedback', 'label' => 'Email me when new feedback is left about my establishment', 'default' => true],
            ['key' => 'email_monthly_summary', 'label' => 'Email me a monthly visitor summary', 'default' => true],
        ];
    }
}
