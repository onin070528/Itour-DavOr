{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Find Near Me (Objective 3, R10) — explanation and privacy notice, Allow Location / Not Now,
    radius and category, and the server's nearest results. Driven by resources/js/find_near_me.js; the
    location is sent once by POST JSON to ExploreController::nearMe() and never stored.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['headingLevel' => 'h3'])

@php
    // Radius options and default come from config/tourism_directory.php only — the script reads this select.
    $arrRadiusOptions = config('tourism_directory.nearby.radius_options_km');
    $intDefaultRadius = (int) config('tourism_directory.nearby.default_radius_km');
    $colCategories = \App\Models\Category::query()->active()->get();
    $strId = 'find-near-me-'.\Illuminate\Support\Str::random(6);
@endphp

<div
    data-find-near-me
    data-endpoint="{{ route('findNearMe', [], false) }}"
    {{ $attributes->merge(['class' => 'rounded-md border border-sand-200 bg-sand-0 p-4 text-left text-sand-900 shadow-sm']) }}
>
    <{{ $headingLevel }} class="flex items-center gap-2 font-display text-base font-bold text-sand-900">
        <i class="ti ti-current-location text-primary-700" aria-hidden="true"></i>
        Find Near Me
    </{{ $headingLevel }}>
    <p class="mt-1 text-sm text-sand-600">See tourism destinations and services near where you are right now.</p>

    <p class="mt-3 flex items-start gap-2 rounded-sm bg-sand-50 px-3 py-2 text-xs text-sand-600">
        <i class="ti ti-shield-lock mt-0.5 text-primary-700" aria-hidden="true"></i>
        <span>iTOUR uses your current location only to identify nearby tourism destinations and services. Your exact location is not permanently stored.</span>
    </p>

    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
        <div>
            <label for="{{ $strId }}-radius" class="mb-1 block text-xs font-semibold text-sand-700">Within</label>
            <select id="{{ $strId }}-radius" data-find-near-me-radius class="w-full rounded-sm border border-sand-300 bg-sand-50 px-3 py-2 text-sm text-sand-900">
                @foreach ($arrRadiusOptions as $intOption)
                    <option value="{{ $intOption }}" @selected($intOption === $intDefaultRadius)>{{ $intOption }} km</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="{{ $strId }}-category" class="mb-1 block text-xs font-semibold text-sand-700">Category</label>
            <select id="{{ $strId }}-category" data-find-near-me-category class="w-full rounded-sm border border-sand-300 bg-sand-50 px-3 py-2 text-sm text-sand-900">
                <option value="">All categories</option>
                @foreach ($colCategories as $objCategory)
                    <option value="{{ $objCategory->legacySlug() }}">{{ $objCategory->isDestinationCategory() ? 'Destinations' : $objCategory->cat_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
        <button type="button" data-find-near-me-allow class="inline-flex items-center gap-1.5 rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900 disabled:cursor-wait disabled:opacity-70">
            <i class="ti ti-current-location" aria-hidden="true"></i>
            <span data-find-near-me-allow-label>Allow Location</span>
        </button>
        <button type="button" data-find-near-me-dismiss class="inline-flex items-center rounded-sm border border-sand-300 bg-sand-0 px-4 py-2 text-sm font-semibold text-sand-800 hover:border-primary-300">
            Not Now
        </button>
    </div>

    <p data-find-near-me-status role="status" aria-live="polite" class="mt-3 text-sm text-sand-700" hidden></p>

    <div data-find-near-me-actions class="mt-2 flex flex-wrap gap-2" hidden>
        <button type="button" data-find-near-me-wider hidden class="inline-flex items-center rounded-sm border border-sand-300 bg-sand-0 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">Search a wider area</button>
        <a href="{{ route('explore') }}" class="inline-flex items-center rounded-sm border border-sand-300 bg-sand-0 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">Browse the full directory</a>
    </div>

    <ol data-find-near-me-results class="mt-3 max-h-96 divide-y divide-sand-100 overflow-y-auto rounded-sm border border-sand-200" aria-label="Places near you" hidden></ol>
    <p data-find-near-me-note class="mt-2 text-xs text-sand-500" hidden>Distances are straight-line, not road distance.</p>

    <noscript>
        <p class="mt-3 text-sm text-sand-600">Find Near Me needs JavaScript. <a href="{{ route('explore') }}" class="font-semibold text-primary-700">Browse the full directory</a> instead.</p>
    </noscript>
</div>
