{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : Status banner for the merged Establishment Profile page — first thing on
                 the page, per the Profile/Photos merge spec.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
@props(['label', 'tone' => 'neutral', 'reason' => null])

@php
    $strIconClass = match ($tone) {
        'success' => 'ti-circle-check',
        'info' => 'ti-clock-hour-4',
        'warning' => 'ti-arrow-back-up',
        default => 'ti-file-text',
    };

    $strWrapperClass = match ($tone) {
        'success' => 'border-success bg-success-bg',
        'info' => 'border-primary-300 bg-primary-100/40',
        'warning' => 'border-warning bg-warning-bg',
        default => 'border-sand-200 bg-sand-0',
    };

    $strIconWrapperClass = match ($tone) {
        'success' => 'bg-success text-sand-0',
        'info' => 'bg-primary-700 text-sand-0',
        'warning' => 'bg-warning text-sand-0',
        default => 'bg-sand-200 text-sand-700',
    };

    $strDescription = match (true) {
        $reason !== null => "Returned by your LGU tourism office: \"{$reason}\". Edit and resubmit when ready.",
        $tone === 'info' && $label === 'Waiting for LGU Review' => 'Your LGU tourism office is reviewing this submission. The form and photos are read-only until it\'s returned or submitted onward.',
        $tone === 'info' => 'Your submission is waiting for the Provincial Tourism Office. The form and photos are read-only until a decision is made.',
        $tone === 'success' => 'This listing is live and visible to visitors.',
        default => 'Fill in the details below and submit when ready.',
    };
@endphp

<div class="mt-6 flex items-start gap-3 rounded-md border p-4 {{ $strWrapperClass }}">
    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $strIconWrapperClass }}">
        <i class="ti {{ $strIconClass }} text-lg" aria-hidden="true"></i>
    </span>
    <div>
        <p class="font-display text-sm font-bold text-sand-900">{{ $label }}</p>
        <p class="mt-0.5 text-sm text-sand-600">{{ $strDescription }}</p>
    </div>
</div>
