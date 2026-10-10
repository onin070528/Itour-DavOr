{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Public listing detail page.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.public :title="$listing->lst_name">
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
        <a href="{{ route('explore') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:text-primary-900">
            <i class="ti ti-arrow-left" aria-hidden="true"></i>
            Back to Explore
        </a>

        <div class="relative mt-4 h-64 overflow-hidden rounded-md bg-sand-200 sm:h-96">
            @if ($coverImageUrl)
                <img src="{{ $coverImageUrl }}" alt="{{ $listing->lst_name }}" class="absolute inset-0 h-full w-full object-cover">
            @else
                <div class="absolute inset-0 flex items-center justify-center bg-sand-200">
                    <i class="ti {{ $categoryIcon }} text-6xl text-sand-400" aria-hidden="true"></i>
                </div>
            @endif
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-sand-900/60 via-transparent to-transparent"></div>
            <span class="absolute bottom-4 left-5 rounded-sm bg-sand-900/45 px-3 py-1.5 text-xs font-semibold tracking-wide text-sand-0 uppercase">{{ $categoryLabel }}</span>
        </div>

        <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="font-display text-2xl font-bold text-sand-900 sm:text-3xl">{{ $listing->lst_name }}</h1>
                <p class="mt-1 flex items-center gap-1 text-sm font-medium text-sand-500">
                    <i class="ti ti-map-pin" aria-hidden="true"></i>
                    {{ $listing->lst_barangay }}, {{ $listing->lst_municipality }}
                </p>
            </div>
            @if ($listing->lst_rating !== null)
                <span class="inline-flex items-center gap-1 text-sm font-semibold text-sand-800">
                    <i class="ti ti-star text-accent-500" aria-hidden="true"></i>
                    {{ number_format((float) $listing->lst_rating, 1) }}
                </span>
            @endif
        </div>

        @if ($listing->lst_description)
            <p class="mt-4 max-w-3xl text-sm leading-relaxed text-sand-700">{{ $listing->lst_description }}</p>
        @endif

        @if (! empty($listing->lst_tags))
            <div class="mt-4 flex flex-wrap gap-1.5">
                @foreach ($listing->lst_tags as $tag)
                    <span class="rounded-sm bg-sand-100 px-2 py-1 text-xs font-medium text-sand-700">{{ $tag }}</span>
                @endforeach
            </div>
        @endif

        <dl class="mt-6 grid grid-cols-1 gap-4 border-t border-sand-200 pt-5 text-sm sm:grid-cols-2">
            @foreach ([
                'contact_office' => ['icon' => 'ti-building', 'label' => 'Contact office'],
                'contact_phone' => ['icon' => 'ti-phone', 'label' => 'Phone'],
                'hours' => ['icon' => 'ti-clock', 'label' => 'Hours'],
                'email' => ['icon' => 'ti-mail', 'label' => 'Email'],
                'website' => ['icon' => 'ti-world', 'label' => 'Website'],
            ] as $field => $meta)
                @if ($listing->{$field})
                    <div class="flex items-start gap-2.5">
                        <i class="ti {{ $meta['icon'] }} mt-0.5 text-primary-700" aria-hidden="true"></i>
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold tracking-wide text-sand-500 uppercase">{{ $meta['label'] }}</dt>
                            <dd class="break-words text-sand-800">{{ $listing->{$field} }}</dd>
                        </div>
                    </div>
                @endif
            @endforeach
        </dl>

        {{-- Simple gallery of every PUBLISHED photo (7E). Each one shows its
             credit line when set, and falls back to the establishment's
             name for alt text. Hidden entirely when there's nothing beyond
             the cover photo already shown above. --}}
        @if ($galleryImages->isNotEmpty())
            <div class="mt-8 border-t border-sand-200 pt-6">
                <h2 class="font-display text-lg font-bold text-sand-900">Photos</h2>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($galleryImages as $image)
                        <figure class="overflow-hidden rounded-md bg-sand-200">
                            <a href="{{ route('establishmentImages.file', [$image, 'full']) }}" target="_blank" rel="noopener">
                                <img
                                    src="{{ route('establishmentImages.file', [$image, 'thumbnail']) }}"
                                    alt="{{ $image->img_alt_text ?: $listing->lst_name }}"
                                    loading="lazy"
                                    class="h-32 w-full object-cover transition-transform hover:scale-105 sm:h-36"
                                >
                            </a>
                            @if ($image->img_credit)
                                <figcaption class="px-1.5 py-1 text-[11px] text-sand-500">{{ $image->img_credit }}</figcaption>
                            @endif
                        </figure>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Public feedback form. Sentiment is analyzed on submit (App\Services\SentimentAnalyzer) and shown to the LGU/PTO only. --}}
        @if ($listing->isPubliclyVisible())
            <div id="feedback" class="mt-8 border-t border-sand-200 pt-6">
                @if (session('toast'))
                    <p role="status" @class(['mb-4 max-w-xl rounded-md px-4 py-3 text-sm font-medium', 'bg-success-bg text-success' => session('toast_tone', 'success') === 'success', 'bg-danger-bg text-danger' => session('toast_tone', 'success') !== 'success'])>{{ session('toast') }}</p>
                @endif
                <h2 class="font-display text-lg font-bold text-sand-900">Share your experience</h2>
                <p class="mt-1 text-sm text-sand-600">Tell us about your visit to {{ $listing->lst_name }}. Your feedback helps the local tourism office improve.</p>

                <form method="POST" action="{{ route('listings.feedback.store', $listing) }}" class="mt-4 flex max-w-xl flex-col gap-4">
                    @csrf
                    <fieldset>
                        <legend class="mb-1 text-xs font-semibold text-sand-700">Your rating <span class="text-danger" aria-hidden="true">*</span></legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ([1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very good', 5 => 'Excellent'] as $intStars => $strRatingLabel)
                                <label class="flex cursor-pointer items-center gap-1.5 rounded-sm border border-sand-300 px-3 py-2 text-sm text-sand-800 has-[:checked]:border-primary-700 has-[:checked]:bg-primary-50 has-[:checked]:font-semibold">
                                    <input type="radio" name="rating" value="{{ $intStars }}" required @checked((int) old('rating') === $intStars) class="h-4 w-4 text-primary-700 focus:ring-primary-500">
                                    {{ $intStars }} <i class="ti ti-star text-accent-500" aria-hidden="true"></i> {{ $strRatingLabel }}
                                </label>
                            @endforeach
                        </div>
                        @error('rating')
                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div>
                        <label for="feedback-comment" class="mb-1 block text-xs font-semibold text-sand-700">Your comment <span class="text-danger" aria-hidden="true">*</span></label>
                        <textarea id="feedback-comment" name="comment" rows="4" required minlength="5" maxlength="1000" placeholder="What did you like? What could be better? (English, Filipino or Bisaya)" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">{{ old('comment') }}</textarea>
                        @error('comment')
                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="feedback-name" class="mb-1 block text-xs font-semibold text-sand-700">Your name (optional)</label>
                        <input id="feedback-name" name="name" type="text" maxlength="60" value="{{ old('name') }}" placeholder="Leave blank to stay anonymous" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                        @error('name')
                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="self-start rounded-sm bg-primary-700 px-5 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                        Send Feedback
                    </button>
                </form>
            </div>
        @endif
    </div>
</x-layouts.public>
