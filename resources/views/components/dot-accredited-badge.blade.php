{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Public "DOT Accredited" badge — shown only when Listing::isDotAccredited() is true. The text
    carries the meaning (never color alone); the title explains it.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-sm border border-primary-300 bg-primary-100 px-2 py-0.5 text-[11px] font-semibold tracking-wide text-primary-900']) }} title="Accredited by the Department of Tourism (DOT)">
    <i class="ti ti-rosette-discount-check text-sm text-primary-700" aria-hidden="true"></i>
    DOT Accredited
</span>
