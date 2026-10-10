{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Listing card used by the Explore page — cover, type, DOT Accredited badge (when applicable),
    name, rating, location, a short description excerpt, and View Details.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['listing'])

<article class="group flex flex-col overflow-hidden rounded-md border border-sand-200 bg-sand-0 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
    <div class="relative h-44 overflow-hidden bg-sand-200">
        <x-listing-photo :listing="$listing" />
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-sand-900/55 via-transparent to-transparent"></div>
        {{-- A destination shows its destination type (e.g. "Beach") when set. --}}
        <span class="relative m-3 inline-block rounded-sm bg-sand-900/45 px-2.5 py-1 text-xs font-semibold text-sand-0">
            {{ ($listing['destinationType'] ?? null) ?: \App\Support\TourismCatalog::categoryLabel($listing['category']) }}
        </span>
    </div>

    <div class="flex flex-1 flex-col gap-2 p-5">
        {{-- Public trust badge from the existing accreditation field (Listing::isDotAccredited()). --}}
        @if ($listing['isDotAccredited'] ?? false)
            <x-dot-accredited-badge class="self-start" />
        @endif

        <div class="flex items-start justify-between gap-2">
            <h3 class="font-display text-lg font-bold text-sand-900">{{ $listing['name'] }}</h3>
            <span class="mt-0.5 inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-sand-800">
                <i class="ti ti-star text-accent-500" aria-hidden="true"></i>
                {{ $listing['rating'] !== null ? number_format($listing['rating'], 1) : 'New' }}
            </span>
        </div>

        <p class="flex items-center gap-1 text-xs font-medium text-sand-500">
            <i class="ti ti-map-pin" aria-hidden="true"></i>
            {{ $listing['barangay'] }}, {{ $listing['municipality'] }}
        </p>

        {{-- A short excerpt (App\Support\TextSummary); the full description is on the detail page. --}}
        @if ($listing['summary'] ?? '')
            <p class="line-clamp-3 text-sm leading-relaxed break-words text-sand-600">{{ $listing['summary'] }}</p>
        @endif

        <a href="{{ $listing['href'] }}" class="mt-auto inline-flex items-center gap-1.5 pt-3 text-sm font-semibold text-primary-700 transition-colors group-hover:text-primary-900">
            View Details
            <i class="ti ti-arrow-right transition-transform group-hover:translate-x-0.5" aria-hidden="true"></i>
        </a>
    </div>
</article>
