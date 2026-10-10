{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Public Nearby page — search a location or use the device location, filter by category and
    distance, and see the closest published tourism places on a map and in a list. Driven by
    initNearbyMap() in resources/js/app.js; distances come from POST /find-near-me (NearbySearchService).
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $arrRadiusOptions = config('tourism_directory.nearby.radius_options_km');
    $intDefaultRadius = (int) config('tourism_directory.nearby.default_radius_km');
    $arrBounds = config('tourism_directory.coordinate_bounds');
    // Mapbox bounding box order: min longitude, min latitude, max longitude, max latitude.
    $strGeocodeBounds = implode(',', [$arrBounds['min_longitude'], $arrBounds['min_latitude'], $arrBounds['max_longitude'], $arrBounds['max_latitude']]);
@endphp

<x-layouts.public title="Nearby" description="Search a location, choose a category, and see the closest tourism services in Davao Oriental.">
    <section
        id="nearby-page"
        data-endpoint="{{ route('findNearMe', [], false) }}"
        data-directory-url="{{ route('explore', [], false) }}"
        data-geocode-bounds="{{ $strGeocodeBounds }}"
        class="mx-auto max-w-[1200px] px-4 py-8 sm:px-6 lg:px-8 lg:py-10"
    >
        <header>
            <h1 class="text-2xl text-sand-900 sm:text-3xl">Nearby</h1>
            <p class="mt-2 max-w-2xl text-sm text-sand-600 sm:text-base">Search a location, choose a category, and see the closest tourism services.</p>
        </header>

        {{-- Location search and "Use my location" --}}
        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <form id="nearby-search-form" class="flex min-w-0 flex-1 gap-2" role="search">
                <label for="nearby-search" class="sr-only">Search a location</label>
                <div class="relative min-w-0 flex-1">
                    <i class="ti ti-search pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sand-500" aria-hidden="true"></i>
                    <input
                        id="nearby-search"
                        type="search"
                        maxlength="100"
                        autocomplete="off"
                        placeholder="Search a town, barangay, or landmark"
                        class="w-full rounded-sm border border-sand-300 bg-sand-0 py-2.5 pr-3 pl-9 text-sm text-sand-900 placeholder:text-sand-500"
                    >
                </div>
                <button type="submit" id="nearby-search-submit" class="inline-flex shrink-0 items-center gap-1.5 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900 disabled:cursor-wait disabled:opacity-70">
                    Search
                </button>
            </form>
            <button type="button" id="nearby-use-location" class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-sm border border-primary-700 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-primary-700 hover:bg-primary-50 disabled:cursor-wait disabled:opacity-70">
                <i class="ti ti-current-location" aria-hidden="true"></i>
                <span id="nearby-use-location-label">Use my location</span>
            </button>
        </div>

        {{-- Category pills (scroll sideways on small screens) and distance --}}
        <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="-mx-4 min-w-0 flex-1 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:px-0 [&::-webkit-scrollbar]:hidden" role="group" aria-label="Filter by category">
                <div class="flex w-max gap-2">
                    <button type="button" data-nearby-category="" aria-pressed="true" class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-sand-300 bg-sand-0 px-3.5 py-1.5 text-sm font-medium text-sand-700 transition-colors hover:border-primary-300 aria-pressed:border-primary-700 aria-pressed:bg-primary-700 aria-pressed:text-sand-0">
                        <i class="ti ti-layout-grid" aria-hidden="true"></i>
                        All
                    </button>
                    @foreach ($categories as $category)
                        <button type="button" data-nearby-category="{{ $category['slug'] }}" aria-pressed="false" class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-sand-300 bg-sand-0 px-3.5 py-1.5 text-sm font-medium text-sand-700 transition-colors hover:border-primary-300 aria-pressed:border-primary-700 aria-pressed:bg-primary-700 aria-pressed:text-sand-0">
                            <i class="ti {{ $category['icon'] }}" aria-hidden="true"></i>
                            {{ $category['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <label for="nearby-radius" class="text-xs font-semibold text-sand-700">Within</label>
                <select id="nearby-radius" class="rounded-sm border border-sand-300 bg-sand-0 px-3 py-2 text-sm text-sand-900">
                    @foreach ($arrRadiusOptions as $intOption)
                        <option value="{{ $intOption }}" @selected($intOption === $intDefaultRadius)>{{ $intOption }} km</option>
                    @endforeach
                </select>
            </div>
        </div>

        <p class="mt-3 flex items-start gap-2 text-xs text-sand-600">
            <i class="ti ti-shield-lock mt-0.5 text-primary-700" aria-hidden="true"></i>
            <span>iTOUR uses your current location only to identify nearby tourism destinations and services. Your exact location is not permanently stored. Searched place names are looked up through Mapbox.</span>
        </p>

        {{-- Status and the active location --}}
        <div class="mt-4 flex min-h-8 flex-wrap items-center gap-2">
            <p id="nearby-status" role="status" aria-live="polite" class="text-sm text-sand-700"></p>
            <button type="button" id="nearby-clear-location" hidden class="inline-flex items-center gap-1 rounded-full border border-sand-300 bg-sand-0 px-3 py-1 text-xs font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-x" aria-hidden="true"></i>
                Clear location
            </button>
        </div>
        <p id="nearby-notice" role="alert" hidden class="mt-2 rounded-sm border border-warning/40 bg-warning-bg px-3 py-2 text-sm text-sand-900"></p>

        {{-- Map / List switch (small screens only; both show side by side on desktop) --}}
        <div class="mt-4 grid grid-cols-2 gap-1 rounded-sm bg-sand-100 p-1 lg:hidden" role="group" aria-label="Choose map or list view">
            <button type="button" data-nearby-view="map" aria-pressed="true" class="inline-flex items-center justify-center gap-1.5 rounded-sm px-3 py-2 text-sm font-semibold text-sand-700 aria-pressed:bg-sand-0 aria-pressed:text-primary-700 aria-pressed:shadow-sm">
                <i class="ti ti-map-2" aria-hidden="true"></i>
                Map View
            </button>
            <button type="button" data-nearby-view="list" aria-pressed="false" class="inline-flex items-center justify-center gap-1.5 rounded-sm px-3 py-2 text-sm font-semibold text-sand-700 aria-pressed:bg-sand-0 aria-pressed:text-primary-700 aria-pressed:shadow-sm">
                <i class="ti ti-list" aria-hidden="true"></i>
                List View
            </button>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div data-nearby-panel="map" class="block lg:block">
                <div data-map-frame class="relative h-[65vh] min-h-[340px] overflow-hidden rounded-md border border-sand-200 bg-gradient-to-br from-sand-200 to-primary-100 lg:h-[640px]">
                    {{-- The wrapper owns the absolute positioning: mapbox-gl.css sets
                         .mapboxgl-map { position: relative } unlayered, which beats
                         Tailwind v4's layered utilities, so #nearby-map itself must
                         size with h-full/w-full rather than absolute/inset-0. --}}
                    <div class="absolute inset-0">
                        <div
                            id="nearby-map"
                            role="region"
                            aria-label="Map of nearby tourism places"
                            data-mapbox-token="{{ \App\Support\MapboxToken::browserToken() }}"
                            data-mapbox-center-lat="6.9214"
                            data-mapbox-center-lng="126.2686"
                            class="h-full w-full"
                        ></div>
                    </div>
                    <x-map-fallback />
                </div>
                <x-map-legend class="mt-2" :items="['reference' => 'Your location', 'destination' => 'Destination', 'establishment' => 'Tourism service']" />
            </div>

            <div data-nearby-panel="list" class="hidden lg:block">
                <ol id="nearby-list" class="flex flex-col gap-3 lg:h-[640px] lg:overflow-y-auto lg:pr-1" aria-label="Nearby tourism places"></ol>
                <noscript>
                    <p class="text-sm text-sand-600">The Nearby page needs JavaScript. <a href="{{ route('explore') }}" class="font-semibold text-primary-700">Browse the full directory</a> instead.</p>
                </noscript>
                <p id="nearby-distance-note" hidden class="mt-2 text-xs text-sand-500">Distances are straight-line, not road distance.</p>
            </div>
        </div>

        <script type="application/json" id="nearby-map-data">{!! json_encode($nearbyPlaces, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    </section>

    <x-listing-details-modal :listings="$listingDetails" />
</x-layouts.public>
