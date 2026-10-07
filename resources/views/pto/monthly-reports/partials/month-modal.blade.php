{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: View / Review modal for one month of the PTO Provincial Reports
    Monthly Records. Shows the month's provincial summary, then every LGU's
    report: status, arrivals, submission and PTO-review details, Report
    Preview, and — only for LGU reports waiting on PTO — Verify Report /
    Return for Correction, posting to the same existing routes and rules as
    LGU Submissions (Pto\MunicipalReportsController::approve() / return()).
    Each LGU can be expanded to its establishments' report statuses.
    Expects $monthRow (from Pto\MonthlyReportsController::_provincialMonth())
    and $municipalityCount.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $lguTone = fn (string $status) => match ($status) {
        'Verified' => 'success',
        'For Review' => 'warning',
        'For Clarification' => 'danger',
        default => 'neutral',
    };
    $isReview = $monthRow['action'] === 'review';
    $coverage = $municipalityCount > 0 ? round($monthRow['approvedCount'] / $municipalityCount * 100, 1) : 0;
@endphp

<x-dashboard.modal :id="'province-month-'.$monthRow['month']->month" :title="($isReview ? 'Review — ' : '').$monthRow['month']->format('F Y').' Provincial Report'" max-width="max-w-4xl">
    <dl class="grid grid-cols-2 gap-4 rounded-md border border-sand-200 bg-sand-50 p-4 text-sm sm:grid-cols-4">
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Status</dt>
            <dd class="mt-1"><x-dashboard.status-badge :tone="$monthRow['statusTone']">{{ $monthRow['status'] }}</x-dashboard.status-badge></dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Official Arrivals</dt>
            <dd class="mt-1 font-semibold text-sand-900">{{ $monthRow['approvedCount'] > 0 ? number_format($monthRow['record']['total']) : '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">LGUs Reported</dt>
            <dd class="mt-1 text-sand-800">{{ $monthRow['reportedCount'] }} of {{ $municipalityCount }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Coverage</dt>
            <dd class="mt-1 text-sand-800">{{ $monthRow['approvedCount'] }} verified ({{ $coverage }}%)</dd>
        </div>
    </dl>

    @if ($monthRow['visitorBreakdown'] && $monthRow['visitorBreakdown']['total'] > 0)
        @php($visitors = $monthRow['visitorBreakdown'])
        <div class="mt-4">
            <h3 class="text-xs font-semibold text-sand-500 uppercase">Visitor Classifications (verified LGU reports)</h3>
            <dl class="mt-2 grid grid-cols-4 gap-3 text-sm sm:grid-cols-7">
                @foreach (['Male' => 'male', 'Female' => 'female', 'Adults' => 'adults', 'Children' => 'children', 'Seniors' => 'seniors', 'Local' => 'local', 'Foreign' => 'foreign'] as $label => $key)
                    <div>
                        <dt class="text-xs text-sand-500">{{ $label }}</dt>
                        <dd class="font-semibold text-sand-900">{{ number_format($visitors[$key]) }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif

    <h3 class="mt-5 text-xs font-semibold text-sand-500 uppercase">LGU Reports</h3>
    <ul class="mt-2 flex flex-col gap-2">
        @foreach ($monthRow['lgus'] as $lgu)
            @php($report = $lgu['report'])
            <li @class(['rounded-md border p-3', 'border-warning/40 bg-warning-bg/40' => $lgu['isPendingPto'], 'border-sand-200' => ! $lgu['isPendingPto']])>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-sand-900">{{ $lgu['municipality']->name }}</p>
                        @if ($report)
                            <p class="text-xs text-sand-600">
                                {{ number_format($report->total_arrivals) }} arrivals ·
                                submitted by {{ $report->submitter->name ?? '—' }} on {{ $report->updated_at->format('M j, Y') }}
                                @if ($report->reviewed_at)
                                    · reviewed by {{ $report->reviewer->name ?? 'PTO' }} on {{ $report->reviewed_at->format('M j, Y') }}
                                @endif
                            </p>
                        @else
                            <p class="text-xs text-sand-500">No municipal report submitted for this month.</p>
                        @endif
                    </div>
                    <x-dashboard.status-badge :tone="$lguTone($lgu['status'])">{{ $lgu['status'] }}</x-dashboard.status-badge>
                </div>

                @if ($report?->status === \App\Models\MunicipalReport::STATUS_RETURNED && $report->remarks)
                    <p class="mt-2 rounded-sm bg-danger-bg px-2.5 py-1.5 text-xs text-danger">Returned with remarks: {{ $report->remarks }}</p>
                @endif

                @if ($report)
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <a href="{{ route('pto.municipalReports.officialReport', $report) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300">
                            <i class="ti ti-file-description" aria-hidden="true"></i>
                            Report Preview
                        </a>

                        @if ($lgu['isPendingPto'])
                            <form method="POST" action="{{ route('pto.municipalReports.approve', $report) }}">
                                @csrf
                                @method('PATCH')
                                <button
                                    type="button"
                                    data-confirm-trigger
                                    data-confirm-title="Verify {{ $lgu['municipality']->name }}'s report?"
                                    data-confirm-message="Once verified, it becomes part of the official provincial report and is locked."
                                    data-confirm-label="Verify Report"
                                    data-confirm-tone="success"
                                    class="rounded-sm bg-primary-700 px-3 py-1.5 text-xs font-semibold text-sand-0 hover:bg-primary-900"
                                >
                                    Verify Report
                                </button>
                            </form>

                            <details class="w-full">
                                <summary class="inline-flex cursor-pointer items-center gap-1.5 rounded-sm border border-danger/30 px-3 py-1.5 text-xs font-semibold text-danger hover:bg-danger-bg">Return for Correction</summary>
                                <form method="POST" action="{{ route('pto.municipalReports.return', $report) }}" class="mt-2 flex flex-col gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <label for="return-remarks-{{ $report->id }}" class="text-xs font-semibold text-sand-700">What should the LGU correct? <span class="text-danger" aria-hidden="true">*</span></label>
                                    <textarea id="return-remarks-{{ $report->id }}" name="remarks" rows="2" required maxlength="2000" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm"></textarea>
                                    <div>
                                        <button type="submit" class="rounded-sm bg-danger px-3 py-1.5 text-xs font-semibold text-sand-0 hover:opacity-90">Return to LGU</button>
                                    </div>
                                </form>
                            </details>
                        @endif
                    </div>
                @endif

                @if ($lgu['establishments']->isNotEmpty())
                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs font-semibold text-primary-700 hover:text-primary-900">
                            Show establishments ({{ $lgu['verifiedEstablishmentCount'] }} of {{ $lgu['establishments']->count() }} verified by the LGU)
                        </summary>
                        <table class="mt-2 w-full border-collapse text-xs">
                            <tbody class="divide-y divide-sand-100">
                                @foreach ($lgu['establishments'] as $establishment)
                                    <tr>
                                        <td class="py-1.5 pr-2 text-sand-800">{{ $establishment['name'] }}</td>
                                        <td class="py-1.5 pr-2">
                                            <x-dashboard.status-badge :tone="$establishment['report']?->status->badgeTone() ?? 'danger'">{{ $establishment['report']?->status->label() ?? 'Not Submitted' }}</x-dashboard.status-badge>
                                        </td>
                                        <td class="py-1.5 text-right font-semibold text-sand-800">{{ $establishment['report'] ? number_format($establishment['report']->total_visitors) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                @endif
            </li>
        @endforeach
    </ul>

    <x-slot:footer>
        <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Close</button>
    </x-slot:footer>
</x-dashboard.modal>
