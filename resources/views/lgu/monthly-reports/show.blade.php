@php
    $isVerified = $report->status === \App\Enums\MonthlyReportStatus::Verified;
    $isManualPaper = $report->submission_source === \App\Enums\ReportSubmissionSource::ManualPaper;
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        :title="$report->listing->name.' — '.$report->period_month->format('F Y')"
        description="Monthly tourist-arrival report."
    >
        <x-slot:actions>
            @unless ($locked)
                <a href="{{ route('lgu.monthlyReports.edit', $report) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    <i class="ti ti-edit" aria-hidden="true"></i>
                    Edit / Correct
                </a>
            @endunless
            <a href="{{ route('lgu.monthlyReports.index', ['period' => $report->period_month->format('Y-m')]) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Monthly Reports
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    @if ($locked)
        <div class="mt-6 flex items-center gap-2 rounded-md border border-sand-300 bg-sand-100 px-4 py-3 text-sm font-semibold text-sand-700">
            <i class="ti ti-lock" aria-hidden="true"></i>
            This report is part of a provincial report PTO has already approved, so it can no longer be corrected here.
        </div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-dashboard.kpi-card label="Total Visitors" :value="number_format($report->total_visitors)" tone="neutral" />
        <div class="rounded-md border border-sand-200 bg-sand-0 p-4">
            <p class="text-xs font-medium text-sand-500">Status</p>
            <p class="mt-1.5"><x-dashboard.status-badge :tone="$report->status->badgeTone()">{{ $report->status->label() }}</x-dashboard.status-badge></p>
        </div>
        <div class="rounded-md border border-sand-200 bg-sand-0 p-4">
            <p class="text-xs font-medium text-sand-500">Source</p>
            <p class="mt-1.5"><x-dashboard.status-badge :tone="$report->submission_source->badgeTone()">{{ $report->submission_source->label() }}</x-dashboard.status-badge></p>
        </div>
    </div>

    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        <h2 class="font-display text-base font-bold text-sand-900">Visitor Breakdown</h2>
        <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ([
                'Male' => $report->party_male,
                'Female' => $report->party_female,
                'Adults' => $report->party_adults,
                'Children' => $report->party_children,
                'Seniors' => $report->party_seniors,
                'Local' => $report->party_local,
                'Foreign' => $report->party_foreign,
            ] as $label => $value)
                <div>
                    <dt class="text-xs font-semibold text-sand-500 uppercase">{{ $label }}</dt>
                    <dd class="mt-1 text-sm text-sand-800">{{ number_format($value) }}</dd>
                </div>
            @endforeach
        </dl>

        <dl class="mt-5 grid grid-cols-1 gap-4 border-t border-sand-200 pt-5 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold text-sand-500 uppercase">Submitted</dt>
                <dd class="mt-1 text-sm text-sand-800">{{ $report->submitter->name ?? '—' }} · {{ $report->submitted_at->format('M j, Y g:i A') }}</dd>
            </div>
            @if ($report->verified_at)
                <div>
                    <dt class="text-xs font-semibold text-sand-500 uppercase">Verified</dt>
                    <dd class="mt-1 text-sm text-sand-800">{{ $report->verifier->name ?? '—' }} · {{ $report->verified_at->format('M j, Y g:i A') }}</dd>
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
                            <td class="px-4 py-3 text-sand-700">{{ $arrival->date->format('M j, Y') }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $arrival->visit_type ?? '—' }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $arrival->party_size }}</td>
                            <td class="px-4 py-3 text-sand-700">{{ $arrival->source === 'self_checkin' ? 'QR Self Check-in' : 'Front Desk' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($isVerified)
        <div class="mt-6 flex items-center gap-2 rounded-md border border-success/20 bg-success-bg px-4 py-3 text-sm font-semibold text-success">
            <i class="ti ti-circle-check" aria-hidden="true"></i>
            This report has been verified.
        </div>
    @else
        <div class="mt-6">
            <form method="POST" action="{{ route('lgu.monthlyReports.verify', $report) }}">
                @csrf
                @method('PATCH')
                <button
                    type="button"
                    data-confirm-trigger
                    data-confirm-title="Verify this report?"
                    data-confirm-message="Confirm the encoded numbers match {{ $isManualPaper ? 'the physical report' : 'the recorded arrivals above' }} before verifying."
                    data-confirm-label="Verify"
                    data-confirm-tone="success"
                    class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900"
                >
                    <i class="ti ti-check" aria-hidden="true"></i>
                    Verify
                </button>
            </form>
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
                            <td class="px-4 py-3 whitespace-nowrap text-sand-700">{{ $entry->created_at->format('M j, Y g:i A') }}</td>
                            <td class="px-4 py-3"><x-dashboard.status-badge :tone="\App\Models\OperationLog::badgeTone($entry->action)">{{ ucfirst($entry->action) }}</x-dashboard.status-badge></td>
                            <td class="px-4 py-3 text-sand-700">{{ $entry->user->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sand-600">
                                {{ $entry->reason ?? '—' }}
                                @if ($entry->old_values || $entry->new_values)
                                    <p class="mt-1 text-xs text-sand-500">
                                        @foreach (array_keys($entry->new_values ?? []) as $field)
                                            {{ $field }}: {{ $entry->old_values[$field] ?? '—' }} → {{ $entry->new_values[$field] ?? '—' }}@if (! $loop->last), @endif
                                        @endforeach
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.dashboard>
