@props(['icon', 'label', 'description', 'href' => '#', 'tone' => 'primary'])

@php
    $toneClasses = match ($tone) {
        'secondary' => 'bg-secondary-100 text-secondary-700 group-hover:bg-secondary-700 group-hover:text-sand-0',
        'accent' => 'bg-accent-100 text-accent-700 group-hover:bg-accent-500 group-hover:text-sand-0',
        default => 'bg-primary-100 text-primary-700 group-hover:bg-primary-700 group-hover:text-sand-0',
    };
@endphp

<a
    href="{{ $href }}"
    class="group flex flex-col items-start gap-3 rounded-md border border-sand-200 bg-sand-0 p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md"
>
    <span class="flex h-11 w-11 items-center justify-center rounded-md text-xl transition-colors {{ $toneClasses }}">
        <i class="ti {{ $icon }}" aria-hidden="true"></i>
    </span>
    <span class="font-display text-base font-semibold text-sand-900">{{ $label }}</span>
    <span class="text-sm leading-relaxed text-sand-600">{{ $description }}</span>
</a>
