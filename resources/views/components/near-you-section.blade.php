@props(['places' => []])

<section id="near-you" class="bg-primary-900">
    <div class="mx-auto grid max-w-[1200px] gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:items-center lg:px-8">
        <div>
            <p class="text-xs font-bold tracking-widest text-accent-500 uppercase">Nearby Services</p>
            <h2 class="mt-2 text-2xl text-sand-0 sm:text-3xl">Everything you need, wherever the road takes you.</h2>
            <p class="mt-4 max-w-lg text-sm leading-relaxed text-white/75 sm:text-base">
                Find the nearest accredited accommodation, restaurants, transport, tour guides and emergency services from anywhere in the province.
            </p>

            <div class="mt-6 flex flex-wrap gap-2">
                @foreach (['Accommodation', 'Restaurants', 'Transportation', 'Emergency Services'] as $tag)
                    <span class="rounded-full border border-white/25 px-3.5 py-1.5 text-xs font-semibold text-sand-0">{{ $tag }}</span>
                @endforeach
            </div>

            <button
                type="button"
                id="find-near-you-button"
                class="mt-8 inline-flex items-center justify-center gap-2 rounded-sm bg-accent-500 px-6 py-3 text-sm font-semibold text-sand-0 shadow-sm transition-colors hover:bg-accent-600 disabled:cursor-wait disabled:opacity-70"
            >
                Open Nearby map
                <i class="ti ti-arrow-right" aria-hidden="true"></i>
            </button>
        </div>

        <div class="relative h-80 overflow-hidden rounded-lg border border-white/10 shadow-sm sm:h-96">
            <div
                id="nearby-map"
                data-mapbox-token="{{ config('services.mapbox.token') }}"
                data-mapbox-center-lat="6.9214"
                data-mapbox-center-lng="126.2686"
                class="absolute inset-0 bg-gradient-to-br from-sand-200 to-primary-100"
            ></div>

            <span id="nearby-map-status" class="pointer-events-none absolute bottom-3 left-3 inline-flex max-w-[calc(100%-1.5rem)] items-center gap-1.5 rounded-sm bg-sand-0 px-2.5 py-1.5 text-xs font-semibold text-sand-700 shadow-sm">
                <i class="ti ti-map-2" aria-hidden="true"></i>
                <span id="nearby-map-status-text">Showing destinations & establishments across Davao Oriental</span>
            </span>
        </div>

        <script type="application/json" id="nearby-map-data">{!! json_encode($places) !!}</script>
    </div>
</section>
