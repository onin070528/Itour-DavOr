{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Public section heading with eyebrow text.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['eyebrow' => null, 'description' => null, 'actionLabel' => null, 'actionHref' => null, 'actionId' => null])

<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="max-w-xl">
        @if ($eyebrow)
            <p class="text-xs font-bold tracking-widest text-accent-700 uppercase">{{ $eyebrow }}</p>
        @endif
        <h2 class="mt-2 text-2xl sm:text-3xl">{{ $slot }}</h2>
        @if ($description)
            <p class="mt-3 text-sm leading-relaxed text-sand-600 sm:text-base">{{ $description }}</p>
        @endif
    </div>

    @if ($actionLabel && $actionHref)
        <a href="{{ $actionHref }}" @if ($actionId) id="{{ $actionId }}" @endif class="inline-flex shrink-0 items-center gap-1.5 text-sm font-semibold text-primary-700 transition-colors hover:text-primary-900">
            <span data-action-label>{{ $actionLabel }}</span>
            <i data-action-icon class="ti ti-arrow-right" aria-hidden="true"></i>
        </a>
    @endif
</div>
