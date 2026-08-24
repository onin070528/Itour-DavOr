@props(['review'])

<article class="flex flex-col gap-4 rounded-md border border-sand-200 bg-sand-0 p-5 shadow-sm">
    <div class="flex items-center gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 font-display text-sm font-bold text-primary-700">
            {{ Str::of($review['name'])->substr(0, 1) }}
        </span>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-sand-900">{{ $review['name'] }}</p>
            <p class="flex items-center gap-1 text-xs text-sand-500">
                <i class="ti ti-map-pin" aria-hidden="true"></i>
                {{ $review['subject'] }}
            </p>
        </div>
    </div>

    <div class="flex items-center gap-1" aria-label="{{ $review['rating'] }} out of 5 stars">
        @for ($i = 1; $i <= 5; $i++)
            <i class="ti ti-star-filled text-sm {{ $i <= $review['rating'] ? 'text-accent-500' : 'text-sand-200' }}" aria-hidden="true"></i>
        @endfor
    </div>

    <p class="text-sm leading-relaxed text-sand-700">&ldquo;{{ $review['text'] }}&rdquo;</p>

    <p class="text-xs text-sand-500">{{ $review['date'] }}</p>
</article>
