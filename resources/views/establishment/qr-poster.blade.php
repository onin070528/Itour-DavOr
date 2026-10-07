{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : Printable check-in QR poster (A4) or table card (A6, cut lines) for one establishment.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.

    Served by QrCodeController::poster() to the same roles as the QR
    download (ListingPolicy::viewQr()). $qrSvg is the branded QR from
    App\Services\QrCodeService, so the poster always shows the exact code
    every other screen shows. Printed or saved as PDF from the browser —
    the toolbar is screen-only.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $establishmentName }} · Check-in QR {{ $layout === 'card' ? 'Table Card' : 'Poster' }} · iTOUR</title>

        @fonts
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.46.0/tabler-icons.min.css">

        @vite(['resources/css/app.css'])

        <style>
            /* A4 paper, no browser margins (the sheet has its own padding). */
            @page {
                size: A4;
                margin: 0;
            }

            .poster-sheet {
                width: 210mm;
                height: 297mm;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .poster-qr > svg,
            .card-qr > svg {
                display: block;
                width: 100%;
                height: 100%;
            }

            /* QR printed size: 120 mm on the poster, 70 mm on the card — both far above the ~3 cm minimum. */
            .poster-qr {
                width: 120mm;
                height: 120mm;
            }

            .card-qr {
                width: 70mm;
                height: 70mm;
            }

            .table-card {
                width: 105mm;
                height: 148mm;
            }

            @media print {
                html,
                body {
                    background: #fff !important;
                }

                .screen-only {
                    display: none !important;
                }

                .poster-sheet {
                    margin: 0 !important;
                    box-shadow: none !important;
                }
            }
        </style>
    </head>
    <body class="bg-sand-100 text-sand-900">
        {{-- Toolbar (screen only) --}}
        <div class="screen-only sticky top-0 z-10 flex flex-wrap items-center justify-center gap-2 border-b border-sand-200 bg-sand-0 px-4 py-3">
            <a href="{{ $posterUrl }}" @class([
                'rounded-sm px-3 py-2 text-sm font-semibold',
                'bg-primary-100 text-primary-900' => $layout === 'a4',
                'text-sand-700 hover:bg-sand-50' => $layout !== 'a4',
            ])>A4 Poster</a>
            <a href="{{ $cardUrl }}" @class([
                'rounded-sm px-3 py-2 text-sm font-semibold',
                'bg-primary-100 text-primary-900' => $layout === 'card',
                'text-sand-700 hover:bg-sand-50' => $layout !== 'card',
            ])>Table Card</a>
            <span class="mx-1 h-5 w-px bg-sand-200" aria-hidden="true"></span>
            <a href="{{ $downloadUrl }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 px-3 py-2 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-download" aria-hidden="true"></i> Download QR (SVG)
            </a>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                <i class="ti ti-printer" aria-hidden="true"></i> Print
            </button>
            <p class="w-full text-center text-xs text-sand-500">Tip: in the print dialog, turn off "Headers and footers" and keep the scale at 100%.</p>
        </div>

        <div class="poster-sheet mx-auto my-6 overflow-hidden bg-sand-0 shadow-md">
            @if ($layout === 'card')
                {{-- Table card: one A6 card with dashed cut lines, top-centered on the A4 sheet --}}
                <div class="flex justify-center pt-[12mm]">
                    <div class="table-card flex flex-col items-center border border-dashed border-sand-400 px-[8mm] py-[7mm] text-center">
                        <img src="{{ asset('storage/itour-images/itour.jpg') }}" alt="iTOUR" class="h-[11mm] w-auto">
                        <p class="mt-[3mm] font-display text-[15pt] leading-tight font-extrabold text-primary-900">Scan to register your visit</p>
                        <div class="card-qr mt-[4mm]" role="img" aria-label="Check-in QR code for {{ $establishmentName }}">{!! $qrSvg !!}</div>
                        <p class="mt-[4mm] font-display text-[13pt] leading-tight font-bold text-sand-900">{{ $establishmentName }}</p>
                        <p class="mt-[1mm] text-[9pt] text-sand-600">{{ $municipalityName }}, Davao Oriental</p>
                        <p class="mt-auto pt-[2mm] font-mono text-[6.5pt] break-all text-sand-500">{{ $checkinUrlLabel }}</p>
                    </div>
                </div>
                <p class="mt-[3mm] text-center text-[8pt] text-sand-400"><i class="ti ti-scissors" aria-hidden="true"></i> Cut along the dashed line</p>
            @else
                {{-- A4 poster --}}
                <div class="flex h-full flex-col items-center px-[18mm] pt-[16mm] pb-[12mm] text-center">
                    <img src="{{ asset('storage/itour-images/itour.jpg') }}" alt="iTOUR" class="h-[22mm] w-auto">
                    <p class="mt-[2mm] text-[9pt] font-semibold tracking-[0.3em] text-sand-500 uppercase">Davao Oriental Tourism</p>

                    <p class="mt-[9mm] font-display text-[32pt] leading-tight font-extrabold text-primary-900">Scan to register your visit</p>
                    <p class="mt-[2mm] text-[12pt] text-sand-600">Open your phone camera, point it at the code, and fill in the short arrival form.</p>

                    <div class="mt-[8mm] rounded-[6mm] border-[1.2mm] border-primary-700 p-[5mm]">
                        <div class="poster-qr" role="img" aria-label="Check-in QR code for {{ $establishmentName }}">{!! $qrSvg !!}</div>
                    </div>

                    <p class="mt-[9mm] font-display text-[24pt] leading-tight font-bold text-sand-900">{{ $establishmentName }}</p>
                    <p class="mt-[1.5mm] text-[13pt] text-sand-600">{{ $municipalityName }}, Davao Oriental</p>

                    <div class="mt-auto w-full border-t-[0.8mm] border-accent-500 pt-[4mm]">
                        <p class="text-[9pt] text-sand-500">Can't scan? Type this address in your browser:</p>
                        <p class="mt-[1mm] font-mono text-[9pt] break-all text-sand-700">{{ $checkinUrlLabel }}</p>
                        <p class="mt-[3mm] text-[8pt] text-sand-400">Provincial Tourism Office of Davao Oriental · iTOUR</p>
                    </div>
                </div>
            @endif
        </div>
    </body>
</html>
