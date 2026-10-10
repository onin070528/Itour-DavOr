{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: PTO report for one destination or establishment (Objective 4):
    its Tourist Experience analytics and feedback entries.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <a href="{{ route('pto.feedback.analytics', request()->only(['period', 'from', 'to'])) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:text-primary-900">
        <i class="ti ti-arrow-left" aria-hidden="true"></i>
        Back to Sentiment Analytics
    </a>

    <div class="mt-3">
        <x-dashboard.page-header
            :title="$listing->lst_name"
            :description="($listing->isDestinationOnly() ? 'Destination' : 'Establishment').' · '.$listing->categoryName().' · '.$listing->lst_municipality.' · '.$listingStatus"
        />
    </div>

    <x-feedback.filters :period="$period" :action="route('pto.feedback.listing', $listing)" :show-status="true" :status="$status" :sentiment="$sentimentFilter" />

    <x-feedback.listing-report :report="$report" />

    <h2 class="mt-8 font-display text-lg font-bold text-sand-900">Feedback</h2>
    <x-feedback.entries :entries="$entries" :show-listing="false" />
</x-layouts.dashboard>
