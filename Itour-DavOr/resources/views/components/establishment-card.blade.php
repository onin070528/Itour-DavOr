@props(['establishment'])

<article class="group flex gap-4 rounded-md border border-sand-200 bg-sand-0 p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
    <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-md bg-sand-200">
        <img
            src="{{ asset('storage/itour-images/'.$establishment['image']) }}"
            alt="{{ $establishment['name'] }}"
            loading="lazy"
            class="h-full w-full object-cover"
        >
    </div>

    <div class="flex flex-1 flex-col gap-1.5">
        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
            <h3 class="font-display text-base font-bold text-sand-900">{{ $establishment['name'] }}</h3>
            <span class="inline-flex items-center gap-1 text-xs font-semibold text-sand-700">
                <i class="ti ti-star text-accent-500" aria-hidden="true"></i>
                {{ number_format($establishment['rating'], 1) }}
            </span>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-sm bg-secondary-100 px-2 py-0.5 font-semibold text-secondary-700">{{ \App\Support\TourismCatalog::categoryLabel($establishment['category']) }}</span>
            <span class="flex items-center gap-1 text-sand-500">
                <i class="ti ti-map-pin" aria-hidden="true"></i>
                {{ $establishment['municipality'] }}
            </span>
        </div>

        <p class="text-sm leading-relaxed text-sand-600">{{ $establishment['description'] }}</p>

        <a href="{{ $establishment['href'] }}" class="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 transition-colors group-hover:text-primary-900">
            View Details
            <i class="ti ti-arrow-right transition-transform group-hover:translate-x-0.5" aria-hidden="true"></i>
        </a>
    </div>
</article>
