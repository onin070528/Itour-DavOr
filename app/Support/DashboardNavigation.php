<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Single, shared builder for every role's dashboard sidebar —
 * replaces the separate PtoNavigation/LguNavigation/EstablishmentNavigation
 * trees with one structure driven by the signed-in user's role.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Models\User;

/**
 * Every role's sidebar is built from the same six-section vocabulary — Main,
 * Work, Reports, Feedback, Management, Administration — in that fixed
 * order; a role that has nothing for a section simply never adds to it, and
 * sections() drops any section left empty. Photos and the old, separate
 * "Photo Approvals" page are one nav item now: its badge is the count of
 * establishments waiting for THIS user's decision (0 shows no badge), and
 * the page itself carries an "All photos" / "Waiting for approval" tab.
 */
class DashboardNavigation
{
    /**
     * @param  string  $active  Dot-path of the current page, e.g. "monitoring.arrivals".
     * @param  int  $intImageApprovalCount  Establishments (not photos) waiting for this user's photo decision — 0 for a role that never approves.
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function sections(User $user, string $active, int $intImageApprovalCount = 0): array
    {
        $arrSections = match (true) {
            $user->isPto() => self::_ptoSections($active, $intImageApprovalCount),
            $user->isLgu() => self::_lguSections($active, $intImageApprovalCount),
            $user->isEstablishment() => self::_establishmentSections($active),
            default => [],
        };

        // Hide any section with no items (array_filter keeps key order).
        return array_filter($arrSections, fn (array $arrItems) => $arrItems !== []);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function _ptoSections(string $active, int $intImageApprovalCount): array
    {
        $item = self::_itemBuilder($active);
        $group = self::_groupBuilder($active);

        return [
            'Main' => [
                $item('dashboard', 'ti-layout-dashboard', 'Dashboard', 'pto.dashboard'),
            ],
            'Work' => [
                $item('directory', 'ti-list-details', 'Tourism Directory', 'pto.directory.index'),
                $item('images.index', 'ti-camera', 'Photos', 'pto.images.index', $intImageApprovalCount ?: null),
                $item('hotlines', 'ti-phone', 'Hotlines', 'pto.hotlines.index'),
                $item('announcements', 'ti-speakerphone', 'Announcements', 'pto.announcements.index'),
            ],
            'Reports' => [
                $item('municipalReports', 'ti-clipboard-check', 'LGU Submissions', 'pto.municipalReports.index'),
                $item('monthlyReports', 'ti-calendar-event', 'Provincial Reports', 'pto.monthlyReports.index'),
            ],
            'Feedback' => [
                $item('feedback', 'ti-message-2', 'Feedback & Analytics', 'pto.feedback.index'),
            ],
            'Management' => [
                $item('users', 'ti-users-group', 'Users', 'pto.users'),
            ],
            'Administration' => [
                $item('auditLogs', 'ti-shield-check', 'Audit Logs', 'pto.auditLogs'),
                $item('settings', 'ti-settings', 'Settings', 'pto.settings'),
            ],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function _lguSections(string $active, int $intImageApprovalCount): array
    {
        $item = self::_itemBuilder($active);
        $group = self::_groupBuilder($active);

        return [
            'Main' => [
                $item('dashboard', 'ti-layout-dashboard', 'Dashboard', 'lgu.dashboard'),
            ],
            'Work' => [
                $group('directory', 'ti-list-details', 'Tourism Directory', [
                    $item('directory.destinations', 'ti-map-pin', 'Destinations', 'lgu.directory.destinations'),
                    $item('directory.establishments', 'ti-building-store', 'Establishments', 'lgu.directory.establishments'),
                ]),
                $item('images.index', 'ti-camera', 'Photos', 'lgu.images.index', $intImageApprovalCount ?: null),
            ],
            'Reports' => [
                $item('monthlyReports', 'ti-calendar-event', 'Monthly Reports', 'lgu.monthlyReports.index'),
            ],
            'Feedback' => [
                $group('feedback', 'ti-message-2', 'Tourist Feedback', [
                    $item('feedback.index', 'ti-messages', 'All Feedback', 'lgu.feedback.index'),
                    $item('feedback.analytics', 'ti-heart-handshake', 'Experience Analytics', 'lgu.feedback.analytics'),
                ]),
            ],
            'Management' => [
                // Renamed from "Establishments" — the route (lgu.users) is
                // unchanged, only the label.
                $item('users', 'ti-users-group', 'Establishment Accounts', 'lgu.users'),
            ],
            'Administration' => [
                // Not named in the new role list, but kept reachable — no
                // instruction removed the Audit Logs page itself, and an
                // unreachable admin page would be a silent regression.
                $item('auditLogs', 'ti-shield-check', 'Audit Logs', 'lgu.auditLogs'),
            ],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function _establishmentSections(string $active): array
    {
        $item = self::_itemBuilder($active);
        $group = self::_groupBuilder($active);

        return [
            'Main' => [
                $item('dashboard', 'ti-layout-dashboard', 'Dashboard', 'establishment.dashboard'),
            ],
            'Work' => [
                // "Establishment Profile" isn't named in the new role list
                // either, but it's the only place to edit the
                // establishment's own details (and, since the Profile/
                // Photos merge, its own photos) — kept reachable here.
                $item('establishment.profile', 'ti-info-circle', 'Establishment Profile', 'establishment.profile'),
                $group('arrivals', 'ti-users', 'Arrival Recording', [
                    $item('arrivals.record', 'ti-send', 'Record Arrival', 'establishment.arrivals.record'),
                    $item('arrivals.index', 'ti-list-details', 'Arrival Records', 'establishment.arrivals.index'),
                ]),
                $item('establishment.qr', 'ti-qrcode', 'QR Code', 'establishment.qr'),
            ],
            'Reports' => [
                $item('arrivals.monthly', 'ti-calendar-event', 'Monthly Report', 'establishment.arrivals.monthly'),
            ],
            'Feedback' => [
                $group('feedback', 'ti-message-2', 'Feedback & Reviews', [
                    $item('feedback.index', 'ti-messages', 'All Feedback', 'establishment.feedback.index'),
                    $item('feedback.analytics', 'ti-heart-handshake', 'Experience Analytics', 'establishment.feedback.analytics'),
                ]),
            ],
            'System' => [
                $item('activityLog', 'ti-shield-check', 'Activity Log', 'establishment.activityLog'),
            ],
        ];
    }

    /**
     * @return \Closure(string, string, string, string, ?int=): array<string, mixed>
     */
    private static function _itemBuilder(string $active): \Closure
    {
        return fn (string $key, string $icon, string $label, string $route, ?int $intBadge = null) => [
            'key' => $key,
            'icon' => $icon,
            'label' => $label,
            'href' => route($route),
            'active' => $active === $key,
            'soon' => false,
            'badge' => $intBadge,
        ];
    }

    /**
     * @return \Closure(string, string, string, array): array<string, mixed>
     */
    private static function _groupBuilder(string $active): \Closure
    {
        return fn (string $key, string $icon, string $label, array $children) => [
            'key' => $key,
            'icon' => $icon,
            'label' => $label,
            'children' => $children,
            'active' => $active === $key || str_starts_with($active, "{$key}."),
        ];
    }
}
