{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: PTO Tourist Feedback page (Objective 4) — Reviews (every
    province-wide feedback entry with its processing status) and Sentiment
    Analytics (the province-wide overview), as two link tabs.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $arrKeptFilters = request()->only(['period', 'from', 'to', 'type']);
    $strAction = $activeTab === 'index' ? route('pto.feedback.index') : route('pto.feedback.analytics');
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Tourist Feedback"
        description="Multilingual tourist feedback for destinations and establishments across Davao Oriental, scored with the lexicon-based polarity method."
    />

    <x-dashboard.tabs :tabs="[
        ['label' => 'Reviews', 'icon' => 'ti-messages', 'active' => $activeTab === 'index', 'href' => route('pto.feedback.index', $arrKeptFilters)],
        ['label' => 'Sentiment Analytics', 'icon' => 'ti-heart-handshake', 'active' => $activeTab === 'analytics', 'href' => route('pto.feedback.analytics', $arrKeptFilters)],
    ]" />

    <x-feedback.filters
        :period="$period"
        :action="$strAction"
        :show-type="true"
        :type="$type"
        :show-status="$activeTab === 'index'"
        :status="$status"
        :sentiment="$sentimentFilter"
    />

    @if ($activeTab === 'index')
        <x-feedback.status-summary :status-counts="$statusCounts" />
        <x-feedback.entries :entries="$entries" />
    @else
        <x-feedback.overview
            :status-counts="$statusCounts"
            :sentiment="$sentiment"
            :trend="$trend"
            :concerns="$concerns"
            :listing-summaries="$listingSummaries"
            listing-route="pto.feedback.listing"
        />
    @endif
</x-layouts.dashboard>
