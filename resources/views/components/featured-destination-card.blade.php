@props(['destination'])

<article class="group flex flex-col overflow-hidden rounded-md border border-sand-200 bg-sand-0 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
    <div class="relative flex h-44 items-end overflow-hidden bg-gradient-to-br from-primary-500 to-secondary-500">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,255,255,0.18),transparent_55%)]"></div>
        <i class="ti ti-photo absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-[60%] text-4xl text-white/50" aria-hidden="true"></i>
        <span class="relative m-3 rounded-sm bg-sand-900/40 px-2.5 py-1 text-xs font-semibold text-sand-0">{{ $destination['municipality'] }}</span>
    </div>

    <div class="flex flex-1 flex-col gap-2 p-5">
        <div class="flex items-start justify-between gap-2">
            <h3 class="font-display text-lg font-bold text-sand-900">{{ $destination['name'] }}</h3>
            <span class="mt-0.5 inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-sand-800">
                <i class="ti ti-star-filled text-accent-500" aria-hidden="true"></i>
                {{ number_format($destination['rating'], 1) }}
            </span>
        </div>

        <p class="flex items-center gap-1 text-xs font-medium text-sand-500">
            <i class="ti ti-map-pin" aria-hidden="true"></i>
            {{ $destination['municipality'] }}, Davao Oriental
        </p>

        <p class="text-sm leading-relaxed text-sand-600">{{ $destination['description'] }}</p>

        <div class="mt-1 flex flex-wrap gap-1.5">
            @foreach ($destination['tags'] as $tag)
                <span class="rounded-sm bg-sand-100 px-2 py-1 text-xs font-medium text-sand-700">{{ $tag }}</span>
            @endforeach
        </div>

        <a href="{{ $destination['href'] }}" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 transition-colors group-hover:text-primary-900">
            View Details
            <i class="ti ti-arrow-right transition-transform group-hover:translate-x-0.5" aria-hidden="true"></i>
        </a>
    </div>
</article>
