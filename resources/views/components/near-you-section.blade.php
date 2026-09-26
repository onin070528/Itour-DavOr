@props(['places' => []])

<section id="near-you" class="border-y border-sand-200 bg-sand-100">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:items-center lg:px-8">
        <div>
            <p class="text-xs font-bold tracking-widest text-primary-700 uppercase">Location-based discovery</p>
            <h2 class="mt-2 text-2xl sm:text-3xl">Find Places Near You</h2>
            <p class="mt-4 max-w-lg text-sm leading-relaxed text-sand-600 sm:text-base">
                iTOUR can help you discover nearby tourism destinations and establishments — from beaches and waterfalls to accommodations, restaurants, and emergency services — based on where you currently are in Davao Oriental.
            </p>

            <ul class="mt-6 flex flex-col gap-3 text-sm text-sand-700">
                <li class="flex items-start gap-2.5">
                    <i class="ti ti-map-pin-check mt-0.5 text-primary-700" aria-hidden="true"></i>
                    See destinations and establishments closest to your location
                </li>
                <li class="flex items-start gap-2.5">
                    <i class="ti ti-route mt-0.5 text-primary-700" aria-hidden="true"></i>
                    Get a sense of distance and direction before you travel
                </li>
                <li class="flex items-start gap-2.5">
                    <i class="ti ti-first-aid-kit mt-0.5 text-primary-700" aria-hidden="true"></i>
                    Quickly locate emergency and tourism assistance services
                </li>
            </ul>

            <button
                type="button"
                id="find-near-you-button"
                class="mt-8 inline-flex items-center justify-center gap-2 rounded-sm bg-primary-700 px-6 py-3 text-sm font-semibold text-sand-0 shadow-sm transition-colors hover:bg-primary-900 disabled:cursor-wait disabled:opacity-70"
            >
                <i class="ti ti-current-location" aria-hidden="true"></i>
                Find Places Near You
            </button>
        </div>

        <div class="relative h-80 overflow-hidden rounded-lg border border-sand-200 shadow-sm sm:h-96">
            <div class="absolute inset-0 bg-gradient-to-br from-sand-200 to-primary-100">
                <div
                    id="nearby-map"
                    data-mapbox-token="{{ config('services.mapbox.token') }}"
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

        <script type="application/json" id="nearby-map-data">{!! json_encode($places) !!}</script>
    </div>
</section>
