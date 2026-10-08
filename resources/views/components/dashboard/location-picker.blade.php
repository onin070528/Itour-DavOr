{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Mapbox location picker for the LGU/PTO listing forms (resources/js/location_picker.js).
    It fills the form's existing latitude/longitude inputs; the server re-validates them on save
    (App\Rules\WithinDavaoOrientalBounds). Typing the coordinates always works without the map.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['latitudeInput', 'longitudeInput', 'isDisabled' => false])

@php
    $arrBounds = config('tourism_directory.coordinate_bounds');
@endphp

@pushOnce('head', 'mapbox-gl')
    <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v3.7.0/mapbox-gl.css">
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.7.0/mapbox-gl.js"></script>
@endPushOnce

<div
    data-location-picker
    data-latitude-input="{{ $latitudeInput }}"
    data-longitude-input="{{ $longitudeInput }}"
    data-disabled="{{ $isDisabled ? 'true' : 'false' }}"
    data-mapbox-token="{{ \App\Support\MapboxToken::browserToken() }}"
    data-min-latitude="{{ $arrBounds['min_latitude'] }}"
    data-max-latitude="{{ $arrBounds['max_latitude'] }}"
    data-min-longitude="{{ $arrBounds['min_longitude'] }}"
    data-max-longitude="{{ $arrBounds['max_longitude'] }}"
    data-center-latitude="{{ ($arrBounds['min_latitude'] + $arrBounds['max_latitude']) / 2 }}"
    data-center-longitude="{{ ($arrBounds['min_longitude'] + $arrBounds['max_longitude']) / 2 }}"
    {{ $attributes->merge(['class' => 'flex flex-col gap-1.5']) }}
>
    <span class="form-label">Map location</span>
    <p class="form-hint">
        @if ($isDisabled)
            The location is locked while it is under review.
        @else
            Click the map or drag the pin to set the location — the latitude and longitude fill in automatically. You can also type them.
        @endif
    </p>
    <div class="relative h-64 overflow-hidden rounded-sm border border-sand-300 bg-sand-100">
        {{-- The wrapper owns the absolute positioning (mapbox-gl.css sets .mapboxgl-map { position: relative }). --}}
        <div class="absolute inset-0">
            <div data-location-picker-map role="region" aria-label="Map for choosing the location" class="h-full w-full"></div>
        </div>
        <x-map-fallback hint="Type the latitude and longitude instead." />
    </div>
    <p data-location-picker-warning hidden class="form-error">This point is outside Davao Oriental, so it will not be accepted. Move the pin into the province.</p>
    <div>
        <button type="button" data-location-picker-clear class="btn-small">
            <i class="ti ti-map-pin-off" aria-hidden="true"></i>
            Clear location
        </button>
    </div>
</div>
