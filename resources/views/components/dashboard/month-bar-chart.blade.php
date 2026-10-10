{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Simple, server-rendered monthly bar chart for the reporting
    modules (Establishment Monthly Reports overview, LGU Municipal
    Reports). One bar per month with its number printed on it so it can be
    read without hovering; a month with no verified report says "No report"
    instead of drawing an empty bar, so it is never mistaken for zero.
    Optional second series (previous year) for side-by-side comparison.
    Props:
      records         — TourismAnalytics::monthlyRecords() of the main year
      label           — legend text for the main year (e.g. "2026")
      compareRecords  — optional monthlyRecords() of the comparison year
      compareLabel    — legend text for the comparison year
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props([
    'records',
    'label',
    'compareRecords' => null,
    'compareLabel' => null,
])

@php
    $hasCompare = $compareRecords !== null;
    $maxValue = max(1, (int) $records->max('total'), $hasCompare ? (int) $compareRecords->max('total') : 0);
    $compareByMonth = $hasCompare ? $compareRecords->keyBy('month') : collect();
@endphp

<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <div class="flex items-center gap-4 text-xs text-sand-600">
        <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-primary-700"></span> {{ $label }}</span>
        @if ($hasCompare)
            <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-sand-300"></span> {{ $compareLabel }}</span>
        @endif
    </div>

    <div class="mt-3 grid min-w-[640px] grid-cols-12 items-end gap-2" role="img" aria-label="Monthly tourist arrivals, {{ $label }}">
        @foreach ($records as $record)
            @php($compare = $compareByMonth->get($record['month']))
            <div class="flex flex-col items-center">
                <div class="flex h-48 w-full items-end justify-center gap-1">
                    @if ($record['hasData'])
                        <div class="flex h-full w-full max-w-8 flex-col items-center justify-end">
                            <span class="mb-1 text-[11px] font-semibold text-sand-900">{{ number_format($record['total']) }}</span>
                            <div class="w-full rounded-t-sm bg-primary-700" style="height: {{ max(2, round($record['total'] / $maxValue * 85)) }}%"></div>
                        </div>
                    @else
                        <div class="flex h-full w-full max-w-8 items-end justify-center">
                            <span class="mb-1 text-center text-[10px] leading-tight text-sand-400">No<br>report</span>
                        </div>
                    @endif

                    @if ($hasCompare)
                        <div class="flex h-full w-full max-w-8 flex-col items-center justify-end">
                            @if ($compare && $compare['hasData'])
                                <span class="mb-1 text-[10px] text-sand-500">{{ number_format($compare['total']) }}</span>
                                <div class="w-full rounded-t-sm bg-sand-300" style="height: {{ max(2, round($compare['total'] / $maxValue * 85)) }}%"></div>
                            @endif
                        </div>
                    @endif
                </div>
                <span class="mt-2 border-t border-sand-200 pt-1 text-xs font-semibold text-sand-600">{{ $record['shortLabel'] }}</span>
            </div>
        @endforeach
    </div>
</div>
