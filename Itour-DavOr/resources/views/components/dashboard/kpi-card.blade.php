@props(['label', 'value', 'delta' => null, 'tone' => 'neutral'])

@php
    $deltaColor = match ($tone) {
        'success' => 'text-success',
        'warning' => 'text-warning',
        'danger' => 'text-danger',
        default => 'text-sand-500',
    };
@endphp

<div class="rounded-md border border-sand-200 bg-sand-0 p-4">
    <p class="text-xs font-medium text-sand-500">{{ $label }}</p>
    <p class="mt-1.5 font-display text-2xl font-extrabold text-sand-900">{{ $value }}</p>
    @if ($delta)
        <p class="mt-1 flex items-center gap-1 text-xs font-semibold {{ $deltaColor }}">{{ $delta }}</p>
    @endif
</div>
