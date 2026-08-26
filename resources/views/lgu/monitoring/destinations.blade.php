@php
    $trendIcon = fn ($trend) => match ($trend) {
        'up' => ['ti-trending-up', 'text-success'],
        'down' => ['ti-trending-down', 'text-danger'],
        default => ['ti-minus', 'text-sand-500'],
    };
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Destination Performance"
        description="Destinations in {{ $municipality }}, ranked by tourist visitation."
    />

    @if (count($performance))
        <div data-filterable-table data-page-size="10" class="mt-6">
            <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 sm:flex-row sm:items-center">
                <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 sm:max-w-sm">
                    <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                    <input data-filter-input type="search" placeholder="Search destinations..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
                </div>
            </div>

            <p class="mt-3 text-xs text-sand-500"><span data-result-count>{{ count($performance) }}</span> of {{ count($performance) }} destinations</p>

            <div class="mt-3 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
                <table class="w-full min-w-[480px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Destination</th>
                            <th class="px-4 py-3 text-right">Visits</th>
                            <th class="px-4 py-3 text-right">Trend</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @foreach ($performance as $row)
                            @php [$icon, $color] = $trendIcon($row['trend']); @endphp
                            <tr data-row data-search-text="{{ strtolower($row['destination']) }}" class="hover:bg-sand-50">
                                <td class="px-4 py-3 text-sand-500">{{ $row['rank'] }}</td>
                                <td class="px-4 py-3 font-medium text-sand-900">{{ $row['destination'] }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-sand-800">{{ number_format($row['visits']) }}</td>
                                <td class="px-4 py-3 text-right {{ $color }}"><i class="ti {{ $icon }}" aria-hidden="true"></i></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>

            <x-dashboard.empty-state
                data-empty-state
                class="hidden mt-3"
                icon="ti-map-search"
                title="No destinations match your search"
                description="Try a different search term."
            />
        </div>
    @else
        <x-dashboard.empty-state
            class="mt-6"
            icon="ti-map-search"
            title="No visit data yet"
            description="Destination visit performance for {{ $municipality }} will appear here once tourism monitoring data is available."
        />
    @endif
</x-layouts.dashboard>
