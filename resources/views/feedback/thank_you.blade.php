{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Neutral thank-you page after a tourist feedback submission
    (Objective 4). It never shows a sentiment result, score, issue,
    recommendation, or translation.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.public title="Thank You" robots="noindex, nofollow">
    <div class="mx-auto max-w-xl px-4 py-16 sm:px-6">
        <div class="flex flex-col items-center rounded-md border border-sand-200 bg-sand-0 p-8 text-center shadow-sm" role="status">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-success-bg text-success">
                <i class="ti ti-circle-check text-3xl" aria-hidden="true"></i>
            </span>
            <h1 class="mt-4 font-display text-xl font-bold text-sand-900">Thank you for sharing your experience!</h1>
            <p class="mt-1.5 text-sm text-sand-600">Your feedback has been recorded.</p>

            <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('explore') }}" class="btn-primary justify-center">
                    <i class="ti ti-compass" aria-hidden="true"></i>
                    Keep Exploring
                </a>
                <a href="{{ route('feedback.create') }}" class="btn-secondary justify-center">Share Another Experience</a>
            </div>
        </div>
    </div>
</x-layouts.public>
