@props(['listing'])

{{-- Used by the landing page's "Signature Experiences" showcase — one card
     style for both destinations and establishments, since both are rows of
     the same listing shape (see App\Support\TourismCatalog::listings()). --}}
<article class="group flex flex-col overflow-hidden rounded-md border border-sand-200 bg-sand-0 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
    <div class="relative flex h-44 items-end overflow-hidden bg-sand-200">
        <img
            src="{{ asset('storage/itour-images/'.$listing['image']) }}"
            alt="{{ $listing['name'] }}, {{ $listing['municipality'] }}"
            loading="lazy"
            class="absolute inset-0 h-full w-full object-cover"
        >
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-sand-900/55 via-transparent to-transparent"></div>
        <span class="relative m-3 inline-flex items-center gap-1 rounded-sm bg-sand-900/45 px-2.5 py-1 text-xs font-semibold tracking-wide text-sand-0 uppercase">
            <i class="ti ti-map-pin text-[11px]" aria-hidden="true"></i>
            {{ $listing['municipality'] }}
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

        <p class="line-clamp-2 text-sm leading-relaxed text-sand-600">{{ $listing['description'] }}</p>

        <div class="mt-1 flex flex-wrap gap-1.5">
            @foreach ($listing['tags'] as $tag)
                <span class="rounded-sm bg-sand-100 px-2 py-1 text-xs font-medium text-sand-700">{{ $tag }}</span>
            @endforeach
        </div>

        <a href="{{ $listing['href'] }}" class="mt-auto inline-flex items-center gap-1.5 pt-2 text-sm font-semibold text-primary-700 transition-colors group-hover:text-primary-900">
            View Details
            <i class="ti ti-arrow-right transition-transform group-hover:translate-x-0.5" aria-hidden="true"></i>
        </a>
    </div>
</article>
