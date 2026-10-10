{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: The public "map can't be displayed" message — hidden until the map script shows it
    (Mapbox unavailable, no public token, no WebGL, or the map failed to load). Never shows error details.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['hint' => null])

<div data-map-fallback hidden {{ $attributes->merge(['class' => 'absolute inset-0 z-10 flex flex-col items-center justify-center gap-1 bg-sand-50/95 px-6 text-center']) }} role="status">
    <i class="ti ti-map-off text-3xl text-sand-400" aria-hidden="true"></i>
    <p class="font-display text-sm font-bold text-sand-900">Map can't be displayed right now.</p>
    @if ($hint)
        <p class="text-xs text-sand-600">{{ $hint }}</p>
    @endif
</div>
