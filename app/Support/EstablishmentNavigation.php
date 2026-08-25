<?php

namespace App\Support;

/**
 * Builds the Establishment sidebar navigation tree — the smallest of the
 * three authenticated roles, since an Establishment account only ever
 * manages its own establishment.
 *
 * @see PtoNavigation, LguNavigation for the province/municipality equivalents.
 */
class EstablishmentNavigation
{
    /**
     * @param  string  $active  Dot-path of the current page, e.g. "arrivals.record".
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
                $item('dashboard', 'ti-layout-dashboard', 'Dashboard', 'establishment.dashboard'),
                $group('establishment', 'ti-building-store', 'My Establishment', [
                    $item('establishment.profile', 'ti-info-circle', 'Establishment Profile', 'establishment.profile'),
                    $item('establishment.qr', 'ti-qrcode', 'QR Code', 'establishment.qr'),
                ]),
                $group('arrivals', 'ti-users', 'Tourist Arrivals', [
                    $item('arrivals.record', 'ti-send', 'Record Arrival', 'establishment.arrivals.record'),
                    $item('arrivals.index', 'ti-list-details', 'Arrival Records', 'establishment.arrivals.index'),
                ]),
                $item('statistics', 'ti-chart-bar', 'Tourism Statistics', 'establishment.statistics'),
                $group('feedback', 'ti-message-2', 'Tourist Feedback', [
                    $item('feedback.index', 'ti-messages', 'All Feedback', 'establishment.feedback.index'),
                    $item('feedback.analytics', 'ti-heart-handshake', 'Experience Analytics', 'establishment.feedback.analytics'),
                ]),
                $item('reports', 'ti-file-report', 'Reports', 'establishment.reports'),
            ],
        ];
    }
}
