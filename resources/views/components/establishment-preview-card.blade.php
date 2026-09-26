@props(['listing'])

<article class="group relative flex flex-col">
    <div class="h-40 overflow-hidden rounded-md bg-sand-200">
        <img
            src="{{ asset('storage/itour-images/'.$listing['image']) }}"
            alt="{{ $listing['name'] }}"
            loading="lazy"
            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
        >
    </div>

    <p class="mt-4 text-xs font-bold tracking-widest text-accent-700 uppercase">{{ \App\Support\TourismCatalog::categoryLabel($listing['category']) }}</p>
    <h3 class="mt-1 font-display text-base font-bold text-sand-900">{{ $listing['name'] }}</h3>

    <div class="mt-3 flex items-center justify-between border-t border-sand-200 pt-3 text-sm">
        <span class="text-sand-500">{{ $listing['municipality'] }}</span>
        <span class="inline-flex items-center gap-1 font-semibold text-sand-800">
            <i class="ti ti-star text-accent-500" aria-hidden="true"></i>
            {{ number_format($listing['rating'], 1) }}
        </span>
    </div>

    <a href="{{ $listing['href'] }}" class="absolute inset-0" aria-label="View {{ $listing['name'] }}"></a>
</article>
