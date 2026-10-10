{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Processing-status counts for tourist feedback (Objective 4).
    Makes clear that only Analyzed feedback is counted in sentiment results;
    pending, failed, and rejected rows are shown here and nowhere else.
    Props:
      statusCounts — FeedbackAnalyticsService::statusCounts() result
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['statusCounts'])

@php
    $arrStatuses = [
        ['key' => 'analyzed', 'label' => 'Analyzed', 'hint' => 'Counted in sentiment results', 'tone' => 'text-success'],
        ['key' => 'pending', 'label' => 'Pending', 'hint' => 'Waiting to be analyzed', 'tone' => 'text-sand-700'],
        ['key' => 'failed', 'label' => 'Failed', 'hint' => 'Translation failed; not counted', 'tone' => 'text-danger'],
        ['key' => 'rejected', 'label' => 'Rejected', 'hint' => 'Nothing to analyze or automated; not counted', 'tone' => 'text-warning'],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4']) }}>
    @foreach ($arrStatuses as $arrStatus)
        <div class="rounded-md border border-sand-200 bg-sand-0 p-4">
            <p class="text-xs font-semibold tracking-wide text-sand-500 uppercase">{{ $arrStatus['label'] }}</p>
            <p class="mt-1 font-display text-2xl font-bold {{ $arrStatus['tone'] }}">{{ number_format($statusCounts[$arrStatus['key']]) }}</p>
            <p class="mt-0.5 text-xs text-sand-500">{{ $arrStatus['hint'] }}</p>
        </div>
    @endforeach
</div>
<p class="mt-2 text-xs text-sand-500">
    {{ number_format($statusCounts['total']) }} {{ \Illuminate\Support\Str::plural('submission', $statusCounts['total']) }} in this period. Only analyzed feedback is included in sentiment results.
</p>
