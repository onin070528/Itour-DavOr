@php
    $statusTone = fn (?string $status) => match ($status) {
        'Verified' => 'success',
        'For Review' => 'warning',
        default => 'danger',
    };
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        title="Tourism Reports"
        description="Province-wide view of establishment monthly reporting progress, by municipality — read-only; encoding, review, and consolidation stay with each LGU."
    />

    <form method="GET" action="{{ route('pto.monthlyReports.index') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-md border border-sand-200 bg-sand-0 p-4">
        <div>
            <label for="period" class="mb-1 block text-xs font-semibold text-sand-700">Reporting Month</label>
            <select id="period" name="period" onchange="this.form.submit()" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-900">
                @foreach ($monthOptions as $option)
                    <option value="{{ $option->format('Y-m') }}" @selected($option->isSameMonth($month))>{{ $option->format('F Y') }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="municipality_id" class="mb-1 block text-xs font-semibold text-sand-700">Municipality / LGU</label>
            <select id="municipality_id" name="municipality_id" onchange="this.form.submit()" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-900">
                @foreach ($municipalities as $option)
                    <option value="{{ $option->id }}" @selected($municipality?->id === $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
    </form>

    @if ($municipality)
        <div class="mt-6">
            <x-dashboard.workflow-steps :steps="$steps" />
        </div>

        <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
            <x-dashboard.kpi-card label="Total Establishments" :value="$rows->count()" tone="neutral" />
            <x-dashboard.kpi-card label="Submitted" :value="$submittedCount" tone="neutral" />
            <x-dashboard.kpi-card label="For Review" :value="$forReviewCount" tone="warning" />
            <x-dashboard.kpi-card label="Not Submitted" :value="$missingCount" tone="danger" />
            <x-dashboard.kpi-card label="Verified" :value="$verifiedCount" tone="success" />
        </div>

        <div data-filterable-table data-page-size="10" class="mt-6">
            <div class="rounded-t-md border border-b-0 border-sand-200 bg-sand-0 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-base font-bold text-sand-900">Establishment Reporting Status · {{ $municipality->name }} · {{ $month->format('F Y') }}</h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <select data-filter-select data-filter-key="status" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-700">
                            <option value="">All</option>
                            <option value="Not Submitted">Not Submitted</option>
                            <option value="For Review">For Review</option>
                            <option value="Verified">Verified</option>
                        </select>
                        <div class="flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5">
                            <i class="ti ti-search text-sand-500" aria-hidden="true"></i>
                            <input data-filter-input type="search" placeholder="Search establishment..." class="w-48 border-0 bg-transparent text-sm text-sand-900 placeholder:text-sand-500 focus:outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto rounded-b-md border border-sand-200 bg-sand-0 shadow-sm">
                <table class="w-full min-w-[840px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                            <th class="px-4 py-3">Establishment</th>
                            <th class="px-4 py-3">Submission Source</th>
                            <th class="px-4 py-3">Submitted / Encoded</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Arrival Total</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sand-100">
                        @forelse ($rows as $row)
                            <tr data-row data-status="{{ $row['status'] }}" data-search-text="{{ strtolower($row['listing']->name) }}" class="hover:bg-sand-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-sand-900">{{ $row['listing']->name }}</p>
                                    <p class="text-xs text-sand-500">{{ \Illuminate\Support\Str::headline($row['listing']->category) }}</p>
                                </td>
                                <td class="px-4 py-3 text-sand-700">
                                    @if ($row['report'])
                                        <span class="inline-flex items-center gap-1.5"><i class="ti {{ $row['report']->submission_source->icon() }}" aria-hidden="true"></i> {{ $row['report']->submission_source->label() }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sand-700">{{ $row['report']?->submitted_at->format('M j') ?? '—' }}</td>
                                <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($row['status'])">{{ $row['status'] }}</x-dashboard.status-badge></td>
                                <td class="px-4 py-3 text-right font-semibold text-sand-800">{{ $row['report'] ? number_format($row['report']->total_visitors) : '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($row['report'])
                                        <a href="{{ route('pto.monthlyReports.show', $row['report']) }}" class="text-sm font-semibold text-sand-700 hover:text-primary-700">View</a>
                                    @else
                                        <span class="text-sm text-sand-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-sand-500">No establishments found for this municipality.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="mt-3 text-xs text-sand-500"><span data-result-count>{{ $rows->count() }}</span> of {{ $rows->count() }} establishments</p>

            <x-dashboard.empty-state
                data-empty-state
                class="hidden mt-3"
                icon="ti-map-search"
                title="No establishments match"
                description="Try a different search term or status filter."
            />

            <div data-pagination class="mt-4 flex items-center justify-center gap-1"></div>
        </div>
    @else
        <x-dashboard.empty-state
            class="mt-6"
            icon="ti-map-search"
            title="No municipalities found"
            description="Add a municipality before monthly reports can be tracked."
        />
    @endif
</x-layouts.dashboard>
