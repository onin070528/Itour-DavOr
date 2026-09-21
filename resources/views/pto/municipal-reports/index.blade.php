@php
    $statusTone = fn ($status) => match ($status) {
        'APPROVED' => 'success',
        'SUBMITTED', 'REVIEWED' => 'warning',
        'RETURNED' => 'danger',
        default => 'neutral',
    };
    $statusLabel = fn ($status) => match ($status) {
        'SUBMITTED' => 'Submitted',
        'REVIEWED' => 'Reviewed',
        'APPROVED' => 'Approved',
        'RETURNED' => 'Returned',
        default => 'Draft',
    };
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Municipal Reports"
        description="Consolidated tourism reports submitted by LGU Tourism Admins, awaiting provincial review."
    />

    <div data-filterable-table data-page-size="10" class="mt-6">
        <div class="flex flex-col gap-3 rounded-md border border-sand-200 bg-sand-0 p-4 lg:flex-row lg:items-center">
            <div class="flex flex-1 items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                <input data-filter-input type="search" placeholder="Search by municipality or submitter..." class="w-full border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
            </div>

            <select data-filter-select data-filter-key="municipality" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                <option value="">All Municipalities</option>
                @foreach ($municipalities as $m)
                    <option value="{{ $m['name'] }}">{{ $m['name'] }}</option>
                @endforeach
            </select>

            <select data-filter-select data-filter-key="status" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                <option value="">All Statuses</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}">{{ $statusLabel($s) }}</option>
                @endforeach
            </select>

            <button type="button" data-filter-reset class="rounded-sm border border-sand-300 px-3 py-2.5 text-sm font-semibold text-sand-700 hover:border-primary-300">
                Reset
            </button>
        </div>

        <p class="mt-3 text-xs text-sand-500"><span data-result-count>{{ $reports->count() }}</span> of {{ $reports->count() }} reports</p>

        <div class="mt-3 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
            <table class="w-full min-w-[800px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <th class="px-4 py-3">Municipality</th>
                        <th class="px-4 py-3">Reporting Period</th>
                        <th class="px-4 py-3 text-right">Total Arrivals</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Submitted By</th>
                        <th class="px-4 py-3">Submitted</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach ($reports as $report)
                        <tr
                            data-row
                            data-municipality="{{ $report->municipality }}"
                            data-status="{{ $report->status }}"
                            data-search-text="{{ strtolower($report->municipality.' '.($report->submitter->name ?? '')) }}"
                            class="hover:bg-sand-50"
                        >
                            <td class="px-4 py-3 font-medium text-sand-900">{{ $report->municipality }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $report->period_start->format('M j') }} – {{ $report->period_end->format('M j, Y') }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-sand-800">{{ number_format($report->total_arrivals) }}</td>
                            <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($report->status)">{{ $statusLabel($report->status) }}</x-dashboard.status-badge></td>
                            <td class="px-4 py-3 text-sand-700">{{ $report->submitter->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $report->created_at->format('M j, Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('pto.municipalReports.show', $report) }}" class="rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">
                                    Review
                                </a>
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
            title="No municipal reports match your filters"
            description="Try a different municipality or status."
        />

        <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
    </div>
</x-layouts.dashboard>
