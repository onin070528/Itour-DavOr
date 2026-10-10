{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Public formal feedback form, reached by scanning a destination's or establishment's own feedback QR code.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $arrAspects = ['service' => 'Staff & service', 'cleanliness' => 'Cleanliness', 'value' => 'Value for money', 'facilities' => 'Facilities & accessibility', 'safety' => 'Safety & security'];
    $arrRatingLabels = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very good', 5 => 'Excellent'];
    $strInput = 'w-full rounded-sm border border-sand-300 bg-sand-0 px-3 py-2 text-sm';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Visitor Feedback Form · iTOUR Davao Oriental</title>

        @fonts
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.46.0/tabler-icons.min.css">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-sand-50 px-4 py-8 text-sand-900 sm:py-12">
        <div class="mx-auto flex max-w-xl flex-col gap-4">
            <div class="flex flex-col items-center text-center">
                <x-logo class="text-xl" />
                <p class="mt-0.5 text-[10px] font-semibold tracking-widest text-sand-400 uppercase">Davao Oriental Provincial Tourism Office</p>
                <h1 class="mt-4 text-xl">Visitor Feedback Form</h1>
                <p class="mt-2 font-display text-lg font-bold text-primary-700">{{ $listing->lst_name }}</p>
                <p class="mt-1 text-sm text-sand-600">{{ $listing->lst_municipality }}</p>
            </div>

            @if (! $isOpen)
                <div class="flex flex-col items-center rounded-md border border-sand-200 bg-sand-0 p-8 text-center shadow-sm">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-danger-bg text-danger">
                        <i class="ti ti-ban text-3xl" aria-hidden="true"></i>
                    </span>
                    <p class="mt-4 font-display text-base font-bold text-sand-900">This place is not accepting feedback right now.</p>
                </div>
            @elseif (session('feedback_sent'))
                <div class="flex flex-col items-center rounded-md border border-sand-200 bg-sand-0 p-8 text-center shadow-sm">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-success-bg text-success">
                        <i class="ti ti-circle-check text-3xl" aria-hidden="true"></i>
                    </span>
                    <p class="mt-4 font-display text-base font-bold text-sand-900">Thank you for your feedback!</p>
                    <p class="mt-1 text-sm text-sand-600">Your response about {{ $listing->lst_name }} was sent to the local tourism office.</p>
                </div>
            @else
                @if (session('toast'))
                    <p role="status" class="rounded-md bg-danger-bg px-4 py-3 text-sm font-medium text-danger">{{ session('toast') }}</p>
                @endif

                <form method="POST" action="{{ route('feedback.submit', $listing->lst_uuid) }}" class="flex flex-col gap-5">
                    @csrf

                    <section class="rounded-md border border-sand-200 bg-sand-0 p-5 shadow-sm">
                        <h2 class="font-display text-base font-bold text-sand-900">Part 1 · Visit Details</h2>
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="visit_date" class="mb-1 block text-xs font-semibold text-sand-700">Date of visit</label>
                                <input id="visit_date" name="visit_date" type="date" max="{{ now()->toDateString() }}" value="{{ old('visit_date') }}" class="{{ $strInput }}">
                                @error('visit_date')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="visit_purpose" class="mb-1 block text-xs font-semibold text-sand-700">Purpose of visit</label>
                                <select id="visit_purpose" name="visit_purpose" class="{{ $strInput }}">
                                    <option value="">Select…</option>
                                    @foreach (['Leisure', 'Business', 'Family / Friends', 'Event', 'Other'] as $strPurpose)
                                        <option value="{{ $strPurpose }}" @selected(old('visit_purpose') === $strPurpose)>{{ $strPurpose }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <fieldset class="sm:col-span-2">
                                <legend class="mb-1 text-xs font-semibold text-sand-700">I am a</legend>
                                <div class="flex flex-wrap gap-2">
                                    @foreach (['Local' => 'Local / Filipino visitor', 'Foreign' => 'Foreign visitor'] as $strValue => $strLabel)
                                        <label class="flex cursor-pointer items-center gap-2 rounded-sm border border-sand-300 px-3 py-2 text-sm has-[:checked]:border-primary-700 has-[:checked]:bg-primary-50 has-[:checked]:font-semibold">
                                            <input type="radio" name="visitor_origin" value="{{ $strValue }}" @checked(old('visitor_origin') === $strValue) class="h-4 w-4 text-primary-700">
                                            {{ $strLabel }}
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        </div>
                    </section>

                    <section class="rounded-md border border-sand-200 bg-sand-0 p-5 shadow-sm">
                        <h2 class="font-display text-base font-bold text-sand-900">Part 2 · Ratings</h2>
                        <fieldset class="mt-4">
                            <legend class="mb-1 text-xs font-semibold text-sand-700">Overall experience <span class="text-danger" aria-hidden="true">*</span></legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($arrRatingLabels as $intStars => $strLabel)
                                    <label class="flex cursor-pointer items-center gap-1.5 rounded-sm border border-sand-300 px-3 py-2 text-sm has-[:checked]:border-primary-700 has-[:checked]:bg-primary-50 has-[:checked]:font-semibold">
                                        <input type="radio" name="rating" value="{{ $intStars }}" required @checked((int) old('rating') === $intStars) class="h-4 w-4 text-primary-700">
                                        {{ $intStars }} <i class="ti ti-star text-accent-500" aria-hidden="true"></i> {{ $strLabel }}
                                    </label>
                                @endforeach
                            </div>
                            @error('rating')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </fieldset>

                        <p class="mt-5 text-xs font-semibold text-sand-700">Rate each aspect (1 = Poor, 5 = Excellent; skip any that do not apply)</p>
                        <div class="mt-2 divide-y divide-sand-100">
                            @foreach ($arrAspects as $strKey => $strLabel)
                                <div class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                                    <span class="text-sm text-sand-800">{{ $strLabel }}</span>
                                    <div class="flex gap-1">
                                        @foreach (range(1, 5) as $intScore)
                                            <label class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-sm border border-sand-300 text-sm has-[:checked]:border-primary-700 has-[:checked]:bg-primary-700 has-[:checked]:text-sand-0">
                                                <input type="radio" name="aspects[{{ $strKey }}]" value="{{ $intScore }}" @checked((int) old("aspects.$strKey") === $intScore) class="sr-only">
                                                {{ $intScore }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <fieldset class="mt-4">
                            <legend class="mb-1 text-xs font-semibold text-sand-700">Would you recommend this place to others?</legend>
                            <div class="flex gap-2">
                                @foreach (['1' => 'Yes', '0' => 'No'] as $strValue => $strLabel)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-sm border border-sand-300 px-4 py-2 text-sm has-[:checked]:border-primary-700 has-[:checked]:bg-primary-50 has-[:checked]:font-semibold">
                                        <input type="radio" name="would_recommend" value="{{ $strValue }}" @checked(old('would_recommend') === $strValue) class="h-4 w-4 text-primary-700">
                                        {{ $strLabel }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </section>

                    <section class="rounded-md border border-sand-200 bg-sand-0 p-5 shadow-sm">
                        <h2 class="font-display text-base font-bold text-sand-900">Part 3 · Comments</h2>
                        <label for="comment" class="mt-4 mb-1 block text-xs font-semibold text-sand-700">Tell us about your experience <span class="text-danger" aria-hidden="true">*</span></label>
                        <textarea id="comment" name="comment" rows="5" required minlength="5" maxlength="1000" placeholder="What did you like? What could be better? (English, Filipino or Bisaya)" class="{{ $strInput }}">{{ old('comment') }}</textarea>
                        @error('comment')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </section>

                    <section class="rounded-md border border-sand-200 bg-sand-0 p-5 shadow-sm">
                        <h2 class="font-display text-base font-bold text-sand-900">Part 4 · Contact (optional)</h2>
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="name" class="mb-1 block text-xs font-semibold text-sand-700">Name</label>
                                <input id="name" name="name" type="text" maxlength="60" value="{{ old('name') }}" placeholder="Leave blank to stay anonymous" class="{{ $strInput }}">
                                @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="email" class="mb-1 block text-xs font-semibold text-sand-700">Email</label>
                                <input id="email" name="email" type="email" maxlength="120" value="{{ old('email') }}" class="{{ $strInput }}">
                                @error('email')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-sand-500">Your answers are used only by the local tourism office and the Provincial Tourism Office to improve tourism services.</p>
                    </section>

                    <button type="submit" class="flex items-center justify-center gap-2 rounded-md bg-primary-700 px-5 py-3.5 text-sm font-semibold text-sand-0 shadow-md hover:bg-primary-900">
                        <i class="ti ti-send text-lg" aria-hidden="true"></i>
                        Submit Feedback
                    </button>
                </form>
            @endif
        </div>
    </body>
</html>
