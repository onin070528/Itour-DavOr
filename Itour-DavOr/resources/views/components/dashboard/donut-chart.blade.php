@props(['segments', 'centerLabel' => null, 'centerSublabel' => null])

@php
    $total = collect($segments)->sum('value') ?: 1;
    $cumulative = 0;
@endphp

<div class="relative inline-flex h-32 w-32 shrink-0 items-center justify-center">
    <svg viewBox="0 0 36 36" class="h-full w-full -rotate-90">
        <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--color-sand-200)" stroke-width="4" pathLength="100" />
        @foreach ($segments as $segment)
            @php
                $pct = ($segment['value'] / $total) * 100;
                $offset = -$cumulative;
                $cumulative += $pct;
            @endphp
            <circle
                cx="18" cy="18" r="15.9" fill="none"
                stroke="{{ $segment['color'] }}"
                stroke-width="4"
                stroke-dasharray="{{ $pct }} {{ 100 - $pct }}"
                stroke-dashoffset="{{ $offset }}"
                pathLength="100"
            />
        @endforeach
    </svg>

    @if ($centerLabel)
        <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="font-display text-lg font-extrabold text-sand-900">{{ $centerLabel }}</span>
            @if ($centerSublabel)
                <span class="text-[10px] text-sand-500">{{ $centerSublabel }}</span>
            @endif
        </div>
    @endif
</div>
