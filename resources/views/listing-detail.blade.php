{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Public listing detail page — public information only (no internal id, owner, QR token,
    or workflow status), plus a destination's Nearby Tourism Services (Objective 3, R9).
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $strDestinationType = $isDestination ? $listing->lst_type : null;
    $arrInfoFields = [
        // A destination's contact office is its managing office (Objective 3, D10).
        'lst_contact_office' => ['icon' => 'ti-building', 'label' => $isDestination ? 'Managing office' : 'Contact office'],
        'lst_contact_phone' => ['icon' => 'ti-phone', 'label' => 'Phone'],
        'lst_hours' => ['icon' => 'ti-clock', 'label' => 'Hours'],
        'lst_entrance_fee' => ['icon' => 'ti-ticket', 'label' => 'Entrance fee'],
        'lst_email' => ['icon' => 'ti-mail', 'label' => 'Email'],
        'lst_website' => ['icon' => 'ti-world', 'label' => 'Website'],
    ];
@endphp

<x-layouts.public :title="$listing->lst_name" :description="$metaDescription" :canonical="route('listings.show', $listing)">
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
        <a href="{{ route('explore') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:text-primary-900">
            <i class="ti ti-arrow-left" aria-hidden="true"></i>
            Back to Explore
        </a>

        <div class="relative mt-4 h-64 overflow-hidden rounded-md bg-sand-200 sm:h-96">
            @if ($coverImageUrl)
                <img src="{{ $coverImageUrl }}" alt="{{ $listing->lst_name }}" class="absolute inset-0 h-full w-full object-cover">
            @else
                <div class="absolute inset-0 flex items-center justify-center bg-sand-200">
                    <i class="ti {{ $categoryIcon }} text-6xl text-sand-400" aria-hidden="true"></i>
                </div>
            @endif
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-sand-900/60 via-transparent to-transparent"></div>
            <span class="absolute bottom-4 left-5 rounded-sm bg-sand-900/45 px-3 py-1.5 text-xs font-semibold tracking-wide text-sand-0 uppercase">{{ $categoryLabel }}{{ $strDestinationType ? ' · '.$strDestinationType : '' }}</span>
        </div>

        <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                @if ($listing->isDotAccredited())
                    <x-dot-accredited-badge class="mb-2" />
                @endif
                <h1 class="font-display text-2xl font-bold text-sand-900 sm:text-3xl">{{ $listing->lst_name }}</h1>
                <p class="mt-1 flex items-center gap-1 text-sm font-medium text-sand-500">
                    <i class="ti ti-map-pin" aria-hidden="true"></i>
                    {{ $listing->lst_barangay }}, {{ $listing->lst_municipality }}
                </p>
            </div>
            @if ($listing->lst_rating !== null)
                <span class="inline-flex items-center gap-1 text-sm font-semibold text-sand-800">
                    <i class="ti ti-star text-accent-500" aria-hidden="true"></i>
                    {{ number_format((float) $listing->lst_rating, 1) }}
                </span>
            @endif
        </div>

        @if ($listing->lst_description)
            <p class="mt-4 max-w-3xl text-sm leading-relaxed text-sand-700">{{ $listing->lst_description }}</p>
        @endif

        @if (! empty($listing->lst_tags))
            <div class="mt-4 flex flex-wrap gap-1.5">
                @foreach ($listing->lst_tags as $tag)
                    <span class="rounded-sm bg-sand-100 px-2 py-1 text-xs font-medium text-sand-700">{{ $tag }}</span>
                @endforeach
            </div>
        @endif

        <dl class="mt-6 grid grid-cols-1 gap-4 border-t border-sand-200 pt-5 text-sm sm:grid-cols-2">
            @foreach ($arrInfoFields as $strField => $arrMeta)
                @if ($listing->{$strField})
                    <div class="flex items-start gap-2.5">
                        <i class="ti {{ $arrMeta['icon'] }} mt-0.5 text-primary-700" aria-hidden="true"></i>
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold tracking-wide text-sand-500 uppercase">{{ $arrMeta['label'] }}</dt>
                            <dd class="break-words text-sand-800">{{ $listing->{$strField} }}</dd>
                        </div>
                    </div>
                @endif
            @endforeach
        </dl>

        {{-- Visitor information (destinations): what to know before going. --}}
        @if ($isDestination && $listing->lst_visitor_information)
            <section class="mt-8 border-t border-sand-200 pt-6">
                <h2 class="font-display text-lg font-bold text-sand-900">Visitor information</h2>
                <p class="mt-2 max-w-3xl text-sm leading-relaxed whitespace-pre-line text-sand-700">{{ $listing->lst_visitor_information }}</p>
            </section>
        @endif

        {{-- Simple gallery of every PUBLISHED photo (7E). Each one shows its
             credit line when set, and falls back to the establishment's
             name for alt text. Hidden entirely when there's nothing beyond
             the cover photo already shown above. --}}
        @if ($galleryImages->isNotEmpty())
            <div class="mt-8 border-t border-sand-200 pt-6">
                <h2 class="font-display text-lg font-bold text-sand-900">Photos</h2>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($galleryImages as $image)
                        <figure class="overflow-hidden rounded-md bg-sand-200">
                            <a href="{{ route('establishmentImages.file', [$image, 'full']) }}" target="_blank" rel="noopener">
                                <img
                                    src="{{ route('establishmentImages.file', [$image, 'thumbnail']) }}"
                                    alt="{{ $image->img_alt_text ?: $listing->lst_name }}"
                                    loading="lazy"
                                    class="h-32 w-full object-cover transition-transform hover:scale-105 sm:h-36"
                                >
                            </a>
                            @if ($image->img_credit)
                                <figcaption class="px-1.5 py-1 text-[11px] text-sand-500">{{ $image->img_credit }}</figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Location map (destinations with a valid stored location only — never a fabricated point). --}}
        @if ($isDestination)
            <section id="location" class="mt-8 scroll-mt-6 border-t border-sand-200 pt-6">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <h2 class="font-display text-lg font-bold text-sand-900">Location</h2>
                    @if ($mapData)
                        <x-map-legend :items="['reference' => 'This destination', 'destination' => 'Nearby destination', 'establishment' => 'Nearby tourism service']" />
                    @endif
                </div>

                @if ($mapData)
                    <div data-map-frame class="relative mt-3 h-72 overflow-hidden rounded-md border border-sand-200 bg-gradient-to-b from-primary-100 to-sand-100 sm:h-96">
                        {{-- The wrapper owns the absolute positioning (mapbox-gl.css sets .mapboxgl-map { position: relative }). --}}
                        <div class="absolute inset-0">
                            <div
                                id="listing-map"
                                role="region"
                                aria-label="Map of {{ $listing->lst_name }} and nearby tourism services"
                                data-mapbox-token="{{ \App\Support\MapboxToken::browserToken() }}"
                                class="relative h-full w-full"
                            ></div>
                        </div>
                        <x-map-fallback hint="The address and the nearby list below still work." />
                    </div>
                    <script type="application/json" id="listing-map-data">@json($mapData)</script>
                    @if ($directionsUrl)
                        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1">
                            {{-- Destination coordinates only (App\Support\DirectionsLink) — never the visitor's location. --}}
                            <a href="{{ $directionsUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 rounded-sm bg-primary-700 px-3.5 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                                <i class="ti ti-route" aria-hidden="true"></i>
                                Get Directions
                                <span class="sr-only">(opens Google Maps in a new tab)</span>
                            </a>
                            <span class="text-xs text-sand-500">Opens Google Maps with this destination. iTOUR never shares your location.</span>
                        </div>
                    @endif
                @else
                    <p class="mt-3 rounded-md border border-sand-200 bg-sand-0 px-4 py-3 text-sm text-sand-600">
                        <i class="ti ti-map-pin-off mr-1 text-sand-400" aria-hidden="true"></i>
                        Map location not set yet. {{ $listing->lst_barangay }}, {{ $listing->lst_municipality }}.
                    </p>
                @endif
            </section>
        @endif

        {{-- Nearby Tourism Services (destinations): grouped by category, nearest first, from NearbySearchService. --}}
        @if ($isDestination)
            <section id="nearby" class="mt-8 scroll-mt-6 border-t border-sand-200 pt-6">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="font-display text-lg font-bold text-sand-900">Nearby Tourism Services</h2>
                        <p class="mt-1 text-xs text-sand-500">Within {{ (int) $nearbyRadiusKm }} km · straight-line distance, not road distance.</p>
                    </div>
                    @if ($hasLocation && $listing->isPubliclyVisible())
                        <a href="{{ route('listings.nearby', $listing) }}" class="inline-flex items-center gap-1.5 rounded-sm border border-sand-300 bg-sand-0 px-3.5 py-2 text-sm font-semibold text-sand-800 hover:border-primary-300 hover:text-primary-700">
                            <i class="ti ti-radar" aria-hidden="true"></i>
                            Find Nearby
                        </a>
                    @endif
                </div>

                @if (! $hasLocation)
                    <p class="mt-4 rounded-md border border-sand-200 bg-sand-0 px-4 py-3 text-sm text-sand-600">
                        This destination's map location hasn't been set yet, so nearby services can't be shown.
                        <a href="{{ route('explore') }}" class="font-semibold text-primary-700 hover:text-primary-900">Browse the full directory</a>.
                    </p>
                @elseif ($isNearbyUnavailable)
                    <p class="mt-4 rounded-md border border-sand-200 bg-sand-0 px-4 py-3 text-sm text-sand-600">
                        Nearby services can't be shown right now. Please try again later, or
                        <a href="{{ route('explore') }}" class="font-semibold text-primary-700 hover:text-primary-900">browse the full directory</a>.
                    </p>
                @elseif ($nearbyGroups->isEmpty())
                    <div class="mt-4 rounded-md border border-sand-200 bg-sand-0 px-4 py-4 text-sm text-sand-600">
                        <p class="font-semibold text-sand-900">No tourism services were found within {{ (int) $nearbyRadiusKm }} km.</p>
                        <p class="mt-1">
                            @if ($listing->isPubliclyVisible())
                                <a href="{{ route('listings.nearby', [$listing, 'radius' => 50]) }}" class="font-semibold text-primary-700 hover:text-primary-900">Search up to 50 km away</a>
                                or
                            @endif
                            <a href="{{ route('explore') }}" class="font-semibold text-primary-700 hover:text-primary-900">browse the full directory</a>.
                        </p>
                    </div>
                @else
                    <div class="mt-4 grid grid-cols-1 gap-5 md:grid-cols-2">
                        @foreach ($nearbyGroups as $arrGroup)
                            <section class="rounded-md border border-sand-200 bg-sand-0 p-4 shadow-sm">
                                <h3 class="font-display text-base font-bold text-sand-900">{{ $arrGroup['label'] }}</h3>
                                <ul class="mt-2 divide-y divide-sand-100">
                                    @foreach ($arrGroup['items'] as $arrItem)
                                        <li data-map-item="{{ $arrItem['slug'] }}" class="-mx-2 flex items-start justify-between gap-3 rounded-sm px-2 py-2.5 transition-colors">
                                            <div class="min-w-0">
                                                <a href="{{ $arrItem['url'] }}" class="font-semibold text-sand-900 hover:text-primary-700">{{ $arrItem['name'] }}</a>
                                                <p class="text-xs text-sand-500">{{ $arrItem['subtype'] ?: $arrItem['category'] }} · {{ $arrItem['municipality'] }}</p>
                                                @if ($mapData)
                                                    <button type="button" data-map-focus="{{ $arrItem['slug'] }}" class="mt-0.5 inline-flex items-center gap-1 text-xs font-semibold text-sand-600 hover:text-primary-700">
                                                        <i class="ti ti-map-pin" aria-hidden="true"></i>
                                                        Show on map
                                                    </button>
                                                @endif
                                            </div>
                                            <div class="shrink-0 text-right">
                                                <p class="text-xs font-semibold text-sand-700">{{ $arrItem['distanceLabel'] }}</p>
                                                <a href="{{ $arrItem['url'] }}" class="text-xs font-semibold text-primary-700 hover:text-primary-900">View Details</a>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($arrGroup['categorySlug'] && $listing->isPubliclyVisible())
                                    <a href="{{ route('listings.nearby', [$listing, 'category' => $arrGroup['categorySlug']]) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary-700 hover:text-primary-900">
                                        See all nearby
                                        <i class="ti ti-arrow-right" aria-hidden="true"></i>
                                    </a>
                                @endif
                            </section>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-layouts.public>
