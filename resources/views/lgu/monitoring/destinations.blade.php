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
        <div class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
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
                        <tr class="hover:bg-sand-50">
                            <td class="px-4 py-3 text-sand-500">{{ $row['rank'] }}</td>
                            <td class="px-4 py-3 font-medium text-sand-900">{{ $row['destination'] }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-sand-800">{{ number_format($row['visits']) }}</td>
                            <td class="px-4 py-3 text-right {{ $color }}"><i class="ti {{ $icon }}" aria-hidden="true"></i></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
