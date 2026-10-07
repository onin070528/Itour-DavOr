{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: The action buttons for one reporting month on the Establishment
    Monthly Reports page — shared by the Monthly Records table and the
    Overview "Needs Your Attention" list so both always offer the same
    action for the same status:
      Not Submitted   -> Prepare Report (Save Draft for that month)
      Draft           -> Continue & Submit
      For Correction  -> View Remarks (modal) / Correct Report
      Submitted / For Review -> View (modal) / Report Preview
      Verified        -> View (modal) / Report Preview
    View and View Remarks open the report's quick-view modal (see
    partials/report-modal) so the user stays on the same tab.
    Expects $row: array{month: CarbonImmutable, report: ?MonthlyArrivalReport, isCurrentMonth: bool}
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $report = $row['report'];
    $status = $report?->status;
    $primaryClasses = 'rounded-sm bg-primary-700 px-3 py-1.5 text-xs font-semibold text-sand-0 hover:bg-primary-900';
    $secondaryClasses = 'rounded-sm border border-sand-300 px-3 py-1.5 text-xs font-semibold text-sand-800 hover:border-primary-300';
@endphp

<div class="flex flex-wrap items-center justify-end gap-2">
    @if (! $report)
        <form method="POST" action="{{ route('establishment.arrivals.monthly.draft') }}">
            @csrf
            <input type="hidden" name="period_month" value="{{ $row['month']->format('Y-m') }}">
            <button type="submit" class="{{ $primaryClasses }}">Prepare Report</button>
        </form>
    @elseif ($status === \App\Enums\MonthlyReportStatus::Draft)
        <a href="{{ route('establishment.arrivals.monthly.show', $report) }}" class="{{ $primaryClasses }}">Continue &amp; Submit</a>
    @elseif ($status === \App\Enums\MonthlyReportStatus::ForCorrection)
        <button type="button" data-modal-open="report-modal-{{ $report->id }}" class="{{ $secondaryClasses }}">View Remarks</button>
        <a href="{{ route('establishment.arrivals.monthly.show', $report) }}" class="{{ $primaryClasses }}">Correct Report</a>
    @else
        <button type="button" data-modal-open="report-modal-{{ $report->id }}" class="{{ $secondaryClasses }}">View</button>
        <a href="{{ route('establishment.arrivals.monthly.show', [$report, 'view' => 'a4']) }}" class="{{ $secondaryClasses }}">Report Preview</a>
    @endif
</div>
