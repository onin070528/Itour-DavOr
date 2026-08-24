@php
    $total = array_sum($sentiment);
    $positivePct = round(($sentiment['positive'] / max($total, 1)) * 100);
    $maxByDestination = $byDestination->max() ?: 1;
    $maxByEstablishment = $byEstablishment->max() ?: 1;
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Tourist Experience Analytics"
        description="Sentiment analysis results from multilingual tourist feedback — English, Filipino, and Bisaya."
    />

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Overall Sentiment</h2>
            <p class="text-xs text-sand-500">{{ number_format($total) }} feedback entries analyzed</p>
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
</x-layouts.dashboard>
