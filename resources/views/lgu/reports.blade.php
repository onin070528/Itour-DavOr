<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Reports"
        description="View, analyze, and generate tourism reports."
    />

    <div class="mt-6">
        <x-dashboard.report-workspace
            :user="$user"
            :report-types="$reportTypes"
            :preview-data="$previewData"
            :filter-options="$filterOptions"
        />

        <x-dashboard.report-history-table :history="$history" />
    </div>
</x-layouts.dashboard>
