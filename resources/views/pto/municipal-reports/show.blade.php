@php
    $statusTone = match ($report->status) {
        'APPROVED' => 'success',
        'SUBMITTED', 'REVIEWED' => 'warning',
        'RETURNED' => 'danger',
        default => 'neutral',
    };
    $statusLabel = match ($report->status) {
        'SUBMITTED' => 'Submitted',
        'REVIEWED' => 'Reviewed',
        'APPROVED' => 'Approved',
        'RETURNED' => 'Returned',
        default => 'Draft',
    };
    $isApproved = $report->status === 'APPROVED';
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        :title="$report->municipality.' — Municipal Report'"
        :description="$report->period_start->format('F j, Y').' to '.$report->period_end->format('F j, Y')"
    >
        <x-slot:actions>
            <a href="{{ route('pto.municipalReports.index') }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Municipal Reports
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-dashboard.kpi-card label="Total Arrivals" :value="number_format($report->total_arrivals)" tone="neutral" />
        <div class="rounded-md border border-sand-200 bg-sand-0 p-4">
            <p class="text-xs font-medium text-sand-500">Status</p>
            <p class="mt-1.5"><x-dashboard.status-badge :tone="$statusTone">{{ $statusLabel }}</x-dashboard.status-badge></p>
        </div>
        <x-dashboard.kpi-card label="Submitted By" :value="$report->submitter->name ?? '—'" :delta="$report->created_at->format('M j, Y')" tone="neutral" />
    </div>

    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        <h2 class="font-display text-base font-bold text-sand-900">Report Details</h2>
        <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold text-sand-500 uppercase">Municipality</dt>
                <dd class="mt-1 text-sm text-sand-800">{{ $report->municipality }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-sand-500 uppercase">Reporting Period</dt>
                <dd class="mt-1 text-sm text-sand-800">{{ $report->period_start->format('M j, Y') }} – {{ $report->period_end->format('M j, Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-sand-500 uppercase">Total Arrivals</dt>
                <dd class="mt-1 text-sm text-sand-800">{{ number_format($report->total_arrivals) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-sand-500 uppercase">Submitted</dt>
                <dd class="mt-1 text-sm text-sand-800">{{ $report->submitter->name ?? '—' }} · {{ $report->created_at->format('M j, Y g:i A') }}</dd>
            </div>
            @if ($report->reviewed_at)
                <div>
                    <dt class="text-xs font-semibold text-sand-500 uppercase">Reviewed</dt>
                    <dd class="mt-1 text-sm text-sand-800">{{ $report->reviewer->name ?? '—' }} · {{ $report->reviewed_at->format('M j, Y g:i A') }}</dd>
                </div>
            @endif
        </dl>

        @if ($report->remarks)
            <div class="mt-5 rounded-md border border-sand-200 bg-sand-50 p-4">
                <p class="text-xs font-semibold text-sand-500 uppercase">PTO Remarks</p>
                <p class="mt-1.5 text-sm leading-relaxed text-sand-800">{{ $report->remarks }}</p>
            </div>
        @endif
    </div>

    @if ($isApproved)
        <div class="mt-6 flex items-center gap-2 rounded-md border border-success/20 bg-success-bg px-4 py-3 text-sm font-semibold text-success">
            <i class="ti ti-circle-check" aria-hidden="true"></i>
            This report has been approved and is now read-only.
        </div>
    @else
        <div class="mt-6 flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('pto.municipalReports.approve', $report) }}">
                @csrf
                @method('PATCH')
                <button
                    type="button"
                    data-confirm-trigger
                    data-confirm-title="Approve this report?"
                    data-confirm-message="{{ $report->municipality }}'s report for {{ $report->period_start->format('M j') }} – {{ $report->period_end->format('M j, Y') }} will be marked Approved."
                    data-confirm-label="Approve"
                    class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900"
                >
                    <i class="ti ti-check" aria-hidden="true"></i>
                    Approve
                </button>
            </form>

            <button type="button" data-modal-open="return-report-modal" class="inline-flex items-center gap-2 rounded-sm border border-danger/30 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-danger hover:bg-danger-bg">
                <i class="ti ti-arrow-back-up" aria-hidden="true"></i>
                Return for Revision
            </button>
        </div>

        <x-dashboard.modal id="return-report-modal" title="Return for Revision">
            <form id="return-report-form" method="POST" action="{{ route('pto.municipalReports.return', $report) }}" class="flex flex-col gap-4">
                @csrf
                @method('PATCH')
                <p class="text-sm text-sand-600">Explain what needs to be corrected. The LGU Tourism Admin will see these remarks on their next visit.</p>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-sand-700">Remarks <span class="text-danger" aria-hidden="true">*</span></label>
                    <textarea name="remarks" rows="4" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>
                    @error('remarks')
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </form>

            <x-slot:footer>
                <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">Cancel</button>
                <button type="submit" form="return-report-form" class="rounded-sm bg-danger px-4 py-2 text-sm font-semibold text-sand-0 hover:opacity-90">Return Report</button>
            </x-slot:footer>
        </x-dashboard.modal>
    @endif
</x-layouts.dashboard>
