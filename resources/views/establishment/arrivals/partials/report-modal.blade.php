{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Quick-view modal for one of the establishment's monthly reports
    (opened by View / View Remarks on the Monthly Reports workspace). Shows
    the essentials — status, LGU remarks, totals, visitor classifications,
    submission and verification — without leaving the page. Editing and
    submitting stay on the full report page ("Open Full Report").
    Expects $report (MonthlyArrivalReport with submitter, verifier loaded).
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php($isForCorrection = $report->status === \App\Enums\MonthlyReportStatus::ForCorrection)

<x-dashboard.modal :id="'report-modal-'.$report->id" :title="$report->period_month->format('F Y').' Report'" max-width="max-w-2xl">
    @if ($isForCorrection)
        <div class="rounded-md border border-danger/30 bg-danger-bg px-4 py-3 text-sm text-danger">
            <p class="font-semibold">Returned by the LGU for correction</p>
            <p class="mt-1">What to fix: {{ $report->remarks ?: '—' }}</p>
        </div>
    @endif

    <dl @class(['grid grid-cols-2 gap-4 text-sm sm:grid-cols-3', 'mt-4' => $isForCorrection])>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Status</dt>
            <dd class="mt-1"><x-dashboard.status-badge :tone="$report->status->badgeTone()">{{ $report->status->label() }}</x-dashboard.status-badge></dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Tourist Arrivals</dt>
            <dd class="mt-1 text-lg font-bold text-sand-900">{{ number_format($report->total_visitors) }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Source</dt>
            <dd class="mt-1 text-sand-800">{{ $report->submission_source->label() }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Submitted</dt>
            <dd class="mt-1 text-sand-800">{{ $report->submitted_at ? ($report->submitter->name ?? '—').' · '.$report->submitted_at->format('M j, Y') : 'Not yet' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-sand-500 uppercase">Verified</dt>
            <dd class="mt-1 text-sand-800">{{ $report->verified_at ? ($report->verifier->name ?? 'LGU').' · '.$report->verified_at->format('M j, Y') : 'Not yet' }}</dd>
        </div>
    </dl>

    <h3 class="mt-5 text-xs font-semibold text-sand-500 uppercase">Visitor Classifications</h3>
    <dl class="mt-2 grid grid-cols-4 gap-3 rounded-md border border-sand-200 p-3 text-sm sm:grid-cols-7">
        @foreach (['Male' => 'party_male', 'Female' => 'party_female', 'Adults' => 'party_adults', 'Children' => 'party_children', 'Seniors' => 'party_seniors', 'Local' => 'party_local', 'Foreign' => 'party_foreign'] as $label => $column)
            <div>
                <dt class="text-xs text-sand-500">{{ $label }}</dt>
                <dd class="font-semibold text-sand-900">{{ number_format($report->{$column}) }}</dd>
            </div>
        @endforeach
    </dl>

    <x-slot:footer>
        <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Close</button>
        <a href="{{ route('establishment.arrivals.monthly.show', [$report, 'view' => 'a4']) }}" class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Report Preview</a>
        <a href="{{ route('establishment.arrivals.monthly.show', $report) }}" class="rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
            {{ $isForCorrection ? 'Correct Report' : 'Open Full Report' }}
        </a>
    </x-slot:footer>
</x-dashboard.modal>
