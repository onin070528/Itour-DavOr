<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-audit-logs.page
        :role="$user->role"
        :tab="$tab"
        :filters="$filters"
        :rows="$rows"
        :security-summary="$securitySummary"
        :operation-summary="$operationSummary"
        :municipalities="$municipalities"
        :subtitle="$subtitle"
        base-route-name="establishment.activityLog"
        security-tab-label="My Login History"
        operation-tab-label="Activity"
    />
</x-layouts.dashboard>
