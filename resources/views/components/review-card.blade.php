@props(['review'])

<article class="flex flex-col gap-3 border-t-2 border-primary-700 pt-5">
    <i class="ti ti-quote text-3xl text-accent-500" aria-hidden="true"></i>

    <p class="text-sm leading-relaxed text-sand-800 italic">&ldquo;{{ $review['text'] }}&rdquo;</p>

    <div class="mt-1 flex items-center gap-1" aria-label="{{ $review['rating'] }} out of 5 stars">
        @for ($i = 1; $i <= 5; $i++)
            <i class="ti ti-star text-sm {{ $i <= $review['rating'] ? 'text-accent-500' : 'text-sand-200' }}" aria-hidden="true"></i>
        @endfor
        <span class="ml-1 text-sm font-semibold text-sand-800">{{ number_format($review['rating'], 1) }}</span>
    </div>

    <p class="text-xs text-sand-500">{{ $review['name'] }} &middot; {{ $review['subject'] }}</p>
</article>
