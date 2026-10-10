{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: LGU report for one destination or establishment in its own
    municipality (Objective 4): Tourist Experience analytics and entries.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <a href="{{ route('lgu.feedback.analytics', request()->only(['period', 'from', 'to'])) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:text-primary-900">
        <i class="ti ti-arrow-left" aria-hidden="true"></i>
        Back to Experience Analytics
    </a>

    <div class="mt-3">
        <x-dashboard.page-header
            :title="$listing->lst_name"
            :description="($listing->isDestinationOnly() ? 'Destination' : 'Establishment').' · '.$listing->categoryName().' · '.$listing->lst_municipality.' · '.$listingStatus"
        />
    </div>

    <x-feedback.filters :period="$period" :action="route('lgu.feedback.listing', $listing)" :show-status="true" :status="$status" :sentiment="$sentimentFilter" />

    <x-feedback.listing-report :report="$report" />

    <h2 class="mt-8 font-display text-lg font-bold text-sand-900">Feedback</h2>
    <x-feedback.entries :entries="$entries" :show-listing="false" />
</x-layouts.dashboard>
