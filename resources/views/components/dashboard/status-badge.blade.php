{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Coloured status badge (success, warning, danger, info, neutral).
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['tone' => 'neutral'])

@php
    $classes = match ($tone) {
        'success' => 'bg-success-bg text-success',
        'warning' => 'bg-warning-bg text-warning',
        'danger' => 'bg-danger-bg text-danger',
        'info' => 'bg-primary-100 text-primary-700',
        default => 'bg-sand-200 text-sand-700',
    };
@endphp

<span class="inline-flex items-center gap-1.5 rounded-sm px-2 py-0.5 text-xs font-semibold {{ $classes }}">
    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
    {{ $slot }}
</span>
