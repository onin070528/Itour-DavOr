<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Abstract base controller for the Establishment role — shares the
 * sidebar chrome and account-scoped rendering used by every Establishment page.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Establishment;

use App\Http\Controllers\Controller;
use App\Support\EstablishmentNavigation;
use Illuminate\Http\Request;
use Illuminate\View\View;

abstract class EstablishmentController extends Controller
{
    /**
     * Render an Establishment page with the sidebar nav, active state, and
     * shared sidebar chrome already wired up, plus the account's own
     * establishment name — every page here is scoped to it.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderEstablishment(Request $request, string $view, string $activeKey, string $pageTitle, array $data = []): View
    {
        $user = $request->user();

        return view($view, array_merge([
            'user' => $user,
            'establishmentName' => $user->organization_name,
            'navSections' => EstablishmentNavigation::sections($activeKey),
            'pageTitle' => $pageTitle,
            'accountHeading' => 'System',
            'settingsHref' => route('establishment.settings'),
        ], $data));
    }
}
