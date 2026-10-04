@props(['listings' => []])

{{-- Shared "View Details" modal for the landing page. Any element with
     data-listing-details="<listing id>" opens it; resources/js/app.js
     (initListingDetailsModal) fills the fields below from the JSON payload.
     m-auto is required: Tailwind's preflight zeroes <dialog>'s default
     centering margin. --}}
<dialog
    id="listing-details-modal"
    aria-labelledby="listing-details-name"
    class="m-auto w-[calc(100%-2rem)] max-w-2xl overflow-hidden rounded-lg bg-sand-0 p-0 text-sand-900 shadow-xl backdrop:bg-sand-900/60"
>
    <div class="relative h-64 bg-sand-200 sm:h-80">
        <img id="listing-details-image" data-listing-details-photo src="" alt="" class="absolute inset-0 h-full w-full object-cover">
        {{-- Shown instead of the <img> above when the listing has no cover
             photo at all — a neutral category icon, never a stock/DOT
             placeholder photo (7E). --}}
        <div id="listing-details-placeholder" data-listing-details-photo class="absolute inset-0 flex items-center justify-center bg-sand-200">
            <i id="listing-details-placeholder-icon" class="ti text-6xl text-sand-400" aria-hidden="true"></i>
        </div>
        <div data-listing-details-photo class="pointer-events-none absolute inset-0 bg-gradient-to-t from-sand-900/60 via-transparent to-transparent"></div>

        {{-- "Get directions" swaps the photo for this Mapbox route map. The
             wrapper owns the absolute positioning: mapbox-gl.css sets
             .mapboxgl-map { position: relative } unlayered, which beats
             Tailwind's layered utilities on the map element itself. --}}
        <div id="listing-details-map-wrapper" class="absolute inset-0" hidden>
            <div
                id="listing-details-map"
                class="h-full w-full"
                data-mapbox-token="{{ config('services.mapbox.token') }}"
            ></div>
        </div>

        <button
            type="button"
            data-listing-details-close
            class="absolute top-3 right-3 z-10 flex h-9 w-9 cursor-pointer items-center justify-center rounded-full bg-sand-0/90 text-sand-800 shadow-sm transition-colors hover:bg-sand-0"
            aria-label="Close details"
        >
            <i class="ti ti-x text-lg" aria-hidden="true"></i>
        </button>

        <span id="listing-details-category" data-listing-details-photo class="absolute bottom-3 left-4 rounded-sm bg-sand-900/45 px-2.5 py-1 text-xs font-semibold tracking-wide text-sand-0 uppercase"></span>
    </div>

    <div class="max-h-[50vh] overflow-y-auto p-6">
        <div class="flex items-start justify-between gap-3">
            <h2 id="listing-details-name" class="font-display text-2xl font-bold"></h2>
            <span class="mt-1 inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-sand-800">
                <i class="ti ti-star text-accent-500" aria-hidden="true"></i>
                <span id="listing-details-rating"></span>
            </span>
        </div>

        <p class="mt-1 flex items-center gap-1 text-sm font-medium text-sand-500">
            <i class="ti ti-map-pin" aria-hidden="true"></i>
            <span id="listing-details-location"></span>
        </p>

        <p id="listing-details-description" class="mt-4 text-sm leading-relaxed text-sand-700"></p>

        <div id="listing-details-tags" class="mt-4 flex flex-wrap gap-1.5"></div>

        <dl class="mt-6 grid grid-cols-1 gap-4 border-t border-sand-200 pt-5 text-sm sm:grid-cols-2">
            @foreach ([
                'contactOffice' => ['icon' => 'ti-building', 'label' => 'Contact office'],
                'contactPhone' => ['icon' => 'ti-phone', 'label' => 'Phone'],
                'hours' => ['icon' => 'ti-clock', 'label' => 'Hours'],
                'email' => ['icon' => 'ti-mail', 'label' => 'Email'],
                'website' => ['icon' => 'ti-world', 'label' => 'Website'],
            ] as $field => $meta)
                <div data-listing-details-row="{{ $field }}" class="flex items-start gap-2.5">
                    <i class="ti {{ $meta['icon'] }} mt-0.5 text-primary-700" aria-hidden="true"></i>
                    <div class="min-w-0">
                        <dt class="text-xs font-semibold tracking-wide text-sand-500 uppercase">{{ $meta['label'] }}</dt>
                        <dd data-listing-details-value class="break-words text-sand-800"></dd>
                    </div>
                </div>
            @endforeach
        </dl>

        <div class="mt-6 flex flex-wrap gap-3">
            <a
                id="listing-details-full-page"
                href=""
                class="inline-flex items-center gap-2 rounded-sm border border-sand-300 px-5 py-2.5 text-sm font-semibold text-sand-800 transition-colors hover:border-primary-300 hover:text-primary-700"
            >
                <i class="ti ti-photo" aria-hidden="true"></i>
                View Full Details &amp; Photos
            </a>
            <button
                type="button"
                id="listing-details-directions"
                class="inline-flex cursor-pointer items-center gap-2 rounded-sm bg-primary-700 px-5 py-2.5 text-sm font-semibold text-sand-0 shadow-sm transition-colors hover:bg-primary-900"
            >
                <i class="ti ti-route" aria-hidden="true"></i>
                <span data-directions-label>Get directions</span>
            </button>
            <button
                type="button"
                data-listing-details-close
                class="inline-flex cursor-pointer items-center rounded-sm border border-sand-300 px-5 py-2.5 text-sm font-semibold text-sand-800 transition-colors hover:border-primary-300 hover:text-primary-700"
            >
                Close
            </button>
        </div>

        <p id="listing-details-directions-status" class="mt-3 flex items-center gap-1.5 text-sm text-sand-600" aria-live="polite" hidden></p>
    </div>

    <script type="application/json" id="listing-details-data">@json($listings)</script>
</dialog>
