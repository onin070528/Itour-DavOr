{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Public Explore page — the unified Tourism Directory of destinations and establishments,
    searched, filtered, and paginated on the server (ExploreController), shown as a Grid, Table, or Map.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    // Explore URLs keep the current filters; the default view and empty values stay out of the query string.
    $fnExploreUrl = fn (array $arrChanges = []) => route('explore', array_filter(
        [...$filters, ...$arrChanges],
        fn ($mixValue, $strKey) => $mixValue !== '' && $mixValue !== null && ! ($strKey === 'view' && $mixValue === 'grid'),
        ARRAY_FILTER_USE_BOTH,
    ));
    $blnHasFilters = $filters['q'] !== '' || $filters['municipality'] !== '' || $filters['category'] !== '' || $filters['type'] !== '';
    $strActiveCategoryLabel = $filters['category'] !== '' ? \App\Support\TourismCatalog::categoryLabel($filters['category']) : null;
    $strChipBase = 'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-xs font-semibold transition-colors hover:border-primary-300';
    $strRowBase = 'block w-full rounded-sm border-l-2 px-3 py-2.5 text-left text-sm font-semibold transition-colors hover:bg-sand-50';
@endphp

<x-layouts.public title="Explore" description="Search tourist destinations, accommodation, food, and other tourism services across Davao Oriental." :canonical="route('explore')">
    <div id="explore-root" class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <h1 class="text-2xl sm:text-3xl">Explore the Heart of Davao Oriental</h1>
        <p id="explore-count" class="mt-2 text-sm text-sand-600" aria-live="polite">
            {{ $listings->total() }} verified {{ \Illuminate\Support\Str::plural('listing', $listings->total()) }} from the Provincial Tourism Office and the 11 municipal tourism offices.
        </p>

        {{-- Categories below `md`: horizontally-scrollable chips (same slugs as the sidebar). --}}
        <nav id="explore-category-chips-mobile" class="mt-4 flex gap-2 overflow-x-auto pb-1 md:hidden" aria-label="Categories">
            <a href="{{ $fnExploreUrl(['category' => '', 'type' => '']) }}" data-category-all aria-current="{{ $filters['category'] === '' ? 'true' : 'false' }}"
                @class([$strChipBase, 'border-primary-300 bg-primary-100 text-primary-700' => $filters['category'] === '', 'border-sand-300 bg-sand-0 text-sand-700' => $filters['category'] !== ''])>
                <i class="ti ti-apps" aria-hidden="true"></i>
                All listings
            </a>
            @foreach ($categories as $category)
                <a href="{{ $fnExploreUrl(['category' => $category['slug'], 'type' => $category['slug'] === 'destinations' ? $filters['type'] : '']) }}" data-category-chip="{{ $category['slug'] }}" aria-current="{{ $filters['category'] === $category['slug'] ? 'true' : 'false' }}"
                    @class([$strChipBase, 'border-primary-300 bg-primary-100 text-primary-700' => $filters['category'] === $category['slug'], 'border-sand-300 bg-sand-0 text-sand-700' => $filters['category'] !== $category['slug']])>
                    <i class="ti {{ $category['icon'] }}" aria-hidden="true"></i>
                    {{ $category['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="mt-4 flex flex-col gap-6 md:mt-6 md:flex-row md:items-start">
            {{-- Categories sidebar: `md` and up only. --}}
            <aside class="hidden shrink-0 rounded-md border border-sand-200 bg-sand-0 p-4 shadow-sm md:block md:w-60">
                <h2 class="mb-2 font-display text-lg font-bold text-sand-900">Categories</h2>
                <ul class="flex flex-col gap-0.5">
                    <li>
                        <a href="{{ $fnExploreUrl(['category' => '', 'type' => '']) }}" data-category-all aria-current="{{ $filters['category'] === '' ? 'true' : 'false' }}"
                            @class([$strRowBase, 'border-primary-300 bg-primary-100 text-primary-700' => $filters['category'] === '', 'border-transparent text-sand-700' => $filters['category'] !== ''])>
                            All listings
                        </a>
                    </li>
                    @foreach ($categories as $category)
                        <li>
                            <a href="{{ $fnExploreUrl(['category' => $category['slug'], 'type' => $category['slug'] === 'destinations' ? $filters['type'] : '']) }}" data-category-chip="{{ $category['slug'] }}" aria-current="{{ $filters['category'] === $category['slug'] ? 'true' : 'false' }}"
                                @class([$strRowBase, 'border-primary-300 bg-primary-100 text-primary-700' => $filters['category'] === $category['slug'], 'border-transparent text-sand-700' => $filters['category'] !== $category['slug']])>
                                {{ $category['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </aside>

            <div class="min-w-0 flex-1">
                {{-- Find Near Me (same component as the landing page) — separate from the keyword search below. --}}
                <details class="group mb-4 rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                    <summary class="flex cursor-pointer items-center gap-2 px-4 py-3 text-sm font-semibold text-sand-800 hover:text-primary-700">
                        <i class="ti ti-current-location text-primary-700" aria-hidden="true"></i>
                        Find places near me
                        <i class="ti ti-chevron-down ml-auto transition-transform group-open:rotate-180" aria-hidden="true"></i>
                    </summary>
                    <x-find-near-me class="m-3 mt-0 border-0 shadow-none" heading-level="h2" />
                </details>

                {{-- Filter bar: a plain GET form — the server does every filter. --}}
                <form method="GET" action="{{ route('explore') }}" class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 shadow-sm lg:flex-row lg:items-center" role="search">
                    @if ($filters['category'] !== '')
                        <input type="hidden" name="category" value="{{ $filters['category'] }}">
                    @endif
                    @if ($filters['view'] !== 'grid')
                        <input type="hidden" name="view" value="{{ $filters['view'] }}">
                    @endif

                    <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                        <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                        <label for="explore-search" class="sr-only">Search destinations, resorts, food...</label>
                        <input id="explore-search" name="q" type="search" maxlength="100" value="{{ $filters['q'] }}" placeholder="Search destinations, resorts, food..."
                            class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
                    </div>

                    <div class="flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 lg:w-52">
                        <i class="ti ti-map-pin text-sand-500" aria-hidden="true"></i>
                        <label for="explore-municipality" class="sr-only">Municipality</label>
                        <select id="explore-municipality" name="municipality" class="w-full border-0 bg-transparent text-sm text-sand-900 focus:outline-none">
                            <option value="">All municipalities</option>
                            @foreach ($municipalities as $municipality)
                                <option value="{{ $municipality['name'] }}" @selected($filters['municipality'] === $municipality['name'])>{{ $municipality['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 lg:w-48">
                        <i class="ti ti-mountain text-sand-500" aria-hidden="true"></i>
                        <label for="explore-type" class="sr-only">Destination type</label>
                        <select id="explore-type" name="type" class="w-full border-0 bg-transparent text-sm text-sand-900 focus:outline-none">
                            <option value="">All destination types</option>
                            @foreach ($destinationTypes as $strDestinationType)
                                <option value="{{ $strDestinationType }}" @selected($filters['type'] === $strDestinationType)>{{ $strDestinationType }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                        <i class="ti ti-search" aria-hidden="true"></i>
                        Search
                    </button>
                </form>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    @if ($blnHasFilters)
                        <a href="{{ route('explore', $filters['view'] !== 'grid' ? ['view' => $filters['view']] : []) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-700 hover:text-primary-900">
                            <i class="ti ti-x" aria-hidden="true"></i>
                            Clear search and filters
                        </a>
                    @else
                        <span></span>
                    @endif

                    {{-- View switcher --}}
                    <nav class="flex shrink-0 items-center gap-1 rounded-sm border border-sand-300 bg-sand-50 p-1" aria-label="Change view">
                        @foreach (['grid' => ['ti-layout-grid', 'Grid'], 'table' => ['ti-table', 'Table'], 'map' => ['ti-map-2', 'Map']] as $strView => [$strIcon, $strLabel])
                            <a href="{{ $fnExploreUrl(['view' => $strView, 'page' => $listings->currentPage() > 1 ? $listings->currentPage() : '']) }}" data-view-option="{{ $strView }}" aria-current="{{ $filters['view'] === $strView ? 'true' : 'false' }}" title="{{ $strLabel }} view"
                                @class(['inline-flex items-center gap-1.5 rounded-sm px-3 py-2 text-sm font-semibold', 'bg-sand-0 text-primary-700 shadow-sm' => $filters['view'] === $strView, 'text-sand-600' => $filters['view'] !== $strView])>
                                <i class="ti {{ $strIcon }}" aria-hidden="true"></i>
                                <span class="hidden sm:inline">{{ $strLabel }}</span>
                            </a>
                        @endforeach
                    </nav>
                </div>

                {{-- Results --}}
                <div class="mt-4">
                    @if ($listings->isEmpty())
                        <div id="explore-empty" class="flex flex-col items-center justify-center rounded-md border border-sand-200 bg-sand-0 px-6 py-16 text-center">
                            <i class="ti ti-map-search mb-3 text-3xl text-sand-400" aria-hidden="true"></i>
                            @if ($filters['q'] !== '')
                                <p class="font-display text-base font-bold text-sand-900">No listings match “{{ $filters['q'] }}”</p>
                                <p class="mt-1 text-sm text-sand-600">Check the spelling, try a shorter word, or search by municipality or category.</p>
                            @elseif ($blnHasFilters)
                                <p class="font-display text-base font-bold text-sand-900">No listings match the selected filters</p>
                                <p class="mt-1 text-sm text-sand-600">{{ $strActiveCategoryLabel ? "Nothing in {$strActiveCategoryLabel} yet" : 'Nothing here yet' }}{{ $filters['municipality'] !== '' ? " for {$filters['municipality']}" : '' }}. Try a different category, municipality, or destination type.</p>
                            @else
                                <p class="font-display text-base font-bold text-sand-900">No listings are published yet</p>
                                <p class="mt-1 text-sm text-sand-600">Check back soon — the tourism offices are adding destinations and services.</p>
                            @endif
                            @if ($blnHasFilters)
                                <a href="{{ route('explore') }}" id="explore-reset" class="mt-4 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2 text-sm font-semibold text-sand-800 hover:border-primary-300">
                                    Browse the full directory
                                </a>
                            @endif
                        </div>
                    @elseif ($filters['view'] === 'table')
                        <div id="explore-table" class="overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                            <table class="w-full min-w-[820px] border-collapse text-sm">
                                <thead>
                                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                                        <th class="px-4 py-3">Name</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3">Location</th>
                                        <th class="px-4 py-3">Contact</th>
                                        <th class="px-4 py-3">Hours</th>
                                        <th class="px-4 py-3">Rating</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-sand-100">
                                    @foreach ($entries as $entry)
                                        <tr class="hover:bg-sand-50">
                                            <td class="flex items-center gap-3 px-4 py-3">
                                                <span class="relative h-11 w-11 shrink-0 overflow-hidden rounded-sm bg-sand-200"><x-listing-photo :listing="$entry" class="h-full w-full object-cover" /></span>
                                                <span class="flex flex-col items-start gap-1">
                                                    <span class="font-semibold text-sand-900">{{ $entry['name'] }}</span>
                                                    @if ($entry['isDotAccredited'])
                                                        <x-dot-accredited-badge />
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sand-700">{{ $entry['destinationType'] ?: \App\Support\TourismCatalog::categoryLabel($entry['category']) }}</td>
                                            <td class="px-4 py-3 text-sand-700">{{ $entry['barangay'] }}, {{ $entry['municipality'] }}</td>
                                            <td class="px-4 py-3 text-sand-700">{{ $entry['contactOffice'] }}<br><span class="text-xs text-sand-500">{{ $entry['contactPhone'] }}</span></td>
                                            <td class="px-4 py-3 text-sand-700">{{ $entry['hours'] }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sand-700"><i class="ti ti-star text-accent-500" aria-hidden="true"></i> {{ $entry['rating'] !== null ? number_format($entry['rating'], 1) : 'New' }}</td>
                                            <td class="px-4 py-3 text-right"><a href="{{ $entry['href'] }}" class="inline-flex items-center rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300 hover:text-primary-700">View</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif ($filters['view'] === 'map')
                        @php($arrMappedSlugs = array_column($mapPlaces, 'slug'))
                        <div id="explore-map">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <p id="explore-map-caption" class="text-xs text-sand-500">Pins mark the listings on this page — select one for details.</p>
                                <x-map-legend :items="['destination' => 'Destination', 'establishment' => 'Tourism service']" />
                            </div>
                            <div data-map-frame class="relative h-[420px] overflow-hidden rounded-md border border-sand-200 bg-gradient-to-b from-primary-100 to-sand-100 sm:h-[520px]">
                                {{-- The wrapper owns the absolute positioning: mapbox-gl.css sets
                                     .mapboxgl-map { position: relative } unlayered, which beats
                                     Tailwind v4's layered utilities. --}}
                                <div class="absolute inset-0">
                                    <div
                                        id="explore-map-canvas"
                                        role="region"
                                        aria-label="Map of the listings on this page"
                                        data-mapbox-token="{{ \App\Support\MapboxToken::browserToken() }}"
                                        data-mapbox-center-lat="6.9214"
                                        data-mapbox-center-lng="126.2686"
                                        class="relative h-full w-full"
                                    ></div>
                                </div>
                                <x-map-fallback hint="Use the list below, or switch to the Grid or Table view." />
                            </div>

                            {{-- The same listings as a plain list, so the map is never the only way to reach one. --}}
                            <ol class="mt-4 divide-y divide-sand-100 rounded-md border border-sand-200 bg-sand-0 shadow-sm" aria-label="Listings on this page">
                                @foreach ($entries as $entry)
                                    <li data-map-item="{{ $entry['id'] }}" class="flex items-center justify-between gap-3 px-4 py-2.5 transition-colors">
                                        <div class="min-w-0">
                                            <a href="{{ $entry['href'] }}" class="font-semibold text-sand-900 hover:text-primary-700">{{ $entry['name'] }}</a>
                                            <p class="text-xs text-sand-500">{{ $entry['destinationType'] ?: \App\Support\TourismCatalog::categoryLabel($entry['category']) }} · {{ $entry['municipality'] }}</p>
                                        </div>
                                        @if (in_array($entry['id'], $arrMappedSlugs, true))
                                            <button type="button" data-map-focus="{{ $entry['id'] }}" class="inline-flex shrink-0 items-center gap-1 rounded-sm border border-sand-300 px-2.5 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300 hover:text-primary-700">
                                                <i class="ti ti-map-pin" aria-hidden="true"></i>
                                                Show on map
                                            </button>
                                        @else
                                            <span class="shrink-0 text-xs text-sand-400">No map location yet</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                        <script type="application/json" id="explore-data">@json(['places' => $mapPlaces, 'total' => count($entries), 'municipalities' => $municipalities])</script>
                    @else
                        <div id="explore-grid" class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($entries as $entry)
                                <x-explore-listing-card :listing="$entry" />
                            @endforeach
                        </div>
                    @endif
                </div>

                <x-public-pagination :paginator="$listings" />
            </div>
        </div>
    </div>
</x-layouts.public>
