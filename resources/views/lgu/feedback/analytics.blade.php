{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: LGU Tourist Experience Analytics (Objective 4) — the
    municipality overview: processing status, sentiment, trend, common
    concerns, and per-destination / per-establishment reports.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Tourist Experience Analytics"
        description="Lexicon-based sentiment results from tourist feedback in {{ $municipality }}."
    >
        <x-slot:actions>
            <a href="{{ route('lgu.feedback.index', request()->only(['period', 'from', 'to', 'type'])) }}" class="btn-secondary">
                <i class="ti ti-messages" aria-hidden="true"></i>
                All Feedback
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <x-feedback.filters :period="$period" :action="route('lgu.feedback.analytics')" :show-type="true" :type="$type" />

    <x-feedback.overview
        :status-counts="$statusCounts"
        :sentiment="$sentiment"
        :trend="$trend"
        :concerns="$concerns"
        :listing-summaries="$listingSummaries"
        listing-route="lgu.feedback.listing"
    />
</x-layouts.dashboard>
