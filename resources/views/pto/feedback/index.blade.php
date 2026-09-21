@php
    $sentimentTone = fn ($s) => match ($s) {
        'Positive' => 'success',
        'Negative' => 'danger',
        default => 'warning',
    };
    $subjects = collect($feedback)->pluck('subject')->unique()->sort()->values();
    $sentiments = collect($feedback)->pluck('sentiment')->unique()->sort()->values();

    $sentimentTotal = array_sum($sentiment);
    $positivePct = round(($sentiment['positive'] / max($sentimentTotal, 1)) * 100);
    $maxByDestination = $byDestination->max() ?: 1;
    $maxByEstablishment = $byEstablishment->max() ?: 1;

    $tabs = [
        'index' => 'All Feedback',
        'analytics' => 'Experience Analytics',
    ];
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Tourist Feedback"
        description="Multilingual tourist feedback with automated sentiment and polarity scoring."
    />

    <div data-tabs class="mt-4 flex border-b border-sand-200">
        @foreach ($tabs as $key => $label)
            <button
                type="button"
                data-tab-target="{{ $key }}"
                aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}"
                @class([
                    'border-b-2 px-4 py-3 text-sm font-semibold transition-colors',
                    'border-primary-700 text-primary-700' => $activeTab === $key,
                    'border-transparent text-sand-500' => $activeTab !== $key,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    {{-- All Feedback tab --}}
    <div data-tab-panel="index" @class(['hidden' => $activeTab !== 'index'])>
        <div data-filterable-table data-page-size="6" class="mt-6">
            <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 lg:flex-row lg:items-center">
                <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                    <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                    <input data-filter-input type="search" placeholder="Search feedback..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
                </div>
                <select data-filter-select data-filter-key="subject" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                    <option value="">All Destinations/Establishments</option>
                    @foreach ($subjects as $s)
                        <option value="{{ $s }}">{{ $s }}</option>
                    @endforeach
                </select>
                <select data-filter-select data-filter-key="sentiment" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                    <option value="">All Sentiments</option>
                    @foreach ($sentiments as $s)
                        <option value="{{ $s }}">{{ $s }}</option>
                    @endforeach
                </select>
                <button type="button" data-filter-reset class="rounded-sm border border-sand-300 px-3 py-2.5 text-sm font-semibold text-sand-700 hover:border-primary-300">
                    Reset
                </button>
            </div>

            <p class="mt-3 text-xs text-sand-500"><span data-result-count>{{ count($feedback) }}</span> of {{ count($feedback) }} entries</p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($feedback as $entry)
                    <div
                        data-row
                        data-subject="{{ $entry['subject'] }}"
                        data-sentiment="{{ $entry['sentiment'] }}"
                        data-search-text="{{ strtolower($entry['name'].' '.$entry['subject'].' '.$entry['text']) }}"
                        class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-sand-900">{{ $entry['name'] }}</p>
                                <p class="flex items-center gap-1 text-xs text-sand-500"><i class="ti ti-map-pin" aria-hidden="true"></i>{{ $entry['subject'] }}</p>
                            </div>
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

            <x-dashboard.empty-state
                data-empty-state
                class="hidden mt-4"
                icon="ti-message-2"
                title="No feedback matches your filters"
                description="Try a different destination, establishment, or sentiment."
            />

            <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
        </div>
    </div>

    {{-- Experience Analytics tab --}}
    <div data-tab-panel="analytics" @class(['hidden' => $activeTab !== 'analytics'])>
        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
                <h2 class="font-display text-base font-bold text-sand-900">Overall Sentiment</h2>
                <p class="text-xs text-sand-500">{{ number_format($sentimentTotal) }} feedback entries analyzed</p>
                <div class="mt-4 flex items-center gap-5">
                    <x-dashboard.donut-chart
                        :segments="[
                            ['label' => 'Positive', 'value' => $sentiment['positive'], 'color' => 'var(--color-success)'],
                            ['label' => 'Neutral', 'value' => $sentiment['neutral'], 'color' => 'var(--color-warning)'],
                            ['label' => 'Negative', 'value' => $sentiment['negative'], 'color' => 'var(--color-danger)'],
                        ]"
                        :center-label="$positivePct.'%'"
                        center-sublabel="Positive"
                    />
                    <div class="flex flex-col gap-2 text-xs">
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-success"></span>Positive <b class="ml-auto font-semibold">{{ number_format($sentiment['positive']) }}</b></span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-warning"></span>Neutral <b class="ml-auto font-semibold">{{ number_format($sentiment['neutral']) }}</b></span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-danger"></span>Negative <b class="ml-auto font-semibold">{{ number_format($sentiment['negative']) }}</b></span>
                    </div>
                </div>
            </div>

            <div class="rounded-md border border-sand-200 bg-sand-0 p-5 lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-base font-bold text-sand-900">Sentiment Trend</h2>
                        <p class="text-xs text-sand-500">Share of positive feedback over time</p>
                    </div>
                    <div class="flex items-center gap-1 rounded-sm border border-sand-300 bg-sand-50 p-1 text-xs font-semibold">
                        @foreach (['week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $period => $label)
                            <button
                                type="button"
                                data-trend-period="{{ $period }}"
                                data-trend-target="sentiment-trend"
                                @class(['rounded-sm px-2.5 py-1.5 transition-colors', 'bg-sand-0 shadow-sm text-primary-700' => $period === 'month', 'text-sand-600' => $period !== 'month'])
                            >{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="mt-4" id="sentiment-trend" data-trend-chart="sentiment-trend-data">
                    <svg class="h-36 w-full" preserveAspectRatio="none"></svg>
                    <div class="mt-2 flex justify-between text-[10px] text-sand-500" data-trend-labels></div>
                </div>
                <script type="application/json" id="sentiment-trend-data">@json($sentimentTrend)</script>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
                <h2 class="font-display text-base font-bold text-sand-900">Feedback by Destination</h2>
                <div class="mt-4 flex flex-col gap-3">
                    @forelse ($byDestination as $name => $count)
                        <div class="flex items-center gap-3">
                            <span class="w-40 shrink-0 truncate text-sm text-sand-700">{{ $name }}</span>
                            <div class="h-2.5 flex-1 rounded-full bg-sand-100">
                                <div class="h-2.5 rounded-full bg-primary-700" style="width: {{ max(4, round(($count / $maxByDestination) * 100)) }}%"></div>
                            </div>
                            <span class="w-6 shrink-0 text-right text-sm font-semibold text-sand-800">{{ $count }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-sand-500">No destination feedback yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
                <h2 class="font-display text-base font-bold text-sand-900">Feedback by Establishment</h2>
                <div class="mt-4 flex flex-col gap-3">
                    @forelse ($byEstablishment as $name => $count)
                        <div class="flex items-center gap-3">
                            <span class="w-40 shrink-0 truncate text-sm text-sand-700">{{ $name }}</span>
                            <div class="h-2.5 flex-1 rounded-full bg-sand-100">
                                <div class="h-2.5 rounded-full bg-secondary-500" style="width: {{ max(4, round(($count / $maxByEstablishment) * 100)) }}%"></div>
                            </div>
                            <span class="w-6 shrink-0 text-right text-sm font-semibold text-sand-800">{{ $count }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-sand-500">No establishment feedback yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.dashboard>
