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
     * @param  array<string, mixed>  $arrData
     */
    protected function renderLgu(Request $objRequest, string $strView, string $strActiveKey, string $strPageTitle, array $arrData = []): View
    {
        $objUser = $objRequest->user();

        return view($strView, array_merge([
            'user' => $objUser,
            'municipality' => $objUser->usr_organization_subtitle,
            'navSections' => DashboardNavigation::sections($objUser, $strActiveKey, $this->_imageApprovalCount($objUser)),
            'pageTitle' => $strPageTitle,
            'accountHeading' => 'System',
            'settingsHref' => route('lgu.settings'),
        ], $arrData));
    }

    /**
     * Establishments (not images) with at least one Pending photo routed
     * to this LGU — Establishment-sourced uploads within its own
     * municipality only (App\Policies\ImagePolicy::approve() routing,
     * mirrored here for the badge count — one card per establishment on
     * the queue page, so the badge counts cards, not photos).
     */
    private function _imageApprovalCount(User $objUser): int
    {
        if ($objUser->mun_id === null) {
            return 0;
        }

        return EstablishmentImage::query()
            ->where('img_status', ImageStatus::Pending->value)
            ->where('img_source_role', ImageSourceRole::Establishment->value)
            ->whereHas('listing', fn ($objQuery) => $objQuery->where('mun_id', $objUser->mun_id))
            ->distinct()
            ->count('lst_id');
    }
}
