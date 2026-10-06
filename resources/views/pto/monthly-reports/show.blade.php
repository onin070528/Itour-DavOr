{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: PTO read-only monthly arrival report detail.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        :title="$report->listing->lst_name.' — '.$report->mar_period_month->format('F Y')"
        description="Monthly tourist-arrival report (read-only)."
    >
        <x-slot:actions>
            <a href="{{ route('pto.monthlyReports.index', ['period' => $report->mar_period_month->format('Y-m'), 'municipality_id' => $report->mun_id]) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Provincial Reports
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-dashboard.kpi-card label="Total Visitors" :value="number_format($report->mar_total_visitors)" tone="neutral" />
        <div class="rounded-md border border-sand-200 bg-sand-0 p-4">
            <p class="text-xs font-medium text-sand-500">Status</p>
            <p class="mt-1.5"><x-dashboard.status-badge :tone="$report->mar_status->badgeTone()">{{ $report->mar_status->label() }}</x-dashboard.status-badge></p>
        </div>
        <div class="rounded-md border border-sand-200 bg-sand-0 p-4">
            <p class="text-xs font-medium text-sand-500">Source</p>
            <p class="mt-1.5"><x-dashboard.status-badge :tone="$report->mar_submission_source->badgeTone()">{{ $report->mar_submission_source->label() }}</x-dashboard.status-badge></p>
        </div>
    </div>

    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        <h2 class="font-display text-base font-bold text-sand-900">Visitor Breakdown</h2>
        <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ([
                'Male' => $report->mar_party_male,
                'Female' => $report->mar_party_female,
                'Adults' => $report->mar_party_adults,
                'Children' => $report->mar_party_children,
                'Seniors' => $report->mar_party_seniors,
                'Local' => $report->mar_party_local,
                'Foreign' => $report->mar_party_foreign,
            ] as $label => $value)
                <div>
                    <dt class="text-xs font-semibold text-sand-500 uppercase">{{ $label }}</dt>
                    <dd class="mt-1 text-sm text-sand-800">{{ number_format($value) }}</dd>
                </div>
            @endforeach
        </dl>

        @if ($originBreakdown['withinProvince'] > 0 || $originBreakdown['outsideProvince'] > 0 || $originBreakdown['topForeignCountries']->isNotEmpty())
            <div class="mt-5 border-t border-sand-200 pt-5">
                <h3 class="font-display text-sm font-bold text-sand-900">Local Guest Origin</h3>
                <dl class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div>
                        <dt class="text-xs font-semibold text-sand-500 uppercase">Within Davao Oriental</dt>
                        <dd class="mt-1 text-sm text-sand-800">{{ number_format($originBreakdown['withinProvince']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-sand-500 uppercase">Outside Davao Oriental</dt>
                        <dd class="mt-1 text-sm text-sand-800">{{ number_format($originBreakdown['outsideProvince']) }}</dd>
                    </div>
                </dl>

                @if ($originBreakdown['topOriginPlaces']->isNotEmpty())
                    <div class="mt-4">
                        <p class="text-xs font-semibold text-sand-500 uppercase">Top Origin Provinces</p>
                        <ul class="mt-1.5 flex flex-col gap-1 text-sm text-sand-800">
                            @foreach ($originBreakdown['topOriginPlaces'] as $place => $count)
                                <li class="flex items-center justify-between">
                                    <span>{{ $place }}</span>
                                    <span class="font-semibold">{{ number_format($count) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($originBreakdown['topForeignCountries']->isNotEmpty())
                    <div class="mt-4">
                        <p class="text-xs font-semibold text-sand-500 uppercase">Top Foreign Countries</p>
                        <ul class="mt-1.5 flex flex-col gap-1 text-sm text-sand-800">
                            @foreach ($originBreakdown['topForeignCountries'] as $country => $count)
                                <li class="flex items-center justify-between">
                                    <span>{{ $country }}</span>
                                    <span class="font-semibold">{{ number_format($count) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        <dl class="mt-5 grid grid-cols-1 gap-4 border-t border-sand-200 pt-5 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold text-sand-500 uppercase">Submitted</dt>
                <dd class="mt-1 text-sm text-sand-800">{{ $report->submitter->usr_name ?? '—' }} · {{ $report->mar_submitted_at->format('M j, Y g:i A') }}</dd>
            </div>
            @if ($report->mar_verified_at)
                <div>
                    <dt class="text-xs font-semibold text-sand-500 uppercase">Verified</dt>
                    <dd class="mt-1 text-sm text-sand-800">{{ $report->verifier->usr_name ?? '—' }} · {{ $report->mar_verified_at->format('M j, Y g:i A') }}</dd>
                </div>
            @endif
        </dl>
    </div>

    @if ($report->arrivals->isNotEmpty())
        <div class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
            <h2 class="border-b border-sand-200 px-5 py-4 font-display text-base font-bold text-sand-900">Underlying Arrivals ({{ $report->arrivals->count() }})</h2>
            <table class="w-full min-w-[600px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Visit Type</th>
                        <th class="px-4 py-3">Party Size</th>
                        <th class="px-4 py-3">Logged Via</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach ($report->arrivals as $arrival)
                        <tr>
                            <td class="px-4 py-3 text-sand-700">{{ $arrival->arr_date->format('M j, Y') }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $arrival->arr_visit_type ?? '—' }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $arrival->arr_party_size }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $arrival->arr_source === \App\Enums\ArrivalSource::SelfCheckin ? 'QR Self Check-in' : 'Front Desk' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($history->isNotEmpty())
        <div class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
            <h2 class="border-b border-sand-200 px-5 py-4 font-display text-base font-bold text-sand-900">Verification History</h2>
            <table class="w-full min-w-[640px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">By</th>
                        <th class="px-4 py-3">Reason</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach ($history as $entry)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sand-700">{{ $entry->opl_created_at->format('M j, Y g:i A') }}</td>
                            <td class="px-4 py-3"><x-dashboard.status-badge :tone="\App\Models\OperationLog::badgeTone($entry->opl_action)">{{ ucfirst($entry->opl_action) }}</x-dashboard.status-badge></td>
                            <td class="px-4 py-3 text-sand-700">{{ $entry->user->usr_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sand-600">{{ $entry->opl_reason ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.dashboard>
