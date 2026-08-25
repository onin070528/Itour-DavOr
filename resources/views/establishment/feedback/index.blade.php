@php
    $sentimentTone = fn ($s) => match ($s) {
        'Positive' => 'success',
        'Negative' => 'danger',
        default => 'warning',
    };
    $sentiments = collect($feedback)->pluck('sentiment')->unique()->sort()->values();
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="Tourist Feedback"
        description="Feedback tourists left about {{ $establishmentName }}. This is read-only — feedback cannot be edited or removed."
    >
        <x-slot:actions>
            <a href="{{ route('establishment.feedback.analytics') }}" class="inline-flex items-center gap-2 rounded-sm bg-accent-500 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-accent-600">
                <i class="ti ti-heart-handshake" aria-hidden="true"></i>
                Experience Analytics
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div data-filterable-table data-page-size="6" class="mt-6">
        <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 lg:flex-row lg:items-center">
            <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                <input data-filter-input type="search" placeholder="Search feedback..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
            </div>
            <select data-filter-select data-filter-key="sentiment" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                <option value="">All Sentiments</option>
                @foreach ($sentiments as $s)
                    <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>
        </div>

        @if (count($feedback))
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($feedback as $entry)
                    <div
                        data-row
                        data-sentiment="{{ $entry['sentiment'] }}"
                        data-search-text="{{ strtolower($entry['name'].' '.$entry['text']) }}"
                        class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-semibold text-sand-900">{{ $entry['name'] }}</p>
                            <x-dashboard.status-badge :tone="$sentimentTone($entry['sentiment'])">{{ $entry['sentiment'] }}</x-dashboard.status-badge>
                        </div>

                        <p class="text-sm leading-relaxed text-sand-700">&ldquo;{{ $entry['text'] }}&rdquo;</p>

                        <div class="mt-auto flex items-center justify-between border-t border-sand-100 pt-3 text-xs text-sand-500">
                            <span class="flex items-center gap-1"><i class="ti ti-language" aria-hidden="true"></i>{{ $entry['language'] }}</span>
                            <span>Polarity: <b class="{{ $entry['polarity'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($entry['polarity'], 2) }}</b></span>
                            <span>{{ \Illuminate\Support\Carbon::parse($entry['date'])->format('M j, Y') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <x-dashboard.empty-state
            data-empty-state
            class="{{ count($feedback) ? 'hidden' : '' }} mt-4"
            icon="ti-message-2"
            title="No feedback yet"
            description="Tourist feedback about {{ $establishmentName }} will appear here once visitors start leaving reviews."
        />

        <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
    </div>
</x-layouts.dashboard>
