@props(['tone' => 'neutral'])

@php
    $classes = match ($tone) {
        'success' => 'bg-success-bg text-success',
        'warning' => 'bg-warning-bg text-warning',
        'danger' => 'bg-danger-bg text-danger',
        default => 'bg-sand-200 text-sand-700',
    };
@endphp

<span class="inline-flex items-center gap-1.5 rounded-sm px-2 py-0.5 text-xs font-semibold {{ $classes }}">
    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
    {{ $slot }}
</span>
