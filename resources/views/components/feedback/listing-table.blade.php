{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Aggregated sentiment report per destination or establishment
    (Objective 4): analyzed counts, positive / neutral / negative, average
    score, the most common concern, and suggested improvements (only when
    the recommendation rule is met). Each name opens that listing's report.
    Props:
      rows          — FeedbackAnalyticsService::listingSummaries() rows
      title         — table heading
      listingRoute  — route name of the per-listing report (null: no links)
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['rows', 'title', 'listingRoute' => null])

@php
    $arrPeriodQuery = request()->only(['period', 'from', 'to']);
    $intMinimumSample = (int) config('tourist_feedback.minimum_sample');
@endphp

<section {{ $attributes->merge(['class' => 'dashboard-panel']) }}>
    <h2 class="dashboard-panel-title">{{ $title }}</h2>

    <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[56rem] text-left text-sm">
            <thead>
                <tr class="border-b border-sand-200 text-xs font-semibold tracking-wide text-sand-500 uppercase">
                    <th scope="col" class="py-2 pr-3">Name</th>
                    <th scope="col" class="px-3 py-2 text-right">Analyzed</th>
                    <th scope="col" class="px-3 py-2 text-right">Positive</th>
                    <th scope="col" class="px-3 py-2 text-right">Neutral</th>
                    <th scope="col" class="px-3 py-2 text-right">Negative</th>
                    <th scope="col" class="px-3 py-2 text-right">Avg. score</th>
                    <th scope="col" class="px-3 py-2">Common concern</th>
                    <th scope="col" class="py-2 pl-3">Suggested improvement</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sand-100">
                @foreach ($rows as $arrRow)
                    <tr class="align-top">
                        <td class="py-3 pr-3">
                            @if ($listingRoute && $arrRow['listing'])
                                <a href="{{ route($listingRoute, array_merge(['listing' => $arrRow['listing']->lst_slug], $arrPeriodQuery)) }}" class="font-semibold text-sand-900 hover:text-primary-700">{{ $arrRow['name'] }}</a>
                            @else
                                <span class="font-semibold text-sand-900">{{ $arrRow['name'] }}</span>
                            @endif
                            <p class="text-xs text-sand-500">{{ $arrRow['category'] }}{{ $arrRow['municipality'] !== '' ? ' · '.$arrRow['municipality'] : '' }}</p>
                            @if ($arrRow['listing_status'] !== 'Published')
                                <div class="mt-1"><x-dashboard.status-badge tone="neutral">{{ $arrRow['listing_status'] }}</x-dashboard.status-badge></div>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-right font-semibold text-sand-800">
                            {{ $arrRow['sentiment']['analyzed'] }}
                            @if ($arrRow['feedback_count'] > $arrRow['sentiment']['analyzed'])
                                <p class="text-[11px] font-normal text-sand-500">{{ $arrRow['feedback_count'] - $arrRow['sentiment']['analyzed'] }} not analyzed</p>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-right text-success">{{ $arrRow['sentiment']['positive'] }}</td>
                        <td class="px-3 py-3 text-right text-warning">{{ $arrRow['sentiment']['neutral'] }}</td>
                        <td class="px-3 py-3 text-right text-danger">{{ $arrRow['sentiment']['negative'] }}</td>
                        <td class="px-3 py-3 text-right text-sand-800">{{ $arrRow['sentiment']['average_score'] !== null ? number_format($arrRow['sentiment']['average_score'], 2) : '—' }}</td>
                        <td class="px-3 py-3 text-sand-700">{{ $arrRow['top_concern'] ?? 'None' }}</td>
                        <td class="py-3 pl-3 text-sand-700">
                            @if (count($arrRow['improvements']))
                                {{ $arrRow['improvements'][0] }}
                                @if (count($arrRow['improvements']) > 1)
                                    <span class="text-xs text-sand-500">+{{ count($arrRow['improvements']) - 1 }} more</span>
                                @endif
                            @elseif (! $arrRow['has_minimum_sample'])
                                <span class="text-xs text-sand-500">None yet (fewer than {{ $intMinimumSample }} analyzed)</span>
                            @else
                                None
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
