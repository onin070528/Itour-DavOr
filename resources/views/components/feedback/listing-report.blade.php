{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: One destination's or establishment's Tourist Experience report
    (Objective 4), shared by the PTO/LGU drill-down and the establishment
    owner's Experience Analytics page: processing status, sentiment,
    monthly trend, common concerns, and suggested improvements.
    Props:
      report — FeedbackAnalyticsService::listingReport() result
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['report'])

<x-feedback.status-summary :status-counts="$report['status']" />

@if ($report['status']['total'] === 0)
    <x-dashboard.empty-state
        class="mt-6"
        icon="ti-message-2"
        title="No feedback in this period"
        description="Tourist feedback will appear here once it is submitted."
    />
@else
    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-feedback.sentiment-overview :sentiment="$report['sentiment']" />
        <x-feedback.sentiment-trend :trend="$report['trend']" class="lg:col-span-2" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-feedback.concerns :concerns="$report['concerns']" />
        <x-feedback.recommendations :report="$report" />
    </div>
@endif
