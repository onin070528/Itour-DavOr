@props(['listing'])

{{-- QR status cell for a directory table row (PTO Tourism Directory, LGU
     Establishments). Status comes from Listing::getQrStatus(); a usable or
     switched-off QR opens the matching <x-dashboard.qr-modal>. --}}
@php($strQrStatus = $listing->getQrStatus())

@switch($strQrStatus)
    @case(\App\Models\Listing::QR_STATUS_ACTIVE)
        <button type="button" data-modal-open="qr-view-{{ $listing->id }}" class="rounded-sm border border-sand-300 px-2.5 py-1 text-xs font-semibold text-sand-800 hover:border-primary-300">
            <i class="ti ti-qrcode" aria-hidden="true"></i> View
        </button>
        @break

    @case(\App\Models\Listing::QR_STATUS_SWITCHED_OFF)
        <button type="button" data-modal-open="qr-view-{{ $listing->id }}" class="rounded-sm border border-warning/40 bg-warning-bg px-2.5 py-1 text-xs font-semibold text-warning hover:border-warning">
            <i class="ti ti-player-pause" aria-hidden="true"></i> QR off
        </button>
        @break

    @case(\App\Models\Listing::QR_STATUS_MANUAL_REPORTING)
        <span class="text-xs text-sand-500" title="Reports on paper — arrivals are recorded through LGU manual/paper entry.">Manual/Paper</span>
        @break

    @case(\App\Models\Listing::QR_STATUS_NO_ACCOUNT)
        <span class="text-xs text-sand-500" title="Online iTOUR, but no active establishment account — QR check-in starts once the account is active.">No active account</span>
        @break

    @default
        <span class="text-xs text-sand-400">No QR</span>
@endswitch
