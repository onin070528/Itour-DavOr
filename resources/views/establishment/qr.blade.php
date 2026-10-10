{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Establishment QR codes page — one QR for visitor check-in and a separate one for post-visit feedback.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $arrCards = array_filter([
        $checkinUrl ? [
            'title' => 'Tourist Arrival QR',
            'url' => $checkinUrl,
            'hint' => 'Ask guests to scan this code on arrival to fill out the tourist arrival form themselves.',
            'file' => 'checkin',
        ] : null,
        $feedbackUrl ? [
            'title' => 'Feedback QR',
            'url' => $feedbackUrl,
            'hint' => 'Display this code where guests can scan it after their stay or visit to answer the feedback form.',
            'file' => 'feedback',
        ] : null,
    ]);
@endphp
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="QR Codes"
        description="{{ $establishmentName }} has two separate QR codes: one for tourist arrivals and one for visitor feedback."
    />

    @if ($arrCards)
        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
            @foreach ($arrCards as $arrCard)
                {{-- Rendered server-side as a real, scannable SVG QR code (see
                     simplesoftwareio/simple-qrcode's QrCode facade). Download/print
                     are wired per card in initQrActions() (resources/js/establishment.js). --}}
                <div class="flex flex-col items-center gap-5 rounded-md border border-sand-200 bg-sand-0 p-8 text-center" data-qr-card>
                    <x-dashboard.status-badge tone="success">Active</x-dashboard.status-badge>

                    <p class="font-display text-lg font-bold text-sand-900">{{ $arrCard['title'] }}</p>
                    <p class="-mt-3 text-sm text-sand-600">{{ $establishmentName }}</p>

                    <div
                        class="flex h-56 w-56 items-center justify-center rounded-md border border-sand-200 bg-sand-0 p-2 [&>svg]:h-full [&>svg]:w-full sm:h-64 sm:w-64"
                        aria-label="{{ $arrCard['title'] }} for {{ $establishmentName }}"
                    >
                        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(256)->margin(1)->generate($arrCard['url']) !!}
                    </div>

                    <p class="max-w-xs break-all font-mono text-[11px] text-sand-400">{{ $arrCard['url'] }}</p>

                    <div class="max-w-sm rounded-md bg-primary-100 px-4 py-3 text-sm text-primary-900">
                        <i class="ti ti-info-circle" aria-hidden="true"></i>
                        {{ $arrCard['hint'] }}
                    </div>

                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <button type="button" data-qr-card-download data-qr-filename="{{ str($establishmentName ?? 'itour')->slug() }}-{{ $arrCard['file'] }}-qr-code.svg" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                            <i class="ti ti-download" aria-hidden="true"></i>
                            Download QR Code
                        </button>
                        <button type="button" data-qr-card-print class="inline-flex items-center gap-2 rounded-sm border border-sand-300 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                            <i class="ti ti-printer" aria-hidden="true"></i>
                            Print QR Code
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <style media="print">
            body * { visibility: hidden; }
            .qr-print-target, .qr-print-target * { visibility: visible; }
            .qr-print-target { position: fixed; inset: 0; border: none; }
        </style>
    @else
        <x-dashboard.empty-state
            class="mt-6"
            icon="ti-qrcode"
            title="No directory listing found"
            description="{{ $establishmentName }} isn't linked to a tourism directory listing yet, so QR codes can't be generated. Contact your LGU to get listed."
        />
    @endif
</x-layouts.dashboard>
