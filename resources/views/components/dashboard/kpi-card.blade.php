{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Dashboard KPI card — label, value, delta and tone.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['label', 'value', 'delta' => null, 'tone' => 'neutral', 'href' => null])

@php
    $deltaColor = match ($tone) {
        'success' => 'text-success',
        'warning' => 'text-warning',
        'danger' => 'text-danger',
        default => 'text-sand-500',
    };
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif class="block rounded-md border border-sand-200 bg-sand-0 p-4 {{ $href ? 'transition-colors hover:border-primary-300' : '' }}">
    <p class="text-xs font-medium text-sand-500">{{ $label }}</p>
    <p class="mt-1.5 font-display text-2xl font-extrabold text-sand-900">{{ $value }}</p>
    @if ($delta)
        <p class="mt-1 flex items-center gap-1 text-xs font-semibold {{ $deltaColor }}">{{ $delta }}</p>
    @endif
</{{ $tag }}>
