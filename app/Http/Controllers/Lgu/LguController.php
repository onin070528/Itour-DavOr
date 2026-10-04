<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Base controller shared by every LGU-role controller; renders
 * pages with the LGU sidebar chrome and the account's municipality pre-wired.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Http\Controllers\Controller;
use App\Models\EstablishmentImage;
use App\Models\User;
use App\Support\DashboardNavigation;
use Illuminate\Http\Request;
use Illuminate\View\View;

abstract class LguController extends Controller
{
    /**
     * Render an LGU page with the sidebar nav, active state, and shared
     * sidebar chrome already wired up, plus the current user's assigned
     * municipality — every LGU page is scoped to it.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderLgu(Request $request, string $view, string $activeKey, string $pageTitle, array $data = []): View
    {
        $user = $request->user();

        return view($view, array_merge([
            'user' => $user,
            'municipality' => $user->organization_subtitle,
            'navSections' => DashboardNavigation::sections($user, $activeKey, $this->_imageApprovalCount($user)),
            'pageTitle' => $pageTitle,
            'accountHeading' => 'System',
            'settingsHref' => route('lgu.settings'),
        ], $data));
    }

    /**
     * Establishments (not images) with at least one Pending photo routed
     * to this LGU — Establishment-sourced uploads within its own
     * municipality only (App\Policies\ImagePolicy::approve() routing,
     * mirrored here for the badge count — one card per establishment on
     * the queue page, so the badge counts cards, not photos).
     */
    private function _imageApprovalCount(User $user): int
    {
        if ($user->municipality_id === null) {
            return 0;
        }

        return EstablishmentImage::query()
            ->where('img_status', ImageStatus::Pending->value)
            ->where('img_source_role', ImageSourceRole::Establishment->value)
            ->whereHas('listing', fn ($query) => $query->where('municipality_id', $user->municipality_id))
            ->distinct()
            ->count('listing_id');
    }
}
