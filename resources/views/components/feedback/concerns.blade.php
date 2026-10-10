{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Common Concerns (Objective 4) — recurring issue categories in
    analyzed negative feedback, ranked by how many reviews mention them.
    Props:
      concerns — ranked [category, count] rows (FeedbackRecommendationService)
      title    — panel title
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['concerns', 'title' => 'Common Concerns'])

@php
    $intMax = max(1, (int) collect($concerns)->max('count'));
@endphp

<div {{ $attributes->merge(['class' => 'dashboard-panel']) }}>
    <h2 class="dashboard-panel-title">{{ $title }}</h2>
    <p class="text-xs text-sand-500">Issues found in negative feedback, by number of reviews</p>

    @if (count($concerns))
        <ol class="mt-4 flex flex-col gap-3">
            @foreach ($concerns as $arrConcern)
                <li class="flex items-center gap-3">
                    <span class="w-36 shrink-0 truncate text-sm text-sand-700">{{ $arrConcern['category'] }}</span>
                    <div class="h-2.5 flex-1 rounded-full bg-sand-100" aria-hidden="true">
                        <div class="h-2.5 rounded-full bg-danger" style="width: {{ max(4, round($arrConcern['count'] / $intMax * 100)) }}%"></div>
                    </div>
                    <span class="w-8 shrink-0 text-right text-sm font-semibold text-sand-800">{{ $arrConcern['count'] }}</span>
                </li>
            @endforeach
        </ol>
    @else
        <p class="mt-4 text-sm text-sand-500">No recurring concerns in negative feedback for this period.</p>
    @endif
</div>
