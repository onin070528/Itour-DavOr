{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Positive / neutral / negative distribution of analyzed tourist
    feedback (Objective 4), reusing the dashboard donut chart. With no
    analyzed feedback it says so instead of drawing an empty chart.
    Props:
      sentiment — FeedbackAnalyticsService::sentimentSummary() result
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['sentiment'])

<div {{ $attributes->merge(['class' => 'dashboard-panel']) }}>
    <h2 class="dashboard-panel-title">Overall Sentiment</h2>
    <p class="text-xs text-sand-500">{{ number_format($sentiment['analyzed']) }} analyzed {{ \Illuminate\Support\Str::plural('review', $sentiment['analyzed']) }}</p>

    @if ($sentiment['analyzed'] > 0)
        <div class="mt-4 flex flex-wrap items-center gap-5">
            <x-dashboard.donut-chart
                :segments="[
                    ['label' => 'Positive', 'value' => $sentiment['positive'], 'color' => 'var(--color-success)'],
                    ['label' => 'Neutral', 'value' => $sentiment['neutral'], 'color' => 'var(--color-warning)'],
                    ['label' => 'Negative', 'value' => $sentiment['negative'], 'color' => 'var(--color-danger)'],
                ]"
                :center-label="$sentiment['positive_pct'].'%'"
                center-sublabel="Positive"
            />
            <dl class="flex min-w-40 flex-col gap-2 text-xs">
                @foreach (['positive' => ['Positive', 'bg-success'], 'neutral' => ['Neutral', 'bg-warning'], 'negative' => ['Negative', 'bg-danger']] as $strKey => [$strLabel, $strDot])
                    <div class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full {{ $strDot }}" aria-hidden="true"></span>
                        <dt>{{ $strLabel }}</dt>
                        <dd class="ml-auto font-semibold text-sand-800">{{ number_format($sentiment[$strKey]) }} <span class="font-normal text-sand-500">({{ $sentiment[$strKey.'_pct'] }}%)</span></dd>
                    </div>
                @endforeach
                <div class="flex items-center gap-1.5 border-t border-sand-100 pt-2">
                    <dt>Average score</dt>
                    <dd class="ml-auto font-semibold text-sand-800">{{ number_format((float) $sentiment['average_score'], 2) }}</dd>
                </div>
            </dl>
        </div>
    @else
        <p class="mt-4 text-sm text-sand-500">No analyzed feedback for this period yet.</p>
    @endif
</div>
