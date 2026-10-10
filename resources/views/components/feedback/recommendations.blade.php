{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Suggested Improvements for one destination or establishment
    (Objective 4). The predefined recommendations appear only when negative
    feedback dominates with the minimum sample (FeedbackRecommendationService);
    otherwise the reason is stated instead of a recommendation.
    Props:
      report — FeedbackAnalyticsService::listingReport() result
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['report'])

<div {{ $attributes->merge(['class' => 'dashboard-panel']) }}>
    <h2 class="dashboard-panel-title">Suggested Improvements</h2>

    @if (count($report['improvements']))
        <p class="text-xs text-sand-500">Negative feedback dominates, so these predefined improvements apply to its common concerns.</p>
        <ul class="mt-3 list-disc space-y-1.5 pl-5 text-sm text-sand-800">
            @foreach ($report['improvements'] as $strImprovement)
                <li>{{ $strImprovement }}</li>
            @endforeach
        </ul>
    @elseif (! $report['has_minimum_sample'])
        <p class="mt-2 text-sm text-sand-600">
            None yet — at least {{ (int) config('tourist_feedback.minimum_sample') }} analyzed reviews are needed before improvements are suggested
            ({{ $report['sentiment']['analyzed'] }} so far).
        </p>
    @elseif ($report['is_negative_dominant'])
        <p class="mt-2 text-sm text-sand-600">None — negative feedback dominates, but no specific concern with a predefined improvement was identified.</p>
    @else
        <p class="mt-2 text-sm text-sand-600">None — negative feedback does not outnumber positive and neutral feedback.</p>
    @endif
</div>
