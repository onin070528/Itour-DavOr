{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Establishment tourist feedback list (Objective 4) — feedback
    about this account's own linked listing only. Read-only.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="Tourist Feedback"
        description="What tourists say about {{ $ownListing?->lst_name ?? $establishmentName }}."
    >
        <x-slot:actions>
            <a href="{{ route('establishment.feedback.analytics', request()->only(['period', 'from', 'to'])) }}" class="inline-flex items-center gap-2 rounded-sm bg-accent-500 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-accent-600">
                <i class="ti ti-heart-handshake" aria-hidden="true"></i>
                Experience Analytics
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    @if ($ownListing === null)
        <x-dashboard.empty-state
            class="mt-6"
            icon="ti-link-off"
            title="No establishment linked"
            description="This account is not linked to an establishment listing, so there is no feedback to show. Contact your LGU tourism office."
        />
    @else
        <x-feedback.filters :period="$period" :action="route('establishment.feedback.index')" :show-status="true" :status="$status" :sentiment="$sentimentFilter" />

        <x-feedback.status-summary :status-counts="$statusCounts" />
        <x-feedback.entries :entries="$entries" :show-listing="false" />
    @endif
</x-layouts.dashboard>
