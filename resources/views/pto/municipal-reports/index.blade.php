@php
    $statusTone = fn (string $status) => match ($status) {
        'Verified' => 'success',
        'For Review' => 'warning',
        'For Clarification' => 'danger',
        default => 'neutral',
    };
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="LGU Submissions"
        description="Consolidated monthly reports submitted by each of the province's 11 LGU Tourism Offices — verify, or return for clarification. PTO never re-encodes establishment-level data."
    />

    <form method="GET" action="{{ route('pto.municipalReports.index') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-md border border-sand-200 bg-sand-0 p-4">
        <div>
            <label for="year" class="mb-1 block text-xs font-semibold text-sand-700">Year</label>
            <select id="year" name="year" onchange="this.form.submit()" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-900">
                @foreach ($yearOptions as $y)
                    <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="month" class="mb-1 block text-xs font-semibold text-sand-700">Month</label>
            <select id="month" name="month" onchange="this.form.submit()" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-900">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($month === $m)>{{ \Carbon\CarbonImmutable::create(2000, $m, 1)->format('F') }}</option>
                @endfor
            </select>
        </div>
        @if ($statusFilter)
            <input type="hidden" name="status" value="{{ $statusFilter }}">
            <span class="rounded-sm bg-primary-100 px-3 py-2.5 text-xs font-semibold text-primary-700">
                Filtered to reports requiring attention
            </span>
            <a href="{{ route('pto.municipalReports.index', ['year' => $year, 'month' => $month]) }}" class="text-xs font-semibold text-sand-600 underline">Clear</a>
        @endif
    </form>

    <div data-filterable-table data-page-size="11" class="mt-4">
        <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 lg:flex-row lg:items-center">
            <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                <input data-filter-input type="search" placeholder="Search by municipality..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
            </div>

            <select data-filter-select data-filter-key="status" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                <option value="">All Statuses</option>
                <option value="Not Submitted">Not Submitted</option>
                <option value="For Review">For Review</option>
                <option value="For Clarification">For Clarification</option>
                <option value="Verified">Verified</option>
            </select>

            <button type="button" data-filter-reset class="rounded-sm border border-sand-300 px-3 py-2.5 text-sm font-semibold text-sand-700 hover:border-primary-300">
                Reset
            </button>
        </div>

        <p class="mt-3 text-xs text-sand-500"><span data-result-count>{{ $rows->count() }}</span> of {{ $rows->count() }} LGUs — {{ $period->format('F Y') }}</p>

        <div class="mt-3 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
            <table class="w-full min-w-[800px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <th class="px-4 py-3">Municipality</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Date Submitted</th>
                        <th class="px-4 py-3 text-right">Total Arrivals</th>
                        <th class="px-4 py-3">Last Updated</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach ($rows as $row)
                        <tr
                            data-row
                            data-status="{{ $row['status'] }}"
                            data-search-text="{{ strtolower($row['municipality']->name) }}"
                            class="hover:bg-sand-50"
                        >
                            <td class="px-4 py-3 font-medium text-sand-900">{{ $row['municipality']->name }}</td>
                            <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($row['status'])">{{ $row['status'] }}</x-dashboard.status-badge></td>
                            <td class="px-4 py-3 text-sand-700">{{ $row['report']?->created_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-sand-800">{{ $row['report'] ? number_format($row['report']->total_arrivals) : '—' }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $row['report']?->updated_at?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($row['report'])
                                    <a href="{{ route('pto.municipalReports.show', $row['report']) }}" class="rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">
                                        View
                                    </a>
                                @else
                                    <span class="text-xs text-sand-400">No submission yet</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-dashboard.empty-state
            data-empty-state
            class="hidden mt-3"
            icon="ti-file-report"
            title="No LGUs match your filters"
            description="Try a different status."
        />

        <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
    </div>
</x-layouts.dashboard>
