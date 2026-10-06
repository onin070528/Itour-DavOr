{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: LGU Audit Logs page for the account's municipality.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-audit-logs.page
        :role="$user->usr_role"
        :tab="$tab"
        :filters="$filters"
        :rows="$rows"
        :security-summary="$securitySummary"
        :operation-summary="$operationSummary"
        :municipalities="$municipalities"
        :subtitle="$subtitle"
        base-route-name="lgu.auditLogs"
        export-route-name="lgu.auditLogs.export"
    />
</x-layouts.dashboard>
