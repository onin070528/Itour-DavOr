{{--
    Public visitor self-registration form, reached by scanning the QR code
    posted at a municipality's registered establishment (hence living next
    to the rest of the LGU views, but served without auth — the visitor
    filling this in is never logged in). $establishmentName is resolved by
    CheckinController from the {establishment} id slug in the URL, which is
    the same id every establishment's QR code encodes — one unique,
    scannable link/code per establishment.

    Counters are wired up client-side in resources/js/app.js (initEstablishmentQrForm).
    There is no backend/database yet — submitting shows a local success
    step, matching the front-end-only arrival wizard under
    resources/views/establishment/arrivals/record.blade.php.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Visitor Registration · iTOUR Davao Oriental</title>

        @fonts
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.46.0/tabler-icons.min.css">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-sand-50 px-4 py-8 text-sand-900 sm:py-12">
        <div class="mx-auto flex max-w-md flex-col gap-4">
            {{-- Brand header --}}
            <div class="flex flex-col items-center text-center">
                <x-logo class="text-xl" />
                <p class="mt-0.5 text-[10px] font-semibold tracking-widest text-sand-400 uppercase">Davao Oriental</p>
                <h1 class="mt-4 text-xl">Visitor Registration</h1>
                <p class="mt-2 font-display text-lg font-bold text-primary-700">{{ $establishmentName }}</p>
                <p class="mt-1 text-sm text-sand-600">You're checking in at this establishment.</p>
            </div>

            <form id="establishment-qr-form" data-action-url="{{ $checkinAction }}" novalidate>
                {{-- Form step --}}
                <div id="qr-form-step" class="flex flex-col gap-4">
                    {{-- Your Details --}}
                    <div class="rounded-md border border-sand-200 bg-sand-0 p-5 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-700 text-sand-0">
                                <i class="ti ti-user text-lg" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h2 class="font-display text-sm font-bold text-sand-900">Your Details</h2>
                                <p class="text-xs text-sand-500">Tell us a bit about yourself.</p>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-col gap-3">
                            <div>
                                <label for="qr-visitor-name" class="mb-1 block text-xs font-semibold text-sand-700">Full Name</label>
                                <input id="qr-visitor-name" name="visitorName" type="text" required placeholder="e.g. Juan Dela Cruz" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                            </div>
                            <div>
                                <label for="qr-visitor-contact" class="mb-1 block text-xs font-semibold text-sand-700">Contact Number</label>
                                <input id="qr-visitor-contact" name="visitorContact" type="tel" required placeholder="e.g. 0912 345 6789" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                            </div>
                        </div>
                    </div>

                    {{-- Companion Details --}}
                    <div class="rounded-md border border-sand-200 bg-sand-0 p-5 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-700 text-sand-0">
                                <i class="ti ti-users text-lg" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h2 class="font-display text-sm font-bold text-sand-900">Companion Details</h2>
                                <p class="text-xs text-sand-500">How many companions are with you? (excluding yourself)</p>
                            </div>
                        </div>

                        <div class="mt-5 border-t border-sand-200 pt-4">
                            <p class="text-xs font-semibold tracking-wide text-sand-500 uppercase">By Gender</p>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <x-lgu.qr-counter name="male" label="Male" />
                                <x-lgu.qr-counter name="female" label="Female" />
                            </div>
                        </div>

                        <div class="mt-5 border-t border-dashed border-sand-200 pt-4">
                            <p class="text-xs font-semibold tracking-wide text-sand-500 uppercase">By Age Group</p>
                            <div class="mt-3 grid grid-cols-3 gap-3">
                                <x-lgu.qr-counter name="adults" label="Adults" caption="18-59" />
                                <x-lgu.qr-counter name="children" label="Children" caption="Below 18" />
                                <x-lgu.qr-counter name="seniors" label="Seniors" caption="60+" />
                            </div>
                        </div>

                        <div class="mt-5 border-t border-dashed border-sand-200 pt-4">
                            <p class="text-xs font-semibold tracking-wide text-sand-500 uppercase">By Tourist Type</p>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <x-lgu.qr-counter name="local" label="Local" />
                                <x-lgu.qr-counter name="foreign" label="Foreign" />
                            </div>
                        </div>
                    </div>

                    {{-- Total Party Size --}}
                    <div class="flex items-center justify-between rounded-md border-2 border-primary-700 bg-primary-100/40 px-5 py-4">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-primary-700 uppercase">Total Party Size</p>
                            <p id="qr-total-caption" class="text-sm text-sand-700">You + <b>0</b> companions</p>
                        </div>
                        <p class="font-display text-3xl font-extrabold text-primary-900">
                            <span id="qr-total-value">1</span> <span class="text-sm font-semibold text-sand-500">person</span>
                        </p>
                    </div>

                    <button type="submit" class="flex items-center justify-center gap-2 rounded-md bg-primary-700 px-5 py-3.5 text-sm font-semibold text-sand-0 shadow-md transition-colors hover:bg-primary-900">
                        <i class="ti ti-clipboard-check text-lg" aria-hidden="true"></i>
                        Submit Registration
                    </button>
                </div>

                {{-- Success step --}}
                <div id="qr-success-step" class="hidden flex-col items-center rounded-md border border-sand-200 bg-sand-0 p-8 text-center shadow-sm">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-success-bg text-success">
                        <i class="ti ti-circle-check text-3xl" aria-hidden="true"></i>
                    </span>
                    <p class="mt-4 font-display text-base font-bold text-sand-900">Registration Submitted</p>
                    <p class="mt-1 text-sm text-sand-600">Thanks! Your visit to {{ $establishmentName }} has been logged.</p>

                    <button type="button" id="qr-form-reset" class="mt-6 rounded-sm border border-sand-300 px-5 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                        Register Another Group
                    </button>
                </div>
            </form>
        </div>
    </body>
</html>
