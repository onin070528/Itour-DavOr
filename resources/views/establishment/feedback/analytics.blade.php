{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Establishment Tourist Experience Analytics (Objective 4) — the
    report for this account's own linked listing only.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="Tourist Experience Analytics"
        description="How tourists experience {{ $ownListing?->lst_name ?? $establishmentName }}, based on lexicon-based analysis of their feedback."
    >
        <x-slot:actions>
            <a href="{{ route('establishment.feedback.index', request()->only(['period', 'from', 'to'])) }}" class="btn-secondary">
                <i class="ti ti-messages" aria-hidden="true"></i>
                All Feedback
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    @if ($ownListing === null)
        <x-dashboard.empty-state
            class="mt-6"
            icon="ti-link-off"
            title="No establishment linked"
            description="This account is not linked to an establishment listing, so there are no analytics to show. Contact your LGU tourism office."
        />
    @else
        @if ($listingStatus !== 'Published')
            <p class="mt-4 rounded-sm border border-sand-200 bg-sand-0 px-3.5 py-2.5 text-sm text-sand-700">
                Your listing is currently {{ strtolower($listingStatus) }}, so it is not accepting new feedback. Earlier feedback is still shown.
            </p>
        @endif

        <x-feedback.filters :period="$period" :action="route('establishment.feedback.analytics')" />

        <x-feedback.listing-report :report="$report" />
    @endif
</x-layouts.dashboard>
