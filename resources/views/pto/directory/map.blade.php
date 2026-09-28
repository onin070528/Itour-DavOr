@php
    $plottedListings = collect($listings)
        ->filter(fn (array $listing) => $listing['lat'] !== null && $listing['lng'] !== null)
        ->map(fn (array $listing) => [
            'name' => $listing['name'],
            'category' => $listing['category'],
            'categoryLabel' => \App\Support\TourismCatalog::categoryLabel($listing['category']),
            'isDestination' => $listing['category'] === 'destinations',
            'municipality' => $listing['municipality'],
            'barangay' => $listing['barangay'],
            'status' => $listing['status'],
            'image' => $listing['image'] ? asset('storage/itour-images/'.$listing['image']) : null,
            'lat' => $listing['lat'],
            'lng' => $listing['lng'],
        ])
        ->values();
    $unplottedCount = count($listings) - $plottedListings->count();
@endphp

@push('head')
    <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v3.7.0/mapbox-gl.css">
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.7.0/mapbox-gl.js"></script>
@endpush

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Tourism Map"
        description="Destinations and establishments across Davao Oriental."
    />

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-[260px_1fr]">
        <div class="rounded-md border border-sand-200 bg-sand-0 p-4">
            <p class="text-xs font-bold tracking-widest text-sand-500 uppercase">Legend</p>
            <ul class="mt-3 flex flex-col gap-2.5 text-sm text-sand-700">
                <li>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="checkbox" data-tourism-map-filter="destinations" class="accent-primary-700" checked>
                        <i class="ti ti-map-pin-filled text-primary-700" aria-hidden="true"></i>Tourist Destinations
                    </label>
                </li>
                <li>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="checkbox" data-tourism-map-filter="establishments" class="accent-accent-600" checked>
                        <i class="ti ti-map-pin-filled text-accent-600" aria-hidden="true"></i>Tourism Establishments
                    </label>
                </li>
            </ul>

            <p class="mt-5 text-xs font-bold tracking-widest text-sand-500 uppercase">Listings Plotted</p>
            <p class="mt-1 font-display text-2xl font-extrabold text-sand-900">{{ $plottedListings->count() }}</p>
            <p class="text-xs text-sand-500">Across {{ $plottedListings->pluck('municipality')->unique()->count() }} municipalities</p>

            @if ($unplottedCount > 0)
                <div class="mt-5 rounded-md border border-dashed border-sand-300 p-3 text-xs text-sand-600">
                    <i class="ti ti-info-circle text-primary-700" aria-hidden="true"></i>
                    {{ $unplottedCount }} {{ Str::plural('listing', $unplottedCount) }} without coordinates {{ $unplottedCount === 1 ? 'is' : 'are' }} not shown on the map.
                </div>
            @endif
        </div>

        <div class="relative h-[560px] overflow-hidden rounded-md border border-sand-200 bg-sand-100">
            {{-- The wrapper owns the absolute positioning: mapbox-gl.css sets
                 .mapboxgl-map { position: relative } unlayered, which beats
                 Tailwind's layered utilities on the map element itself. --}}
            <div class="absolute inset-0">
                <div
                    id="tourism-map"
                    class="h-full w-full"
                    data-mapbox-token="{{ config('services.mapbox.token') }}"
                    data-mapbox-center-lat="7.0"
                    data-mapbox-center-lng="126.3"
                ></div>
            </div>

            <div class="absolute top-3 left-3 z-10 flex flex-wrap gap-2">
                <div class="inline-flex overflow-hidden rounded-sm border border-sand-300 bg-sand-0 text-xs font-semibold shadow-sm" role="group" aria-label="Map view">
                    <button type="button" data-tourism-map-style="satellite" aria-pressed="true" class="cursor-pointer px-3 py-2 text-sand-700 transition-colors aria-pressed:bg-primary-700 aria-pressed:text-sand-0">
                        <i class="ti ti-satellite" aria-hidden="true"></i> Satellite
                    </button>
                    <button type="button" data-tourism-map-style="streets" aria-pressed="false" class="cursor-pointer border-l border-sand-300 px-3 py-2 text-sand-700 transition-colors aria-pressed:bg-primary-700 aria-pressed:text-sand-0">
                        <i class="ti ti-map" aria-hidden="true"></i> Streets
                    </button>
                </div>
                <button type="button" id="tourism-map-3d" aria-pressed="false" class="cursor-pointer rounded-sm border border-sand-300 bg-sand-0 px-3 py-2 text-xs font-semibold text-sand-700 shadow-sm transition-colors aria-pressed:bg-primary-700 aria-pressed:text-sand-0">
                    <i class="ti ti-mountain" aria-hidden="true"></i> 3D terrain
                </button>
            </div>

            <p id="tourism-map-status" class="absolute inset-x-0 bottom-0 z-10 bg-sand-0/90 px-4 py-2 text-xs text-sand-600" hidden></p>

            <script type="application/json" id="tourism-map-data">@json($plottedListings)</script>
        </div>
    </div>
</x-layouts.dashboard>
