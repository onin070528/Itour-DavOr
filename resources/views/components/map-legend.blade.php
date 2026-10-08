{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Legend for the public Mapbox maps — one swatch per marker kind, in the same colors the
    map script uses (resources/js/app.js MAP_MARKER_COLORS).
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['items' => []])

@php
    // Marker kind => swatch class; keep in step with MAP_MARKER_COLORS in resources/js/app.js.
    $arrSwatches = [
        'reference' => 'bg-danger',
        'destination' => 'bg-primary-700',
        'establishment' => 'bg-accent-600',
    ];
@endphp

<ul {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-sand-600']) }} aria-label="Map legend">
    @foreach ($items as $strKind => $strLabel)
        <li class="inline-flex items-center gap-1.5">
            <span class="inline-block h-2.5 w-2.5 rounded-full {{ $arrSwatches[$strKind] ?? 'bg-sand-500' }}" aria-hidden="true"></span>
            {{ $strLabel }}
        </li>
    @endforeach
</ul>
