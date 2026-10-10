{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Paginated tourist feedback entries for authorized personnel
    (Objective 4): the original text, its English translation, processing
    status, sentiment and score (analyzed only), detected issues, and the
    failure reason of failed or rejected rows. All tourist text is escaped.
    Props:
      entries      — FeedbackAnalyticsService::feedbackEntries() paginator
      showListing  — show which destination or establishment it is about
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['entries', 'showListing' => true])

<div {{ $attributes }}>
    @if ($entries->isEmpty())
        <x-dashboard.empty-state
            class="mt-6"
            icon="ti-message-2"
            title="No feedback found"
            description="Try another reporting period or filter."
        />
    @else
        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($entries as $objEntry)
                @php
                    $blnIsAnalyzed = $objEntry->isAnalyzed();
                    $blnHasTranslation = $blnIsAnalyzed && $objEntry->fbk_translated_text !== null && $objEntry->fbk_translated_text !== $objEntry->fbk_original_text;
                @endphp
                <article class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-sand-900">{{ $objEntry->fbk_tourist_name ?: 'Anonymous' }}</p>
                            @if ($showListing && $objEntry->listing)
                                <p class="flex items-center gap-1 text-xs text-sand-500">
                                    <i class="ti {{ $objEntry->listing->isDestinationOnly() ? 'ti-map-pin' : 'ti-building-store' }}" aria-hidden="true"></i>
                                    {{ $objEntry->listing->lst_name }} · {{ $objEntry->listing->categoryName() }}
                                </p>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-wrap justify-end gap-1">
                            @if ($blnIsAnalyzed && $objEntry->fbk_sentiment)
                                <x-dashboard.status-badge :tone="$objEntry->fbk_sentiment->badgeTone()">{{ $objEntry->fbk_sentiment->label() }}</x-dashboard.status-badge>
                            @else
                                <x-dashboard.status-badge :tone="$objEntry->fbk_status->badgeTone()">{{ $objEntry->fbk_status->label() }}</x-dashboard.status-badge>
                            @endif
                        </div>
                    </div>

                    <p class="text-sm leading-relaxed whitespace-pre-line text-sand-700">&ldquo;{{ $objEntry->fbk_original_text }}&rdquo;</p>

                    @if ($blnHasTranslation)
                        <div class="rounded-sm bg-sand-50 px-3 py-2">
                            <p class="text-[11px] font-semibold tracking-wide text-sand-500 uppercase">English translation</p>
                            <p class="mt-0.5 text-sm text-sand-700">{{ $objEntry->fbk_translated_text }}</p>
                        </div>
                    @endif

                    @if ($blnIsAnalyzed && $objEntry->issues->isNotEmpty())
                        <p class="text-xs text-sand-600">
                            <span class="font-semibold">Concerns:</span>
                            {{ $objEntry->issues->pluck('fbi_issue_category')->join(', ') }}
                        </p>
                    @endif

                    @if (! $blnIsAnalyzed && $objEntry->fbk_failure_reason)
                        <p class="text-xs text-sand-600"><span class="font-semibold">Not analyzed:</span> {{ $objEntry->fbk_failure_reason }}</p>
                    @endif

                    <div class="mt-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-t border-sand-100 pt-3 text-xs text-sand-500">
                        @if ($objEntry->fbk_detected_language)
                            <span class="flex items-center gap-1"><i class="ti ti-language" aria-hidden="true"></i>{{ strtoupper($objEntry->fbk_detected_language) }}</span>
                        @endif
                        @if ($blnIsAnalyzed)
                            <span>Score: <b class="{{ (float) $objEntry->fbk_sentiment_score >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format((float) $objEntry->fbk_sentiment_score, 2) }}</b></span>
                        @endif
                        @if ($objEntry->fbk_visit_date)
                            <span>Visited {{ $objEntry->fbk_visit_date->format('M j, Y') }}</span>
                        @endif
                        <span>Submitted {{ $objEntry->fbk_created_at->format('M j, Y') }}</span>
                    </div>
                </article>
            @endforeach
        </div>

        <x-public-pagination :paginator="$entries" />
    @endif
</div>
