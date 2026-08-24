@php
    $total = array_sum($sentiment);
    $positivePct = $total ? round(($sentiment['positive'] / $total) * 100) : 0;
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="Tourist Experience Analytics"
        description="How tourists experience {{ $establishmentName }}, based on their feedback."
    />

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Overall Sentiment</h2>
            <p class="text-xs text-sand-500">{{ number_format($total) }} feedback entries analyzed</p>

            @if ($total)
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
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-success"></span>Positive <b class="ml-auto font-semibold">{{ $sentiment['positive'] }}</b></span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-warning"></span>Neutral <b class="ml-auto font-semibold">{{ $sentiment['neutral'] }}</b></span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-danger"></span>Negative <b class="ml-auto font-semibold">{{ $sentiment['negative'] }}</b></span>
                    </div>
                </div>
            @else
                <p class="mt-4 text-sm text-sand-500">No feedback recorded yet.</p>
            @endif
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
                            data-trend-target="est-sentiment-trend"
                            @class(['rounded-sm px-2.5 py-1.5 transition-colors', 'bg-sand-0 shadow-sm text-primary-700' => $period === 'month', 'text-sand-600' => $period !== 'month'])
                        >{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <div class="mt-4" id="est-sentiment-trend" data-trend-chart="est-sentiment-trend-data">
                <svg class="h-36 w-full" preserveAspectRatio="none"></svg>
                <div class="mt-2 flex justify-between text-[10px] text-sand-500" data-trend-labels></div>
            </div>
            <script type="application/json" id="est-sentiment-trend-data">@json($sentimentTrend)</script>
        </div>
    </div>

    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        <h2 class="font-display text-base font-bold text-sand-900">Feedback Summary</h2>
        @if (count($feedback))
            <div class="mt-4 flex flex-col gap-3">
                @foreach (array_slice($feedback, 0, 5) as $entry)
                    <div class="flex items-start justify-between gap-3 border-b border-sand-100 pb-3 last:border-0">
                        <div>
                            <p class="text-sm font-semibold text-sand-900">{{ $entry['name'] }}</p>
                            <p class="text-xs text-sand-600">&ldquo;{{ \Illuminate\Support\Str::limit($entry['text'], 100) }}&rdquo;</p>
                        </div>
                        <x-dashboard.status-badge :tone="match ($entry['sentiment']) { 'Positive' => 'success', 'Negative' => 'danger', default => 'warning' }">{{ $entry['sentiment'] }}</x-dashboard.status-badge>
                    </div>
                @endforeach
            </div>
        @else
            <p class="mt-3 text-sm text-sand-500">No feedback to summarize yet.</p>
        @endif
    </div>
</x-layouts.dashboard>
