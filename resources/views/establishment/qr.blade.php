@php
    // A deterministic, QR-shaped placeholder pattern (finder squares + a
    // pseudo-random module field seeded from the establishment name) — it
    // is not a real scannable code. Once QR generation exists on the
    // backend, this view swaps in the generated image.
    $size = 21;
    $seed = crc32($establishmentName ?? 'iTOUR');
    $isFinder = fn ($r, $c) => ($r < 7 && $c < 7) || ($r < 7 && $c >= $size - 7) || ($r >= $size - 7 && $c < 7);
    $finderOn = function ($r, $c) use ($size) {
        $localR = $r < 7 ? $r : ($c < 7 ? $r - ($size - 7) : $r);
        $localC = $c < 7 ? $c : $c - ($size - 7);
        $onBorder = $localR === 0 || $localR === 6 || $localC === 0 || $localC === 6;
        $onCore = $localR >= 2 && $localR <= 4 && $localC >= 2 && $localC <= 4;

        return $onBorder || $onCore;
    };
    $modules = [];
    for ($r = 0; $r < $size; $r++) {
        for ($c = 0; $c < $size; $c++) {
            if ($isFinder($r, $c)) {
                $modules[] = ['r' => $r, 'c' => $c, 'on' => $finderOn($r, $c)];

                continue;
            }
            $on = (crc32("{$seed}-{$r}-{$c}") % 2) === 0;
            $modules[] = ['r' => $r, 'c' => $c, 'on' => $on];
        }
    }
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="QR Code"
        description="Tourists scan this establishment-specific QR code to reach the tourist arrival form for {{ $establishmentName }}."
    />

    <div class="mt-6 flex flex-col items-center gap-5 rounded-md border border-sand-200 bg-sand-0 p-8 text-center" id="qr-card">
        <x-dashboard.status-badge tone="success">Active</x-dashboard.status-badge>

        <p class="font-display text-lg font-bold text-sand-900">{{ $establishmentName }}</p>

        <svg id="establishment-qr-svg" viewBox="0 0 {{ $size }} {{ $size }}" class="h-56 w-56 rounded-md border border-sand-200 bg-sand-0 p-2 sm:h-64 sm:w-64">
            <rect width="{{ $size }}" height="{{ $size }}" fill="white" />
            @foreach ($modules as $m)
                @if ($m['on'])
                    <rect x="{{ $m['c'] }}" y="{{ $m['r'] }}" width="1" height="1" fill="#211F1A" />
                @endif
            @endforeach
        </svg>

        <p class="max-w-xs text-xs text-sand-500">Sample QR for preview purposes — connect QR generation on the backend to make this scannable.</p>

        <div class="max-w-sm rounded-md bg-primary-100 px-4 py-3 text-sm text-primary-900">
            <i class="ti ti-info-circle" aria-hidden="true"></i>
            Ask guests to scan this code on arrival to fill out the tourist arrival form themselves.
        </div>

        <div class="flex flex-wrap items-center justify-center gap-2">
            <button type="button" id="qr-download" data-qr-filename="{{ str($establishmentName ?? 'itour')->slug() }}-qr-code.svg" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                <i class="ti ti-download" aria-hidden="true"></i>
                Download QR Code
            </button>
            <button type="button" id="qr-print" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-printer" aria-hidden="true"></i>
                Print QR Code
            </button>
        </div>
    </div>

    <style media="print">
        body * { visibility: hidden; }
        #qr-card, #qr-card * { visibility: visible; }
        #qr-card { position: fixed; inset: 0; border: none; }
    </style>
</x-layouts.dashboard>
