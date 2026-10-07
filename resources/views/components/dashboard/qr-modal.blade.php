@props(['listing', 'canManage' => false])

{{-- The one QR modal shared by the PTO Tourism Directory and the LGU
     Establishments page (pair with <x-dashboard.qr-cell>). The QR image is
     the branded SVG from QrCodeController::show (App\Services\QrCodeService),
     lazy-loaded so a page with many establishments only fetches the codes
     actually opened. Print Poster / Download go to the authorized endpoints;
     the on/off switch (QrCodeController::updateStatus) shows only when
     $canManage (ListingPolicy::manageQr()). Renders nothing for a listing
     with no QR to show. --}}
@php($strQrStatus = $listing->getQrStatus())

@if (in_array($strQrStatus, [\App\Models\Listing::QR_STATUS_ACTIVE, \App\Models\Listing::QR_STATUS_SWITCHED_OFF], true))
    <x-dashboard.modal id="qr-view-{{ $listing->lst_id }}" title="{{ $listing->lst_name }} QR Code">
        @if ($strQrStatus === \App\Models\Listing::QR_STATUS_ACTIVE)
            <div class="mx-auto flex h-56 w-56 items-center justify-center rounded-md border border-sand-200 bg-sand-0 p-2">
                <img src="{{ route('qrCodes.show', $listing) }}" loading="lazy" alt="Check-in QR code for {{ $listing->lst_name }}" class="h-full w-full">
            </div>
            <p class="mt-3 text-center text-xs text-sand-500">Tourists scan this to register their arrival at {{ $listing->lst_name }}.</p>
        @else
            <div class="flex flex-col items-center gap-2 rounded-md bg-warning-bg px-4 py-6 text-center">
                <i class="ti ti-player-pause text-2xl text-warning" aria-hidden="true"></i>
                <p class="text-sm font-semibold text-sand-900">QR check-in is turned off</p>
                <p class="text-xs text-sand-600">Guests who scan the QR code for {{ $listing->lst_name }} see that it is not accepting registrations. The same printed QR code works again once it is turned back on.</p>
            </div>
        @endif

        @if ($canManage)
            <form method="POST" action="{{ route('qrCodes.updateStatus', $listing) }}" class="mt-4 flex items-center justify-between gap-3 border-t border-sand-200 pt-4">
                @csrf
                @method('PATCH')
                @if ($strQrStatus === \App\Models\Listing::QR_STATUS_ACTIVE)
                    <input type="hidden" name="is_enabled" value="0">
                    <p class="text-xs text-sand-600">QR check-in is <b class="text-success">on</b>.</p>
                    <button
                        type="button"
                        data-confirm-trigger
                        data-confirm-title="Turn off QR check-in for {{ $listing->lst_name }}?"
                        data-confirm-message="Guests who scan its QR code will see that it is not accepting registrations until it is turned back on. Existing arrivals are untouched."
                        data-confirm-label="Turn off"
                        data-confirm-tone="danger"
                        class="inline-flex items-center gap-1.5 rounded-sm border border-sand-300 px-3 py-2 text-xs font-semibold text-sand-800 hover:border-danger hover:text-danger"
                    >
                        <i class="ti ti-player-pause" aria-hidden="true"></i> Turn off
                    </button>
                @else
                    <input type="hidden" name="is_enabled" value="1">
                    <p class="text-xs text-sand-600">QR check-in is <b class="text-warning">off</b>.</p>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-sm bg-primary-700 px-3 py-2 text-xs font-semibold text-sand-0 hover:bg-primary-900">
                        <i class="ti ti-player-play" aria-hidden="true"></i> Turn back on
                    </button>
                @endif
            </form>
        @endif

        @if ($strQrStatus === \App\Models\Listing::QR_STATUS_ACTIVE)
            <x-slot:footer>
                <a href="{{ route('qrCodes.poster', $listing) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    <i class="ti ti-printer" aria-hidden="true"></i> Print Poster
                </a>
                <a href="{{ route('qrCodes.download', $listing) }}" class="inline-flex items-center gap-1.5 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                    <i class="ti ti-download" aria-hidden="true"></i> Download QR (SVG)
                </a>
            </x-slot:footer>
        @endif
    </x-dashboard.modal>
@endif
