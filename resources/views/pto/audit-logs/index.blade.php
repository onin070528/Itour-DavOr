{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: PTO province-wide Audit Logs page.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-audit-logs.page
        :role="$user->usr_role"
        :tab="$tab"
        :filters="$filters"
        :rows="$rows"
        :security-summary="$securitySummary"
        :operation-summary="$operationSummary"
        :municipalities="$municipalities"
        :subtitle="$subtitle"
        base-route-name="pto.auditLogs"
        export-route-name="pto.auditLogs.export"
    />
</x-layouts.dashboard>
