@props(['dark' => false])

@php
    $textColor = $dark ? 'text-sand-0' : 'text-primary-700';
    $dotColor = 'bg-accent-500';
    $stemColor = $dark ? 'bg-sand-0' : 'bg-primary-700';
    $ringLeft = $dark ? '#7FB8B3' : '#3F7D5C';
    $ringRight = $dark ? '#9AC1A8' : '#125D5A';
    $textStroke = 'style="-webkit-text-stroke:0.022em currentColor;"';
@endphp

{{-- The iTOUR wordmark: a custom dot+stem "i" (orange tittle) and a
     two-tone ring standing in for the "O" — one clean circle split cleanly
     down the middle, not overlapping blobs — flowed between real "T" and
     "UR" text so kerning stays native. A hairline text-stroke bulks up the
     glyphs beyond Manrope's heaviest weight. Colors flip via $dark for use
     on the dashboard sidebar. --}}
<span {{ $attributes->class(['inline-flex items-baseline font-display font-extrabold tracking-tight']) }}>
    <span class="relative inline-block align-baseline" style="width:0.3em;">
        <span class="absolute rounded-full {{ $dotColor }}" style="width:0.18em; height:0.18em; top:-0.26em; left:0.005em;"></span>
        <span class="block rounded-[0.03em] {{ $stemColor }}" style="width:0.14em; height:0.66em; margin-left:0.065em;"></span>
    </span>
    <span class="{{ $textColor }}" {!! $textStroke !!}>T</span>
    <svg viewBox="0 0 100 100" class="inline-block h-[0.78em] w-[0.78em] -mx-[0.02em] translate-y-[0.05em]" aria-hidden="true">
        <path d="M 50 15 A 35 35 0 0 0 50 85" fill="none" stroke="{{ $ringLeft }}" stroke-width="20" stroke-linecap="round" />
        <path d="M 50 15 A 35 35 0 0 1 50 85" fill="none" stroke="{{ $ringRight }}" stroke-width="20" stroke-linecap="round" />
    </svg>
    <span class="{{ $textColor }}" {!! $textStroke !!}>UR</span>
</span>
