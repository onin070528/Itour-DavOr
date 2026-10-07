<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="QR Code"
        description="Tourists scan this establishment-specific QR code to reach the tourist arrival form for {{ $establishmentName }}."
    />

    @if ($isAcceptingRegistrations)
        <div class="mt-6 flex flex-col items-center gap-5 rounded-md border border-sand-200 bg-sand-0 p-8 text-center" id="qr-card">
            <x-dashboard.status-badge tone="success">Active</x-dashboard.status-badge>

            <p class="font-display text-lg font-bold text-sand-900">{{ $establishmentName }}</p>

            {{-- Rendered server-side as a real, scannable SVG QR code by
                 App\Services\QrCodeService — each establishment's
                 $checkinUrl is its own unique check-in URL, so no two
                 establishments share a code. Download and Print Poster go
                 to the authorized QrCodeController endpoints. --}}
            <div
                id="establishment-qr-svg"
                class="flex h-56 w-56 items-center justify-center rounded-md border border-sand-200 bg-sand-0 p-2 [&>svg]:h-full [&>svg]:w-full sm:h-64 sm:w-64"
                aria-label="QR code for {{ $establishmentName }} check-in"
            >
                @if ($qrSvg)
                    {!! $qrSvg !!}
                @else
                    <p class="text-sm text-sand-600">The QR code couldn't be generated right now. Please refresh the page in a moment.</p>
                @endif
            </div>

            <p class="max-w-xs break-all font-mono text-[11px] text-sand-400">{{ $checkinUrl }}</p>

            <div class="max-w-sm rounded-md bg-primary-100 px-4 py-3 text-sm text-primary-900">
                <i class="ti ti-info-circle" aria-hidden="true"></i>
                Ask guests to scan this code on arrival to fill out the tourist arrival form themselves.
            </div>

            <div class="flex flex-wrap items-center justify-center gap-2">
                <a href="{{ $qrDownloadUrl }}" id="qr-download" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                    <i class="ti ti-download" aria-hidden="true"></i>
                    Download QR (SVG)
                </a>
                <a href="{{ $qrPosterUrl }}" id="qr-print" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    <i class="ti ti-printer" aria-hidden="true"></i>
                    Print Poster
                </a>
            </div>
        </div>

        {{-- On/off switch (QrCodeController::updateStatus) — outside #qr-card so it never prints. --}}
        @if ($canManageQr)
            <form method="POST" action="{{ $qrStatusUrl }}" class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-sand-200 bg-sand-0 px-5 py-4">
                @csrf
                @method('PATCH')
                <input type="hidden" name="is_enabled" value="0">
                <div>
                    <p class="text-sm font-semibold text-sand-900">QR check-in is on</p>
                    <p class="text-xs text-sand-500">Closed for a while? Turn it off — scans will show "not accepting registrations" until you turn it back on. The QR code stays the same.</p>
                </div>
                <button
                    type="button"
                    data-confirm-trigger
                    data-confirm-title="Turn off QR check-in?"
                    data-confirm-message="Guests who scan your QR code will see that {{ $establishmentName }} is not accepting registrations. Your printed QR code keeps working once you turn it back on."
                    data-confirm-label="Turn off"
                    data-confirm-tone="danger"
                    class="inline-flex items-center gap-2 rounded-sm border border-sand-300 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-danger hover:text-danger"
                >
                    <i class="ti ti-player-pause" aria-hidden="true"></i>
                    Turn off QR check-in
                </button>
            </form>
        @endif

        <style media="print">
            body * { visibility: hidden; }
            #qr-card, #qr-card * { visibility: visible; }
            #qr-card { position: fixed; inset: 0; border: none; }
        </style>
    @elseif ($isQrSwitchedOff)
        <x-dashboard.empty-state
            class="mt-6"
            icon="ti-player-pause"
            title="QR check-in is turned off"
            description="Guests who scan the QR code for {{ $establishmentName }} see that it is not accepting registrations. Turn it back on to accept QR check-ins again — the same printed QR code works."
        >
            @if ($canManageQr)
                <x-slot:action>
                    <form method="POST" action="{{ $qrStatusUrl }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="is_enabled" value="1">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                            <i class="ti ti-player-play" aria-hidden="true"></i>
                            Turn QR check-in back on
                        </button>
                    </form>
                </x-slot:action>
            @endif
        </x-dashboard.empty-state>
    @else
        <x-dashboard.empty-state
            class="mt-6"
            icon="ti-qrcode"
            title="QR check-in isn't available yet"
            description="{{ $establishmentName }} can't accept QR check-ins right now. A check-in QR code appears here once your LGU has set your establishment to Online iTOUR reporting with an active account, and QR check-in is switched on for it. Contact your LGU if you think this is wrong."
        />
    @endif
</x-layouts.dashboard>
