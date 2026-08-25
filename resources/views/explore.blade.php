<x-layouts.public title="Explore">
    <div id="explore-root" class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="text-2xl sm:text-3xl">Explore the Heart of Davao Oriental</h1>
        <p id="explore-count" class="mt-2 text-sm text-sand-600" aria-live="polite">
            {{ count($listings) }} verified listings from the Provincial Tourism Office and the 11 municipal tourism
            offices.
        </p>

        {{-- Filter bar --}}
        <div
            class="mt-6 flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 shadow-sm lg:flex-row lg:items-center">
            <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                <label for="explore-search" class="sr-only">Search destinations, resorts, food...</label>
                <input id="explore-search" type="search" placeholder="Search destinations, resorts, food..."
                    class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
            </div>

            <div class="flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 lg:w-56">
                <i class="ti ti-map-pin text-sand-500" aria-hidden="true"></i>
                <label for="explore-municipality" class="sr-only">Municipality</label>
                <select id="explore-municipality"
                    class="w-full border-0 bg-transparent text-sm text-sand-900 focus:outline-none">
                    <option value="">All municipalities</option>
                    @foreach ($municipalities as $municipality)
                        <option value="{{ $municipality['name'] }}">{{ $municipality['name'] }}</option>
                    @endforeach
                </select>
            </div>

            {{-- View switcher --}}
            <div class="flex shrink-0 items-center gap-1 rounded-sm border border-sand-300 bg-sand-50 p-1" role="group"
                aria-label="Change view">
                <button type="button" data-view-option="grid" aria-pressed="true"
                    class="inline-flex items-center gap-1.5 rounded-sm px-3 py-2 text-sm font-semibold"
                    title="Grid view">
                    <i class="ti ti-layout-grid" aria-hidden="true"></i>
                    <span class="hidden sm:inline">Grid</span>
                </button>
                <button type="button" data-view-option="table" aria-pressed="false"
                    class="inline-flex items-center gap-1.5 rounded-sm px-3 py-2 text-sm font-semibold"
                    title="Table view">
                    <i class="ti ti-table" aria-hidden="true"></i>
                    <span class="hidden sm:inline">Table</span>
                </button>
                <button type="button" data-view-option="map" aria-pressed="false"
                    class="inline-flex items-center gap-1.5 rounded-sm px-3 py-2 text-sm font-semibold"
                    title="Map view">
                    <i class="ti ti-map-2" aria-hidden="true"></i>
                    <span class="hidden sm:inline">Map</span>
                </button>
            </div>
        </div>

        {{-- Category chips --}}
        <div id="explore-category-chips" class="mt-4 flex flex-wrap gap-2">
            @foreach ($categories as $category)
                <button type="button" data-category-chip="{{ $category['slug'] }}" aria-pressed="false"
                    class="inline-flex items-center gap-1.5 rounded-full border border-sand-300 bg-sand-0 px-3.5 py-1.5 text-xs font-semibold text-sand-700 transition-colors hover:border-primary-300">
                    <i class="ti {{ $category['icon'] }}" aria-hidden="true"></i>
                    {{ $category['label'] }}
                </button>
            @endforeach
        </div>

        {{-- Results --}}
        <div class="mt-8">
            <div id="explore-grid" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"></div>

            <div id="explore-table"
                class="hidden overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                <table class="w-full min-w-[820px] border-collapse text-sm">
                    <thead>
                        <tr
                            class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Hours</th>
                            <th class="px-4 py-3">Rating</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody id="explore-table-body" class="divide-y divide-sand-100"></tbody>
                </table>
            </div>

            <div id="explore-map" class="hidden">
                <p class="mb-3 text-xs text-sand-500">Illustrative province map — not to scale. Pins mark the
                    municipality of each filtered listing.</p>
                <div
                    class="relative h-[520px] overflow-hidden rounded-md border border-sand-200 bg-gradient-to-b from-primary-100 to-sand-100">
                    <div id="explore-map-canvas" class="absolute inset-0"></div>
                </div>
            </div>

            <div id="explore-empty"
                class="hidden flex-col items-center justify-center rounded-md border border-sand-200 bg-sand-0 px-6 py-16 text-center">
                <i class="ti ti-map-search mb-3 text-3xl text-sand-400" aria-hidden="true"></i>
                <p class="font-display text-base font-bold text-sand-900">No listings match your filters</p>
                <p class="mt-1 text-sm text-sand-600">Try widening your municipality or category selection.</p>
                <button type="button" id="explore-reset"
                    class="mt-4 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    Reset filters
                </button>
            </div>
        </div>

        <noscript>
            <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($listings as $listing)
                    <x-explore-listing-card :listing="$listing" />
                @endforeach
            </div>
        </noscript>
    </div>

    <script type="application/json"
        id="explore-data">@json(['listings' => $listings, 'categories' => $categories, 'municipalities' => $municipalities])</script>
</x-layouts.public>