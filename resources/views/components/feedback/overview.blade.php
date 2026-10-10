{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Tourist Experience Analytics overview (Objective 4), shared by the
    PTO and LGU analytics pages: processing status, sentiment distribution,
    monthly trend, common concerns, and the per-listing reports — separate
    tables for destinations and establishments.
    Props:
      statusCounts, sentiment, trend, concerns, listingSummaries — from
        ShowsFeedbackAnalytics::feedbackOverviewData()
      listingRoute — route name of the per-listing report
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['statusCounts', 'sentiment', 'trend', 'concerns', 'listingSummaries', 'listingRoute'])

@php
    $colDestinations = $listingSummaries->where('type', 'Destination')->values();
    $colEstablishments = $listingSummaries->where('type', 'Establishment')->values();
@endphp

<x-feedback.status-summary :status-counts="$statusCounts" />

@if ($statusCounts['total'] === 0)
    <x-dashboard.empty-state
        class="mt-6"
        icon="ti-message-2"
        title="No feedback in this period"
        description="Tourist feedback will appear here once it is submitted."
    />
@else
    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-feedback.sentiment-overview :sentiment="$sentiment" />
        <x-feedback.sentiment-trend :trend="$trend" class="lg:col-span-2" />
    </div>

    <x-feedback.concerns :concerns="$concerns" class="mt-6" />

    @if ($colDestinations->isNotEmpty())
        <x-feedback.listing-table :rows="$colDestinations" title="Sentiment by Destination" :listing-route="$listingRoute" class="mt-6" />
    @endif

    @if ($colEstablishments->isNotEmpty())
        <x-feedback.listing-table :rows="$colEstablishments" title="Sentiment by Establishment" :listing-route="$listingRoute" class="mt-6" />
    @endif

    <p class="mt-3 text-xs text-sand-500">
        Suggested improvements appear only for a destination or establishment whose negative feedback outnumbers both its positive and neutral feedback, with at least {{ (int) config('tourist_feedback.minimum_sample') }} analyzed reviews.
    </p>
@endif
