{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Landing page "Near you" section — Find Near Me (server-side nearest places) and a map that plots them.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['places' => []])

<section id="near-you" class="bg-primary-900">
    <div class="mx-auto grid max-w-[1200px] gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:items-center lg:px-8">
        <div>
            <p class="text-xs font-bold tracking-widest text-accent-500 uppercase">Nearby Services</p>
            <h2 class="mt-2 text-2xl text-sand-0 sm:text-3xl">Everything you need, wherever the road takes you.</h2>
            <p class="mt-4 max-w-lg text-sm leading-relaxed text-white/75 sm:text-base">
                Find the nearest published destinations, accommodation, food, and other tourism services from wherever you are in the province.
            </p>

            {{-- Find Near Me: the server finds the nearest places; the map on the right plots them. --}}
            <x-find-near-me class="mt-6" />
        </div>

        <div class="relative h-80 overflow-hidden rounded-lg border border-white/10 shadow-sm sm:h-96">
            {{-- The wrapper owns the absolute positioning: mapbox-gl.css sets
                 .mapboxgl-map { position: relative } unlayered, which beats
                 Tailwind v4's layered utilities, so #nearby-map itself must
                 size with h-full/w-full rather than absolute/inset-0. --}}
            <div class="absolute inset-0 bg-gradient-to-br from-sand-200 to-primary-100">
                <div
                    id="nearby-map"
                    data-mapbox-token="{{ \App\Support\MapboxToken::browserToken() }}"
                    data-mapbox-center-lat="6.9214"
                    data-mapbox-center-lng="126.2686"
                    class="h-full w-full"
                ></div>
            </div>

            <span id="nearby-map-status" class="pointer-events-none absolute bottom-3 left-3 inline-flex max-w-[calc(100%-1.5rem)] items-center gap-1.5 rounded-sm bg-sand-0 px-2.5 py-1.5 text-xs font-semibold text-sand-700 shadow-sm">
                <i class="ti ti-map-2" aria-hidden="true"></i>
                <span id="nearby-map-status-text">Showing destinations & establishments across Davao Oriental</span>
            </span>
        </div>

        <script type="application/json" id="nearby-map-data">{!! json_encode($places, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    </div>
</section>
