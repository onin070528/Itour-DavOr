{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: LGU tourist feedback list (Objective 4) — feedback for
    destinations and establishments in the assigned municipality only, with
    each entry's processing status, original text, and translation.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Tourist Feedback"
        description="Feedback for destinations and establishments in {{ $municipality }}."
    >
        <x-slot:actions>
            <a href="{{ route('lgu.feedback.analytics', request()->only(['period', 'from', 'to', 'type'])) }}" class="inline-flex items-center gap-2 rounded-sm bg-accent-500 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-accent-600">
                <i class="ti ti-heart-handshake" aria-hidden="true"></i>
                Experience Analytics
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <x-feedback.filters
        :period="$period"
        :action="route('lgu.feedback.index')"
        :show-type="true"
        :type="$type"
        :show-status="true"
        :status="$status"
        :sentiment="$sentimentFilter"
    />

    <x-feedback.status-summary :status-counts="$statusCounts" />
    <x-feedback.entries :entries="$entries" />
</x-layouts.dashboard>
