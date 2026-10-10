{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Monthly sentiment trend of analyzed tourist feedback
    (Objective 4): one stacked bar per month (positive / neutral / negative)
    with its numbers printed, server-rendered. A month without analyzed
    feedback says "No feedback" instead of drawing a zero, so it is never
    mistaken for poor satisfaction.
    Props:
      trend — FeedbackAnalyticsService::monthlyTrend() result
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['trend'])

@php
    $blnHasData = collect($trend)->sum('total') > 0;
@endphp

<div {{ $attributes->merge(['class' => 'dashboard-panel']) }}>
    <h2 class="dashboard-panel-title">Sentiment Trend</h2>
    <p class="text-xs text-sand-500">Analyzed feedback per month, up to the last 12 months of the period</p>

    @if ($blnHasData)
        <ul class="mt-4 flex flex-col gap-2.5">
            @foreach ($trend as $arrMonth)
                <li class="grid grid-cols-[4.5rem_1fr] items-center gap-3 sm:grid-cols-[4.5rem_1fr_9rem]">
                    <span class="text-xs font-semibold text-sand-700">{{ $arrMonth['label'] }}</span>
                    @if ($arrMonth['total'] > 0)
                        <div class="flex h-2.5 overflow-hidden rounded-full bg-sand-100" role="img" aria-label="{{ $arrMonth['label'] }}: {{ $arrMonth['positive'] }} positive, {{ $arrMonth['neutral'] }} neutral, {{ $arrMonth['negative'] }} negative">
                            <div class="h-full bg-success" style="width: {{ $arrMonth['positive'] / $arrMonth['total'] * 100 }}%"></div>
                            <div class="h-full bg-warning" style="width: {{ $arrMonth['neutral'] / $arrMonth['total'] * 100 }}%"></div>
                            <div class="h-full bg-danger" style="width: {{ $arrMonth['negative'] / $arrMonth['total'] * 100 }}%"></div>
                        </div>
                        <span class="col-start-2 text-xs text-sand-600 sm:col-start-auto sm:text-right">{{ $arrMonth['total'] }} analyzed · {{ $arrMonth['positive_pct'] }}% positive</span>
                    @else
                        <span class="text-xs text-sand-400 sm:col-span-2">No feedback</span>
                    @endif
                </li>
            @endforeach
        </ul>
        <div class="mt-3 flex flex-wrap gap-3 text-[11px] text-sand-500">
            <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-success" aria-hidden="true"></span>Positive</span>
            <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-warning" aria-hidden="true"></span>Neutral</span>
            <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-danger" aria-hidden="true"></span>Negative</span>
        </div>
    @else
        <p class="mt-4 text-sm text-sand-500">No analyzed feedback in these months yet.</p>
    @endif
</div>
