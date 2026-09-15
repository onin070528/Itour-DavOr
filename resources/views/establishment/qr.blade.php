<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="QR Code"
        description="Tourists scan this establishment-specific QR code to reach the tourist arrival form for {{ $establishmentName }}."
    />

    @if ($checkinUrl)
        <div class="mt-6 flex flex-col items-center gap-5 rounded-md border border-sand-200 bg-sand-0 p-8 text-center" id="qr-card">
            <x-dashboard.status-badge tone="success">Active</x-dashboard.status-badge>

            <p class="font-display text-lg font-bold text-sand-900">{{ $establishmentName }}</p>

            {{-- Rendered client-side into a real, scannable SVG QR code —
                 see initQrActions() in resources/js/establishment.js. Each
                 establishment's data-qr-value is its own unique check-in
                 URL, so no two establishments share a code. --}}
            <div
                id="establishment-qr-mount"
                data-qr-value="{{ $checkinUrl }}"
                class="flex h-56 w-56 items-center justify-center rounded-md border border-sand-200 bg-sand-0 p-2 sm:h-64 sm:w-64"
                aria-label="QR code for {{ $establishmentName }} check-in"
            >
                <span class="text-xs text-sand-400">Generating QR code…</span>
            </div>

            <p class="max-w-xs break-all font-mono text-[11px] text-sand-400">{{ $checkinUrl }}</p>

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
    @else
        <x-dashboard.empty-state
            class="mt-6"
            icon="ti-qrcode"
            title="No directory listing found"
            description="{{ $establishmentName }} isn't linked to a tourism directory listing yet, so a check-in QR code can't be generated. Contact your LGU to get listed."
        />
    @endif
</x-layouts.dashboard>
