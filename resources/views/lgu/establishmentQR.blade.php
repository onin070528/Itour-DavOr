{{--
    Public visitor self-registration form, reached by scanning the QR code
    posted at a municipality's registered establishment (hence living next
    to the rest of the LGU views, but served without auth — the visitor
    filling this in is never logged in). $establishmentName is resolved by
    CheckinController from the {establishment} uuid in the URL — the same
    uuid every establishment's QR code encodes (App\Services\QrCodeService).

    Layout puts the headcount first: visit type, then the group headcount
    (the main section, with a live total), then where the group is from,
    then the lead visitor's details, with a sticky submit bar showing the
    total. Same fields as staff Arrival Recording
    (resources/views/establishment/arrivals/record.blade.php); both are
    validated and saved by App\Services\ArrivalRecorder.

    Counters and the submit are wired up client-side in resources/js/app.js
    (initEstablishmentQrForm), which posts to CheckinController::store and
    then shows the success step rather than reloading the page.
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
    <body class="min-h-screen bg-sand-50 px-4 pt-8 text-sand-900 sm:pt-12">
        <div class="mx-auto flex max-w-md flex-col gap-4">
            {{-- Brand header --}}
            <div class="flex flex-col items-center text-center">
                <x-logo class="text-xl" />
                <p class="mt-0.5 text-[10px] font-semibold tracking-widest text-sand-400 uppercase">Davao Oriental</p>
                <h1 class="mt-4 text-xl">Visitor Registration</h1>
                <p class="mt-2 font-display text-lg font-bold text-primary-700">{{ $establishmentName }}</p>
                <p class="mt-1 text-sm text-sand-600">You're checking in at this establishment today.</p>
            </div>

            @if ($refusalMessage)
                <div class="mb-8 flex flex-col items-center rounded-md border border-sand-200 bg-sand-0 p-8 text-center shadow-sm">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-danger-bg text-danger">
                        <i class="ti ti-ban text-3xl" aria-hidden="true"></i>
                    </span>
                    <p class="mt-4 font-display text-base font-bold text-sand-900">{{ $refusalMessage }}</p>
                    <p class="mt-1 text-sm text-sand-600">Please check with staff at {{ $establishmentName }} for assistance.</p>
                </div>
            @else
            <form id="establishment-qr-form" data-action-url="{{ $checkinAction }}" novalidate>
                {{-- Honeypot: left empty by real visitors, hidden from
                     screen readers and sighted users, but visible to naive
                     bots that fill in every field. CheckinController::store
                     rejects the submission if this is non-empty. --}}
                <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="absolute left-[-9999px] h-0 w-0 opacity-0">

                {{-- Form step --}}
                <div id="qr-form-step" class="flex flex-col gap-4">
                    {{-- 1. Visit type --}}
                    <fieldset class="rounded-md border border-sand-200 bg-sand-0 p-4 shadow-sm">
                        <legend class="sr-only">Visit type</legend>
                        <p class="text-xs font-semibold tracking-wide text-sand-500 uppercase">Visit Type</p>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach ([
                                ['value' => 'Daytour', 'icon' => 'ti-sun', 'label' => 'Day Tour', 'caption' => 'Leaving today'],
                                ['value' => 'Overnight', 'icon' => 'ti-moon', 'label' => 'Overnight', 'caption' => 'Staying the night'],
                            ] as $visitType)
                                <label class="cursor-pointer">
                                    <input type="radio" name="visitType" value="{{ $visitType['value'] }}" class="peer sr-only" @checked($loop->first)>
                                    <span class="flex items-center gap-2.5 rounded-md border-2 border-sand-200 px-3 py-2.5 transition-colors peer-checked:border-primary-700 peer-checked:bg-primary-100/50 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-300">
                                        <i class="ti {{ $visitType['icon'] }} text-xl text-primary-700" aria-hidden="true"></i>
                                        <span class="leading-tight">
                                            <span class="block text-sm font-bold text-sand-900">{{ $visitType['label'] }}</span>
                                            <span class="block text-[11px] text-sand-500">{{ $visitType['caption'] }}</span>
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    {{-- 2. Group headcount — the main section. A Local/Foreign x
                         Male/Female x Age-group matrix (12 counters); the JS
                         rolls it up into the 7 flat totals the backend saves. --}}
                    <div class="rounded-md border-2 border-primary-700 bg-sand-0 p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="font-display text-base font-bold text-sand-900">How many are in your group?</h2>
                                <p class="mt-0.5 text-xs text-sand-500">Count everyone, <b>including yourself</b>.</p>
                            </div>
                            <p class="shrink-0 rounded-md bg-primary-100 px-3 py-1.5 text-center leading-none">
                                <span id="qr-total-value" class="block font-display text-2xl font-extrabold text-primary-900">0</span>
                                <span class="text-[10px] font-semibold tracking-wide text-primary-700 uppercase">People</span>
                            </p>
                        </div>

                        @foreach ([
                            ['key' => 'local', 'icon' => 'ti-home', 'label' => 'Local / Domestic', 'caption' => 'Living in the Philippines'],
                            ['key' => 'foreign', 'icon' => 'ti-world', 'label' => 'Foreign', 'caption' => 'Living outside the Philippines'],
                        ] as $group)
                            <div class="mt-4">
                                <div class="flex items-center justify-between">
                                    <p class="flex items-center gap-1.5 text-sm font-bold text-sand-900">
                                        <i class="ti {{ $group['icon'] }} text-base text-primary-700" aria-hidden="true"></i>
                                        {{ $group['label'] }}
                                        <span class="text-[11px] font-normal text-sand-500">· {{ $group['caption'] }}</span>
                                    </p>
                                    <span class="rounded-sm bg-sand-100 px-2 py-0.5 text-xs font-bold text-sand-700"><span id="qr-{{ $group['key'] }}-value">0</span></span>
                                </div>

                                <div class="mt-2 overflow-hidden rounded-sm border border-sand-200">
                                    <div class="grid grid-cols-[1fr_auto_auto] bg-sand-100 text-[10px] font-semibold tracking-wide text-sand-500 uppercase">
                                        <span class="px-3 py-1.5">Age Group</span>
                                        <span class="w-[6.5rem] border-l border-sand-200 py-1.5 text-center">Male</span>
                                        <span class="w-[6.5rem] border-l border-sand-200 py-1.5 text-center">Female</span>
                                    </div>

                                    @foreach ([
                                        ['key' => 'adults', 'label' => 'Adults', 'caption' => '18 to 59'],
                                        ['key' => 'children', 'label' => 'Kids', 'caption' => 'Under 18'],
                                        ['key' => 'seniors', 'label' => 'Seniors', 'caption' => '60 & above'],
                                    ] as $row)
                                        <div class="grid grid-cols-[1fr_auto_auto] items-center border-t border-sand-200">
                                            <div class="px-3 py-2 leading-tight">
                                                <p class="text-xs font-semibold text-sand-900">{{ $row['label'] }}</p>
                                                <p class="text-[10px] text-sand-500">{{ $row['caption'] }}</p>
                                            </div>
                                            <div class="flex w-[6.5rem] justify-center border-l border-sand-200 py-2">
                                                <x-lgu.qr-counter
                                                    compact
                                                    name="{{ $group['key'] }}-male-{{ $row['key'] }}"
                                                    label="{{ $row['label'] }} ({{ $row['caption'] }}) · Male · {{ $group['label'] }}"
                                                />
                                            </div>
                                            <div class="flex w-[6.5rem] justify-center border-l border-sand-200 py-2">
                                                <x-lgu.qr-counter
                                                    compact
                                                    name="{{ $group['key'] }}-female-{{ $row['key'] }}"
                                                    label="{{ $row['label'] }} ({{ $row['caption'] }}) · Female · {{ $group['label'] }}"
                                                />
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- 3. Where the group is from — appears once the group has someone in it --}}
                    <div id="qr-origin-card" class="hidden rounded-md border border-sand-200 bg-sand-0 p-4 shadow-sm">
                        <h2 class="font-display text-sm font-bold text-sand-900">Where is your group from? <span class="font-normal text-sand-500">(optional)</span></h2>

                        <div id="qr-local-origin-wrap" class="mt-3 hidden">
                            <p class="mb-1.5 text-xs font-semibold text-sand-700">Local / Domestic guests</p>
                            <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Where local guests are from">
                                @foreach ([
                                    ['value' => '', 'label' => 'Prefer not to say'],
                                    ['value' => 'within_province', 'label' => 'Within Davao Oriental'],
                                    ['value' => 'outside_province', 'label' => 'Outside Davao Oriental'],
                                ] as $scope)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="localOriginScope" value="{{ $scope['value'] }}" class="peer sr-only" @checked($loop->first)>
                                        <span class="inline-block rounded-full border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-700 transition-colors peer-checked:border-primary-700 peer-checked:bg-primary-700 peer-checked:text-sand-0 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-300">{{ $scope['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <div id="qr-local-origin-municipality-wrap" class="mt-3 hidden">
                                <label for="qr-local-origin-municipality" class="mb-1 block text-xs font-semibold text-sand-700">Municipality / City</label>
                                <select id="qr-local-origin-municipality" class="w-full rounded-sm border border-sand-300 bg-sand-0 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                                    <option value="">Select a municipality or city</option>
                                    @foreach ($municipalities as $municipality)
                                        <option value="{{ $municipality }}">{{ $municipality }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div id="qr-local-origin-place-wrap" class="mt-3 hidden">
                                <label for="qr-local-origin-place" class="mb-1 block text-xs font-semibold text-sand-700">Home Province</label>
                                <input id="qr-local-origin-place" type="text" list="province-options" placeholder="e.g. Davao del Sur" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                            </div>
                        </div>

                        <div id="qr-foreign-country-wrap" class="mt-4 hidden">
                            <label for="qr-foreign-country" class="mb-1 block text-xs font-semibold text-sand-700">Foreign guests · Home Country</label>
                            <input id="qr-foreign-country" type="text" list="country-options" placeholder="e.g. Japan" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                        </div>

                        <datalist id="province-options">
                            @foreach ($provinces as $province)
                                <option value="{{ $province }}"></option>
                            @endforeach
                        </datalist>
                        <datalist id="country-options">
                            @foreach ($countries as $country)
                                <option value="{{ $country }}"></option>
                            @endforeach
                        </datalist>
                    </div>

                    {{-- 4. Lead visitor --}}
                    <div class="rounded-md border border-sand-200 bg-sand-0 p-4 shadow-sm">
                        <h2 class="font-display text-sm font-bold text-sand-900">Your Details</h2>
                        <p class="text-xs text-sand-500">The person filling in this form for the group.</p>

                        <div class="mt-3 flex flex-col gap-3">
                            <div>
                                <label for="qr-visitor-name" class="mb-1 block text-xs font-semibold text-sand-700">Full Name <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="qr-visitor-name" name="visitorName" type="text" required maxlength="255" autocomplete="name" placeholder="e.g. Juan Dela Cruz" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                            </div>
                            <div>
                                <label for="qr-visitor-contact" class="mb-1 block text-xs font-semibold text-sand-700">Contact Number <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="qr-visitor-contact" name="visitorContact" type="tel" required maxlength="255" autocomplete="tel" placeholder="e.g. 0912 345 6789" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-sand-500">
                        Your information is used for tourism statistics of the Provincial Tourism Office of Davao Oriental.
                        See our <a href="{{ route('privacy') }}" target="_blank" rel="noopener" class="font-semibold text-primary-700 hover:text-primary-900">Privacy Notice</a>.
                    </p>

                    {{-- Sticky submit bar: the total stays in view while scrolling --}}
                    <div class="sticky bottom-0 -mx-4 border-t border-sand-200 bg-sand-50/95 px-4 pt-3 pb-4 backdrop-blur">
                        <p id="qr-form-error" role="alert" class="mb-2 hidden rounded-sm bg-danger-bg px-3 py-2 text-sm text-danger"></p>
                        <button type="submit" id="qr-submit" class="flex w-full items-center justify-center gap-2 rounded-md bg-primary-700 px-5 py-3.5 text-sm font-semibold text-sand-0 shadow-md transition-colors hover:bg-primary-900 disabled:cursor-not-allowed disabled:opacity-60">
                            <i class="ti ti-clipboard-check text-lg" aria-hidden="true" data-submit-icon></i>
                            <span data-submit-label>Submit Registration</span>
                            <span class="rounded-sm bg-white/15 px-2 py-0.5 text-xs"><span id="qr-submit-total">0</span> people</span>
                        </button>
                    </div>
                </div>

                {{-- Success step --}}
                <div id="qr-success-step" class="mb-8 hidden flex-col items-center rounded-md border border-sand-200 bg-sand-0 p-8 text-center shadow-sm">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-success-bg text-success">
                        <i class="ti ti-circle-check text-3xl" aria-hidden="true"></i>
                    </span>
                    <p class="mt-4 font-display text-base font-bold text-sand-900">Registration Submitted</p>
                    <p class="mt-1 text-sm text-sand-600">Thanks! Your visit to {{ $establishmentName }} has been logged.</p>
                    <p id="qr-success-summary" class="mt-3 rounded-sm bg-sand-50 px-3 py-1.5 text-sm font-semibold text-sand-800"></p>

                    <button type="button" id="qr-form-reset" class="mt-6 rounded-sm border border-sand-300 px-5 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                        Register Another Group
                    </button>
                </div>
            </form>
            @endif
        </div>
    </body>
</html>
