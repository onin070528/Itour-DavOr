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

            <a href="#" class="mt-8 inline-flex items-center justify-center gap-2 rounded-sm bg-primary-700 px-6 py-3 text-sm font-semibold text-sand-0 shadow-sm transition-colors hover:bg-primary-900">
                <i class="ti ti-current-location" aria-hidden="true"></i>
                Find Places Near You
            </a>
        </div>

        {{-- Map placeholder. `data-map-container` marks the mount point for the future Mapbox GL integration. --}}
        <div
            id="nearby-map"
            data-map-container
            class="relative h-80 overflow-hidden rounded-lg border border-sand-200 bg-gradient-to-br from-sand-200 to-primary-100 shadow-sm sm:h-96"
        >
            <i class="ti ti-map-pin-filled absolute top-[38%] left-[42%] text-3xl text-primary-700 drop-shadow" aria-hidden="true"></i>
            <i class="ti ti-map-pin-filled absolute top-[55%] left-[60%] text-4xl text-accent-600 drop-shadow" aria-hidden="true"></i>
            <i class="ti ti-map-pin-filled absolute top-[28%] left-[65%] text-2xl text-primary-700 drop-shadow" aria-hidden="true"></i>
            <i class="ti ti-map-pin-filled absolute top-[68%] left-[30%] text-2xl text-primary-700 drop-shadow" aria-hidden="true"></i>

            <span class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-sm bg-sand-0 px-2.5 py-1.5 text-xs font-semibold text-sand-700 shadow-sm">
                <i class="ti ti-map-2" aria-hidden="true"></i>
                Map view — live map coming soon
            </span>
        </div>
    </div>
</section>
