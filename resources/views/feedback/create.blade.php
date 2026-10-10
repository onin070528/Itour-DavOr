{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Public tourist feedback form (Objective 4) — choose a published
    destination or establishment, write feedback in any language, consent,
    and pass the shared Turnstile check. No account, no results shown.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $strSelectedSlug = old('listing', $selectedListing?->lst_slug);
    $intMaxLength = (int) config('tourist_feedback.feedback_max_length');
    $intMinLength = (int) config('tourist_feedback.feedback_min_length');
@endphp

<x-layouts.public title="Share Your Experience" description="Tell the Davao Oriental tourism offices about your visit." robots="noindex, nofollow">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

    <div class="mx-auto max-w-xl px-4 py-10 sm:px-6">
        <h1 class="font-display text-2xl font-bold text-sand-900">Share Your Experience</h1>
        <p class="mt-1.5 text-sm text-sand-600">
            @if ($selectedListing)
                Tell us about your visit to <span class="font-semibold text-sand-800">{{ $selectedListing->lst_name }}</span>.
            @else
                Tell us about a destination or establishment you visited in Davao Oriental.
            @endif
            You can write in English, Bisaya, Tagalog, or any language.
        </p>

        @if ($isRequestedListingUnavailable)
            <p class="mt-4 rounded-sm border border-sand-200 bg-sand-0 px-3.5 py-2.5 text-sm text-sand-700" role="status">
                That place isn't accepting feedback right now. Please choose another one from the list.
            </p>
        @endif

        <form method="POST" action="{{ route('feedback.store') }}" data-feedback-form class="mt-6 flex flex-col gap-5 rounded-md border border-sand-200 bg-sand-0 p-4 shadow-sm sm:p-6">
            @csrf

            {{-- Honeypot: hidden from people, filled only by bots (see
                 App\Services\FeedbackSubmissionService). --}}
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="absolute left-[-9999px] h-0 w-0 opacity-0">

            <div>
                <label for="feedback-listing" class="form-label">Destination or establishment <span class="text-danger">*</span></label>
                <select id="feedback-listing" name="listing" required class="form-input">
                    <option value="">Choose where you visited</option>
                    @foreach ($listingGroups as $strGroup => $arrListings)
                        <optgroup label="{{ $strGroup }}">
                            @foreach ($arrListings as $arrListing)
                                <option value="{{ $arrListing['slug'] }}" @selected($strSelectedSlug === $arrListing['slug'])>{{ $arrListing['name'] }}{{ $arrListing['municipality'] !== '' ? ' — '.$arrListing['municipality'] : '' }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('listing')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="feedback-text" class="form-label">Your feedback <span class="text-danger">*</span></label>
                <textarea
                    id="feedback-text"
                    name="feedback"
                    rows="6"
                    required
                    minlength="{{ $intMinLength }}"
                    maxlength="{{ $intMaxLength }}"
                    data-feedback-text
                    class="form-input"
                    placeholder="What did you enjoy? What could be better?"
                >{{ old('feedback') }}</textarea>
                <div class="flex items-start justify-between gap-3">
                    <p class="form-hint">At least {{ $intMinLength }} characters. Please don't include phone numbers, emails, or other personal details.</p>
                    <p class="form-hint shrink-0 tabular-nums" aria-live="polite"><span data-feedback-count>{{ mb_strlen((string) old('feedback')) }}</span>/{{ $intMaxLength }}</p>
                </div>
                @error('feedback')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="feedback-name" class="form-label">Your name <span class="font-normal text-sand-500">(optional)</span></label>
                    <input id="feedback-name" type="text" name="tourist_name" value="{{ old('tourist_name') }}" maxlength="{{ (int) config('tourist_feedback.tourist_name_max_length') }}" autocomplete="given-name" class="form-input">
                    @error('tourist_name')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="feedback-visit-date" class="form-label">Visit date <span class="font-normal text-sand-500">(optional)</span></label>
                    <input id="feedback-visit-date" type="date" name="visit_date" value="{{ old('visit_date') }}" max="{{ $maxVisitDate }}" class="form-input">
                    @error('visit_date')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label class="flex items-start gap-2.5 text-sm text-sand-700">
                    <input type="checkbox" name="consent" value="1" @checked(old('consent')) required class="mt-0.5 h-4 w-4 shrink-0 rounded-sm border-sand-300 text-primary-700">
                    <span>
                        I agree that iTOUR may store this feedback and use it for tourism analysis by the Provincial Tourism Office and the
                        municipal tourism offices of Davao Oriental. Feedback that is not in English may be sent to an
                        <strong class="font-semibold">external translation service</strong> to be translated into English.
                        See the <a href="{{ route('privacy') }}" class="font-semibold text-primary-700 hover:text-primary-900">privacy notice</a>.
                    </span>
                </label>
                @error('consent')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Cloudflare Turnstile — the same widget, keys, and server-side
                 rule (App\Rules\Turnstile) as the login page. --}}
            <div>
                <p class="form-label">Security check</p>
                <div class="flex justify-center rounded-sm border border-sand-300 bg-sand-50 py-3">
                    <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
                </div>
                @error('cf-turnstile-response')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" data-feedback-submit class="btn-primary justify-center disabled:cursor-not-allowed disabled:opacity-60">
                <i class="ti ti-send" aria-hidden="true"></i>
                Submit Feedback
            </button>
        </form>
    </div>
</x-layouts.public>
