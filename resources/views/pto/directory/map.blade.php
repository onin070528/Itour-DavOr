@php
    $positions = collect($municipalities)->keyBy('name');
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Tourism Map"
        description="Destinations and establishments across Davao Oriental."
    />

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-[260px_1fr]">
        <div class="rounded-md border border-sand-200 bg-sand-0 p-4">
            <p class="text-xs font-bold tracking-widest text-sand-500 uppercase">Legend</p>
            <ul class="mt-3 flex flex-col gap-2.5 text-sm text-sand-700">
                <li class="flex items-center gap-2"><i class="ti ti-map-pin text-primary-700" aria-hidden="true"></i>Tourist Destinations</li>
                <li class="flex items-center gap-2"><i class="ti ti-map-pin text-accent-600" aria-hidden="true"></i>Tourism Establishments</li>
            </ul>

            <p class="mt-5 text-xs font-bold tracking-widest text-sand-500 uppercase">Listings Plotted</p>
            <p class="mt-1 font-display text-2xl font-extrabold text-sand-900">{{ count($listings) }}</p>
            <p class="text-xs text-sand-500">Across {{ collect($listings)->pluck('municipality')->unique()->count() }} municipalities</p>

            <div class="mt-5 rounded-md border border-dashed border-sand-300 p-3 text-xs text-sand-600">
                <i class="ti ti-info-circle text-primary-700" aria-hidden="true"></i>
                Interactive map view is coming soon. Pins below are positioned by municipality only, not exact location.
            </div>
        </div>

        <div class="relative h-[560px] overflow-hidden rounded-md border border-sand-200 bg-gradient-to-b from-primary-100 to-sand-100">
            @foreach ($municipalities as $m)
                <div class="absolute flex -translate-x-1/2 -translate-y-1/2 items-center gap-1 text-[10px] font-medium text-sand-500" style="top:{{ $m['top'] }}%; left:{{ $m['left'] }}%;">
                    <span class="h-1.5 w-1.5 rounded-full bg-sand-400"></span>{{ $m['name'] }}
                </div>
            @endforeach

            @php $seen = []; @endphp
            @foreach ($listings as $listing)
                @php
                    $pos = $positions->get($listing['municipality']);
                    if (! $pos) continue;
                    $n = $seen[$listing['municipality']] ?? 0;
                    $seen[$listing['municipality']] = $n + 1;
                    $top = $pos['top'] + ($n % 3) * 2.2 - 2.2;
                    $left = $pos['left'] + intdiv($n, 3) * 2.5;
                    $color = $listing['category'] === 'destinations' ? 'text-primary-700' : 'text-accent-600';
                @endphp
                <div class="absolute -translate-x-1/2 -translate-y-full {{ $color }} drop-shadow" style="top:{{ $top }}%; left:{{ $left }}%;" title="{{ $listing['name'] }}">
                    <i class="ti ti-map-pin text-2xl" aria-hidden="true"></i>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.dashboard>
