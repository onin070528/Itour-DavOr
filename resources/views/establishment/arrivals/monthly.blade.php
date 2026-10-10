{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Establishment Monthly Reports page — submission history and the Submit control.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        title="Monthly Reports"
        description="Submit {{ $establishmentName }}'s monthly tourist-arrival report for LGU review."
    />

    @if ($reminder)
        <div class="mt-6 flex items-start gap-3 rounded-md border {{ $reminder['stage'] === 'upcoming' ? 'border-warning bg-warning-bg' : 'border-danger bg-danger-bg' }} p-4 text-sm text-sand-800">
            <i class="ti {{ $reminder['stage'] === 'upcoming' ? 'ti-bell-ringing' : 'ti-alert-triangle' }} mt-0.5 text-lg" aria-hidden="true"></i>
            <p>
                @if ($reminder['stage'] === 'upcoming')
                    Your <strong>{{ $reminder['period']->format('F Y') }}</strong> report is due on {{ $reminder['due']->format('F j') }} — {{ $reminder['daysLeft'] }} {{ \Illuminate\Support\Str::plural('day', $reminder['daysLeft']) }} left.
                @elseif ($reminder['stage'] === 'due')
                    Your <strong>{{ $reminder['period']->format('F Y') }}</strong> report is due today.
                @else
                    Your <strong>{{ $reminder['period']->format('F Y') }}</strong> report was due on {{ $reminder['due']->format('F j') }} and is overdue.
                @endif
            </p>
        </div>
    @endif

    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        <h2 class="font-display text-base font-bold text-sand-900">Submit a Monthly Report</h2>
        <p class="mt-1.5 text-sm text-sand-600">
            This totals whatever guests you've already logged through Record Arrival for the month you pick — there's nothing extra to type. A month with no recorded guests still submits, as a zero-arrival report.
        </p>

        @if ($monthOptions->isEmpty())
            <p class="mt-4 text-sm text-sand-500">Every month in the last year has already been submitted.</p>
        @else
            <form id="monthly-report-form" method="POST" action="{{ route('establishment.arrivals.monthly.submit') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label for="period_month" class="mb-1 block text-xs font-semibold text-sand-700">Reporting Month</label>
                    <select id="period_month" name="period_month" class="rounded-sm border border-sand-300 bg-sand-50 px-3 py-2.5 text-sm text-sand-900">
                        @foreach ($monthOptions as $month)
                            <option value="{{ $month->format('Y-m') }}">{{ $month->format('F Y') }}</option>
                        @endforeach
                    </select>
                </div>
                <button
                    type="button"
                    data-confirm-trigger
                    data-confirm-title="Submit this month's report?"
                    data-confirm-message="Once submitted, this report can no longer be edited from your side — the LGU will review and verify it."
                    data-confirm-label="Submit"
                    class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900"
                >
                    <i class="ti ti-send" aria-hidden="true"></i>
                    Submit Report
                </button>
            </form>
        @endif
    </div>

    <div class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
        <table class="w-full min-w-[640px] border-collapse text-sm">
            <thead>
                <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                    <th class="px-4 py-3">Month</th>
                    <th class="px-4 py-3">Total Visitors</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Submitted</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sand-100">
                @forelse ($reports as $report)
                    <tr class="hover:bg-sand-50">
                        <td class="px-4 py-3 font-medium text-sand-900">{{ $report->mar_period_month->format('F Y') }}</td>
                        <td class="px-4 py-3 text-sand-700">{{ number_format($report->mar_total_visitors) }}</td>
                        <td class="px-4 py-3"><x-dashboard.status-badge :tone="$report->mar_status->badgeTone()">{{ $report->mar_status->label() }}</x-dashboard.status-badge></td>
                        <td class="px-4 py-3 text-sand-700">{{ $report->mar_submitted_at->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-sand-500">No monthly reports submitted yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.dashboard>
