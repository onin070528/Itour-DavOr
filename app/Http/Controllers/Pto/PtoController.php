<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Base controller shared by every PTO-role controller; renders
 * pages with the PTO sidebar chrome pre-wired.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Http\Controllers\Controller;
use App\Models\EstablishmentImage;
use App\Support\DashboardNavigation;
use Illuminate\Http\Request;
use Illuminate\View\View;

abstract class PtoController extends Controller
{
    /**
     * Render a PTO page with the sidebar nav, active state, and shared
     * sidebar chrome (System heading, Settings link) already wired up.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderPto(Request $request, string $view, string $activeKey, string $pageTitle, array $data = []): View
    {
        return view($view, array_merge([
            'user' => $request->user(),
            'navSections' => DashboardNavigation::sections($request->user(), $activeKey, $this->_imageApprovalCount()),
            'pageTitle' => $pageTitle,
            'accountHeading' => 'System',
            'settingsHref' => route('pto.settings'),
        ], $data));
    }

    /**
     * Establishments (not images) with at least one Pending photo routed
     * to PTO — LGU-sourced uploads, province-wide (App\Policies\
     * ImagePolicy::approve() routing, mirrored here for the badge count —
     * one card per establishment on the queue page, so the badge counts
     * cards, not photos).
     */
    private function _imageApprovalCount(): int
    {
        return EstablishmentImage::query()
            ->where('img_status', ImageStatus::Pending->value)
            ->where('img_source_role', ImageSourceRole::Lgu->value)
            ->distinct()
            ->count('listing_id');
    }
}
