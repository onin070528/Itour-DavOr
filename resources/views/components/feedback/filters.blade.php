{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Filter bar for the tourist feedback pages (Objective 4): the
    reporting period (All Time, This Month, Last Month, This Year, Custom
    Range by submission date), and optionally the listing type and the
    processing status / sentiment. A plain GET form — every value is
    re-checked on the server (FeedbackAnalyticsService::resolvePeriod(),
    ShowsFeedbackAnalytics) and can never widen the user's scope.
    Props:
      period        — FeedbackAnalyticsService::resolvePeriod() result
      action        — URL the form submits to (the current page)
      showType      — show the Destinations / Establishments filter
      type          — selected listing type
      showStatus    — show the status and sentiment filters
      status        — selected processing status (or null)
      sentiment     — selected sentiment (or null)
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['period', 'action', 'showType' => false, 'type' => 'all', 'showStatus' => false, 'status' => null, 'sentiment' => null])

@php
    $blnIsCustom = $period['key'] === 'custom';
@endphp

<form method="GET" action="{{ $action }}" class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="feedback-filter-period" class="form-label">Reporting period</label>
            <select id="feedback-filter-period" name="period" data-auto-submit class="form-input">
                @foreach (\App\Services\FeedbackAnalyticsService::PERIODS as $strKey => $strLabel)
                    <option value="{{ $strKey }}" @selected($period['key'] === $strKey)>{{ $strLabel }}</option>
                @endforeach
            </select>
        </div>

        @if ($blnIsCustom)
            <div>
                <label for="feedback-filter-from" class="form-label">From</label>
                <input id="feedback-filter-from" type="date" name="from" value="{{ $period['fromInput'] }}" max="{{ today()->toDateString() }}" class="form-input">
            </div>
            <div>
                <label for="feedback-filter-to" class="form-label">To</label>
                <input id="feedback-filter-to" type="date" name="to" value="{{ $period['toInput'] }}" class="form-input">
            </div>
        @endif

        @if ($showType)
            <div>
                <label for="feedback-filter-type" class="form-label">Listing type</label>
                <select id="feedback-filter-type" name="type" data-auto-submit class="form-input">
                    @foreach (\App\Services\FeedbackAnalyticsService::LISTING_TYPES as $strKey => $strLabel)
                        <option value="{{ $strKey }}" @selected($type === $strKey)>{{ $strLabel }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if ($showStatus)
            <div>
                <label for="feedback-filter-status" class="form-label">Processing status</label>
                <select id="feedback-filter-status" name="status" data-auto-submit class="form-input">
                    <option value="">All statuses</option>
                    @foreach (\App\Enums\FeedbackAnalysisStatus::cases() as $objStatus)
                        <option value="{{ $objStatus->value }}" @selected($status === $objStatus->value)>{{ $objStatus->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="feedback-filter-sentiment" class="form-label">Sentiment</label>
                <select id="feedback-filter-sentiment" name="sentiment" data-auto-submit class="form-input">
                    <option value="">All sentiments</option>
                    @foreach (\App\Enums\SentimentClassification::cases() as $objClass)
                        <option value="{{ $objClass->value }}" @selected($sentiment === $objClass->value)>{{ $objClass->label() }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-sand-500">
            Showing <span class="font-semibold text-sand-700">{{ $period['label'] }}</span> · by submission date (Philippine time)
            @if ($blnIsCustom && $period['from'] === null && ! $period['error'])
                — choose a start and end date.
            @endif
        </p>
        <button type="submit" class="btn-small">Apply</button>
    </div>

    @if ($period['error'])
        <p class="form-error" role="alert">{{ $period['error'] }}</p>
    @endif
</form>
