@props(['listing'])

<article class="group flex flex-col overflow-hidden rounded-md border border-sand-200 bg-sand-0 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
    <div class="relative h-44 overflow-hidden bg-sand-200">
        <img
            src="{{ asset('storage/itour-images/'.$listing['image']) }}"
            alt="{{ $listing['name'] }}"
            loading="lazy"
            class="absolute inset-0 h-full w-full object-cover"
        >
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-sand-900/55 via-transparent to-transparent"></div>
        <span class="relative m-3 inline-block rounded-sm bg-sand-900/45 px-2.5 py-1 text-xs font-semibold text-sand-0">
            {{ \App\Support\TourismCatalog::categoryLabel($listing['category']) }}
        </span>
    </div>

    <div class="flex flex-1 flex-col gap-2 p-5">
        <div class="flex items-start justify-between gap-2">
            <h3 class="font-display text-lg font-bold text-sand-900">{{ $listing['name'] }}</h3>
            <span class="mt-0.5 inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-sand-800">
                <i class="ti ti-star text-accent-500" aria-hidden="true"></i>
                {{ number_format($listing['rating'], 1) }}
            </span>
        </div>

        <p class="flex items-center gap-1 text-xs font-medium text-sand-500">
            <i class="ti ti-map-pin" aria-hidden="true"></i>
            {{ $listing['barangay'] }}, {{ $listing['municipality'] }}
        </p>

        <p class="text-sm leading-relaxed text-sand-600">{{ $listing['description'] }}</p>

        <a href="{{ $listing['href'] }}" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 transition-colors group-hover:text-primary-900">
            View Details
            <i class="ti ti-arrow-right transition-transform group-hover:translate-x-0.5" aria-hidden="true"></i>
        </a>
    </div>
</article>
