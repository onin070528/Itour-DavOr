@php
    $isVerified = $report->status === \App\Enums\MonthlyReportStatus::Verified;
    $isDraft = $report->status === \App\Enums\MonthlyReportStatus::Draft;
    $isForCorrection = $report->status === \App\Enums\MonthlyReportStatus::ForCorrection;
    $isManualPaper = $report->submission_source === \App\Enums\ReportSubmissionSource::ManualPaper;
    $isSubmitted = $report->status === \App\Enums\MonthlyReportStatus::Submitted;
    $isSubmittedOrVerified = ! $isDraft && ! $isForCorrection;
    $period = $report->period_month->format('Y-m');
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        :title="$report->listing->name.' — '.$report->period_month->format('F Y')"
        description="Establishment monthly tourist-arrival report — review it, then verify or return it for correction."
    >
        <x-slot:actions>
            @unless ($locked)
                <a href="{{ route('lgu.monthlyReports.edit', $report) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    <i class="ti ti-edit" aria-hidden="true"></i>
                    Edit / Correct
                </a>
            @endunless
            @can('editDraft', $report)
                <a href="{{ route('lgu.monthlyReports.manualEntry', ['listing' => $report->listing, 'period' => $report->period_month->format('Y-m')]) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    <i class="ti ti-edit" aria-hidden="true"></i>
                    Edit Draft
                </a>
            @endcan
            @if ($isManualPaper && $isDraft)
                <a href="{{ route('lgu.monthlyReports.manualEntry.index', ['period' => $period]) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    <i class="ti ti-arrow-left" aria-hidden="true"></i>
                    Back to Manual Entry
                </a>
            @else
                <a href="{{ route('lgu.monthlyReports.index', ['period' => $period]) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    <i class="ti ti-arrow-left" aria-hidden="true"></i>
                    Back to Establishment Reports
                </a>
            @endif
        </x-slot:actions>
    </x-dashboard.page-header>

    @if ($locked && $isSubmittedOrVerified)
        <div class="mt-6 flex items-center gap-2 rounded-md border border-sand-300 bg-sand-100 px-4 py-3 text-sm font-semibold text-sand-700">
            <i class="ti ti-lock" aria-hidden="true"></i>
            This report is part of a municipal report already with PTO, so it can no longer be corrected here.
        </div>
    @endif

    <x-dashboard.tabs sync="view" panel-host="#lgu-report-panels" :tabs="[
        ['label' => 'Report Details', 'panel' => 'details', 'active' => $activeView === 'details', 'icon' => 'ti-list-details'],
        ['label' => 'Report Preview', 'panel' => 'a4', 'active' => $activeView === 'a4', 'icon' => 'ti-file-description'],
    ]" />

    <div id="lgu-report-panels">
    <div data-tab-panel="details" @class(['hidden' => $activeView !== 'details'])>
    @if ($balanceErrors !== [])
        <div class="mt-6 rounded-md border border-warning/30 bg-warning-bg px-4 py-3 text-sm text-warning">
            <p class="font-semibold">Check these totals before verifying — PTO will not accept a municipal report whose columns don't add up:</p>
            <ul class="mt-1 list-disc pl-5 text-xs">
                @foreach ($balanceErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
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
                <dd class="mt-1 text-sm text-sand-800">
                    @if ($report->submitted_at)
                        {{ $report->submitter->name ?? '—' }} · {{ $report->submitted_at->format('M j, Y g:i A') }}
                    @else
                        Not yet submitted
                    @endif
                </dd>
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
                            <td class="px-4 py-3 text-sand-700">{{ $arrival->source === \App\Enums\ArrivalSource::SelfCheckin ? 'QR Self Check-in' : 'Front Desk' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    </div>

    {{-- Report Preview: the same official A4 layout used for printing and for the municipal report. --}}
    <div data-tab-panel="a4" @class(['hidden' => $activeView !== 'a4'])>
        <x-dashboard.report-preview-frame
            frame-id="lgu-report-preview-frame"
            :preview-url="route('lgu.monthlyReports.preview', $report)"
            :pdf-url="route('lgu.monthlyReports.pdf', $report)"
        />
    </div>
    </div>

    @if ($isVerified)
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-md border border-success/20 bg-success-bg px-4 py-3 text-sm font-semibold text-success">
            <span class="flex items-center gap-2"><i class="ti ti-circle-check" aria-hidden="true"></i> This report has been verified and counts toward the municipal report.</span>
            <a href="{{ route('lgu.monthlyReports.municipal.show', $period) }}" class="text-xs font-semibold text-primary-700 hover:text-primary-900">View {{ $report->period_month->format('F Y') }} Municipal Report</a>
        </div>
    @elseif ($isForCorrection)
        <div class="mt-6 rounded-md border border-danger/30 bg-danger-bg px-4 py-3 text-sm text-danger">
            <p class="flex items-center gap-2 font-semibold"><i class="ti ti-arrow-back-up" aria-hidden="true"></i> Returned to the establishment for correction. Waiting for it to resubmit.</p>
            <p class="mt-1">Remarks: {{ $report->remarks ?: '—' }}</p>
        </div>
    @elseif ($isSubmitted)
        @can('startReview', $report)
            <div class="mt-6">
                <form method="POST" action="{{ route('lgu.monthlyReports.review', $report) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                        <i class="ti ti-eye-check" aria-hidden="true"></i>
                        Start Review
                    </button>
                </form>
            </div>
        @endcan
    @elseif ($isDraft)
        @can('submit', $report)
            <div class="mt-6 flex flex-wrap items-center gap-3">
                <p class="w-full text-sm text-sand-600">This paper report is a draft. Check the Report Preview against the paper report, then submit it for review and verification.</p>
                <form method="POST" action="{{ route('lgu.monthlyReports.submit', $report) }}">
                    @csrf
                    @method('PATCH')
                    <button
                        type="button"
                        data-confirm-trigger
                        data-confirm-title="Submit this encoded report?"
                        data-confirm-message="Confirm the encoded numbers match the physical report. After submitting, the draft can no longer be edited and the report still needs to be verified."
                        data-confirm-label="Submit"
                        class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900"
                    >
                        <i class="ti ti-send" aria-hidden="true"></i>
                        Submit
                    </button>
                </form>
            </div>
        @endcan
    @else
        <div class="mt-6 flex flex-wrap items-center gap-3">
            @can('verify', $report)
            <form method="POST" action="{{ route('lgu.monthlyReports.verify', $report) }}">
                @csrf
                @method('PATCH')
                <button
                    type="button"
                    data-confirm-trigger
                    data-confirm-title="Verify this report?"
                    data-confirm-message="Confirm the encoded numbers match {{ $isManualPaper ? 'the physical report' : 'the recorded arrivals above' }} before verifying."
                    data-confirm-label="Verify Report"
                    data-confirm-tone="success"
                    class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900"
                >
                    <i class="ti ti-check" aria-hidden="true"></i>
                    Verify Report
                </button>
            </form>
            @endcan

            @can('returnForCorrection', $report)
                <button type="button" data-modal-open="return-report-modal" class="inline-flex items-center gap-2 rounded-sm border border-danger/30 px-4 py-2.5 text-sm font-semibold text-danger hover:bg-danger-bg">
                    <i class="ti ti-arrow-back-up" aria-hidden="true"></i>
                    Return for Correction
                </button>
            @endcan
        </div>

        @can('returnForCorrection', $report)
            <x-dashboard.modal id="return-report-modal" title="Return for Correction">
                <form id="return-report-form" method="POST" action="{{ route('lgu.monthlyReports.return', $report) }}" class="flex flex-col gap-3">
                    @csrf
                    @method('PATCH')
                    <p class="text-sm text-sand-700">The establishment will see these remarks, fix its arrival records, and resubmit the report.</p>
                    <label for="return-remarks" class="text-xs font-semibold text-sand-700">Remarks <span class="text-danger" aria-hidden="true">*</span></label>
                    <textarea id="return-remarks" name="remarks" rows="3" required maxlength="2000" placeholder="What needs to be corrected?" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>
                    @error('remarks')
                        <p class="text-xs text-danger">{{ $message }}</p>
                    @enderror
                </form>
                <x-slot:footer>
                    <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
                    <button type="submit" form="return-report-form" class="rounded-sm bg-danger px-4 py-2 text-sm font-semibold text-sand-0 hover:opacity-90">Return</button>
                </x-slot:footer>
            </x-dashboard.modal>
        @endcan
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
