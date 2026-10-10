{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Public "Find Nearby" list for one destination — every published listing within the chosen
    radius, optionally one category, nearest first, paginated (ListingDetailController::nearby()).
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    // The next larger radius option, offered when nothing is found.
    $intWiderRadius = collect($radiusOptions)->first(fn ($intOption) => $intOption > $radiusKm);
@endphp

<x-layouts.public :title="'Near '.$listing->lst_name" :description="'Tourism destinations and services near '.$listing->lst_name.', Davao Oriental.'" :canonical="route('listings.nearby', $listing)">
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
        <a href="{{ route('listings.show', $listing) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:text-primary-900">
            <i class="ti ti-arrow-left" aria-hidden="true"></i>
            Back to {{ $listing->lst_name }}
        </a>

        <h1 class="mt-4 font-display text-2xl font-bold text-sand-900 sm:text-3xl">Near {{ $listing->lst_name }}</h1>
        <p class="mt-1 text-sm text-sand-600">
            {{ $listing->lst_barangay }}, {{ $listing->lst_municipality }} · distances are straight-line, not road distance.
        </p>

        @if (! $hasLocation)
            <p class="mt-6 rounded-md border border-sand-200 bg-sand-0 px-4 py-3 text-sm text-sand-600">
                This destination's map location hasn't been set yet, so nearby services can't be shown.
                <a href="{{ route('explore') }}" class="font-semibold text-primary-700 hover:text-primary-900">Browse the full directory</a>.
            </p>
        @else
            {{-- Radius and category: a plain GET form — the server filters. --}}
            <form method="GET" action="{{ route('listings.nearby', $listing) }}" class="mt-6 flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 shadow-sm sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="nearby-radius" class="mb-1 block text-xs font-semibold text-sand-700">Within</label>
                    <select id="nearby-radius" name="radius" class="w-full rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-900">
                        @foreach ($radiusOptions as $intOption)
                            <option value="{{ $intOption }}" @selected((int) $radiusKm === $intOption)>{{ $intOption }} km</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1">
                    <label for="nearby-category" class="mb-1 block text-xs font-semibold text-sand-700">Category</label>
                    <select id="nearby-category" name="category" class="w-full rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-900">
                        <option value="">All categories</option>
                        @foreach ($categoryOptions as $strSlug => $strLabel)
                            <option value="{{ $strSlug }}" @selected($selectedCategory === $strSlug)>{{ $strLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                    <i class="ti ti-radar" aria-hidden="true"></i>
                    Find Nearby
                </button>
            </form>

            @if ($isUnavailable)
                <p class="mt-6 rounded-md border border-sand-200 bg-sand-0 px-4 py-3 text-sm text-sand-600">
                    Nearby services can't be shown right now. Please try again later, or
                    <a href="{{ route('explore') }}" class="font-semibold text-primary-700 hover:text-primary-900">browse the full directory</a>.
                </p>
            @elseif ($items === [])
                <div class="mt-6 flex flex-col items-center justify-center rounded-md border border-sand-200 bg-sand-0 px-6 py-12 text-center">
                    <i class="ti ti-map-search mb-3 text-3xl text-sand-400" aria-hidden="true"></i>
                    <p class="font-display text-base font-bold text-sand-900">No tourism services were found within the selected radius.</p>
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                        @if ($intWiderRadius)
                            <a href="{{ route('listings.nearby', array_filter([$listing, 'radius' => $intWiderRadius, 'category' => $selectedCategory])) }}" class="rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">Search within {{ $intWiderRadius }} km</a>
                        @endif
                        @if ($selectedCategory !== '')
                            <a href="{{ route('listings.nearby', [$listing, 'radius' => (int) $radiusKm]) }}" class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2 text-sm font-semibold text-sand-800 hover:border-primary-300">Show all categories</a>
                        @endif
                        <a href="{{ route('explore') }}" class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2 text-sm font-semibold text-sand-800 hover:border-primary-300">Browse the full directory</a>
                    </div>
                </div>
            @else
                <p class="mt-6 text-sm text-sand-600" aria-live="polite">
                    {{ $results->total() }} {{ \Illuminate\Support\Str::plural('place', $results->total()) }} within {{ (int) $radiusKm }} km{{ $selectedCategoryLabel ? ' · '.$selectedCategoryLabel : '' }}, nearest first.
                </p>
                <ul class="mt-3 divide-y divide-sand-100 rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                    @foreach ($items as $arrItem)
                        <li class="flex items-center gap-4 px-4 py-3">
                            <span class="relative h-14 w-14 shrink-0 overflow-hidden rounded-sm bg-sand-200">
                                @if ($arrItem['imageUrl'])
                                    <img src="{{ $arrItem['imageUrl'] }}" alt="{{ $arrItem['name'] }}" loading="lazy" class="h-full w-full object-cover">
                                @else
                                    <span class="flex h-full w-full items-center justify-center"><i class="ti {{ $arrItem['type'] === 'destination' ? 'ti-map-pin' : 'ti-building-store' }} text-xl text-sand-400" aria-hidden="true"></i></span>
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <a href="{{ $arrItem['url'] }}" class="font-semibold text-sand-900 hover:text-primary-700">{{ $arrItem['name'] }}</a>
                                <p class="text-xs text-sand-500">{{ $arrItem['category'] }}{{ $arrItem['subtype'] ? ' · '.$arrItem['subtype'] : '' }} · {{ $arrItem['municipality'] }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-xs font-semibold text-sand-700">{{ $arrItem['distanceLabel'] }}</p>
                                <a href="{{ $arrItem['url'] }}" class="text-xs font-semibold text-primary-700 hover:text-primary-900">View Details</a>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <x-public-pagination :paginator="$results" />
            @endif
        @endif
    </div>
</x-layouts.public>
