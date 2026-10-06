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
use App\Support\DashboardNavigation;
use Illuminate\Http\Request;
use Illuminate\View\View;

abstract class EstablishmentController extends Controller
{
    /**
     * Render an Establishment page with the sidebar nav, active state, and
     * shared sidebar chrome already wired up, plus the account's own
     * establishment name — every page here is scoped to it.
     *
     * @param  array<string, mixed>  $arrData
     */
    protected function renderEstablishment(Request $objRequest, string $strView, string $strActiveKey, string $strPageTitle, array $arrData = []): View
    {
        $objUser = $objRequest->user();

        return view($strView, array_merge([
            'user' => $objUser,
            'establishmentName' => $objUser->usr_organization_name,
            'navSections' => DashboardNavigation::sections($objUser, $strActiveKey),
            'pageTitle' => $strPageTitle,
            'accountHeading' => 'System',
            'settingsHref' => route('establishment.settings'),
        ], $arrData));
    }
}
