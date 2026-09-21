<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Builds the LGU dashboard's sidebar navigation tree.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

/**
 * Builds the LGU sidebar navigation tree. Deliberately smaller than the PTO
 * tree — no Map — reflecting the LGU's municipality-only access boundary.
 * "Users" here is scoped to Establishment accounts within the LGU's own
 * municipality only (Lgu\UsersController), unlike PTO's province-wide
 * Users page.
 *
 * @see PtoNavigation for the province-wide equivalent.
 */
class LguNavigation
{
    /**
     * @param  string  $active  Dot-path of the current page, e.g. "monitoring.arrivals".
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function sections(string $active): array
    {
        $item = fn (string $key, string $icon, string $label, string $route) => [
            'key' => $key,
            'icon' => $icon,
            'label' => $label,
            'href' => route($route),
            'active' => $active === $key,
            'soon' => false,
        ];

        $group = fn (string $key, string $icon, string $label, array $children) => [
            'key' => $key,
            'icon' => $icon,
            'label' => $label,
            'children' => $children,
            'active' => $active === $key || str_starts_with($active, "{$key}."),
        ];

        return [
            'Main' => [
                $item('dashboard', 'ti-layout-dashboard', 'Dashboard', 'lgu.dashboard'),
                $group('monitoring', 'ti-chart-line', 'Tourism Monitoring', [
                    $item('monitoring.arrivals', 'ti-users', 'Tourist Arrivals', 'lgu.monitoring.arrivals'),
                    $item('monitoring.statistics', 'ti-chart-bar', 'Visitation Statistics', 'lgu.monitoring.statistics'),
                    $item('monitoring.destinations', 'ti-trophy', 'Destination Performance', 'lgu.monitoring.destinations'),
                ]),
                $group('directory', 'ti-list-details', 'Tourism Directory', [
                    $item('directory.destinations', 'ti-map-pin', 'Destinations', 'lgu.directory.destinations'),
                    $item('directory.establishments', 'ti-building-store', 'Establishments', 'lgu.directory.establishments'),
                ]),
                $group('feedback', 'ti-message-2', 'Tourist Feedback', [
                    $item('feedback.index', 'ti-messages', 'All Feedback', 'lgu.feedback.index'),
                    $item('feedback.analytics', 'ti-heart-handshake', 'Experience Analytics', 'lgu.feedback.analytics'),
                ]),
                $item('reports', 'ti-file-report', 'Reports', 'lgu.reports'),
            ],
            'User Management' => [
                $item('users', 'ti-users-group', 'Users', 'lgu.users'),
            ],
        ];
    }
}
