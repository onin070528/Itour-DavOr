@props(['eyebrow' => null, 'description' => null, 'actionLabel' => null, 'actionHref' => null])

<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="max-w-xl">
        @if ($eyebrow)
            <p class="text-xs font-bold tracking-widest text-primary-700 uppercase">{{ $eyebrow }}</p>
        @endif
        <h2 class="mt-2 text-2xl sm:text-3xl">{{ $slot }}</h2>
        @if ($description)
            <p class="mt-3 text-sm leading-relaxed text-sand-600 sm:text-base">{{ $description }}</p>
        @endif
    </div>

    @if ($actionLabel && $actionHref)
        <a href="{{ $actionHref }}" class="inline-flex shrink-0 items-center gap-1.5 text-sm font-semibold text-primary-700 transition-colors hover:text-primary-900">
            {{ $actionLabel }}
            <i class="ti ti-arrow-right" aria-hidden="true"></i>
        </a>
    @endif
</div>
