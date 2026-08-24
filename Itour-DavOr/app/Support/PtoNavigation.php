<?php

namespace App\Support;

/**
 * Builds the PTO sidebar navigation tree and marks the active item/group
 * based on the current page's key, so every PTO controller stays in sync
 * without re-declaring this structure seven times.
 */
class PtoNavigation
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
                $item('dashboard', 'ti-layout-dashboard', 'Dashboard', 'pto.dashboard'),
                $group('monitoring', 'ti-chart-line', 'Tourism Monitoring', [
                    $item('monitoring.arrivals', 'ti-users', 'Tourist Arrivals', 'pto.monitoring.arrivals'),
                    $item('monitoring.statistics', 'ti-chart-bar', 'Visitation Statistics', 'pto.monitoring.statistics'),
                    $item('monitoring.destinations', 'ti-trophy', 'Destination Performance', 'pto.monitoring.destinations'),
                ]),
                $group('directory', 'ti-list-details', 'Tourism Directory', [
                    $item('directory.destinations', 'ti-map-pin', 'Destinations', 'pto.directory.destinations'),
                    $item('directory.establishments', 'ti-building-store', 'Establishments', 'pto.directory.establishments'),
                    $item('directory.map', 'ti-map', 'Map', 'pto.directory.map'),
                ]),
                $group('feedback', 'ti-message-2', 'Tourist Feedback', [
                    $item('feedback.index', 'ti-messages', 'All Feedback', 'pto.feedback.index'),
                    $item('feedback.analytics', 'ti-heart-handshake', 'Experience Analytics', 'pto.feedback.analytics'),
                ]),
                $item('reports', 'ti-file-report', 'Reports', 'pto.reports'),
            ],
            'User Management' => [
                $item('users', 'ti-users-group', 'Users', 'pto.users'),
            ],
        ];
    }
}
