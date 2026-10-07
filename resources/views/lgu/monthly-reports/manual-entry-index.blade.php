{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : Tourism Reports -> Manual Entry — the month's Manual/Paper establishments and each one's paper report:
                 Encode -> Save Draft -> Preview -> Submit, then the same review and verification as an online report.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
@php
    $period = $month->format('Y-m');
    $statusTone = fn (string $status) => match ($status) {
        'Verified' => 'success',
        'For Review' => 'warning',
        'Submitted' => 'info',
        'Draft' => 'neutral',
        'For Correction' => 'danger',
        default => 'danger',
    };
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Manual Entry"
        description="Encode the paper monthly reports of Manual/Paper establishments in {{ $municipality }}. Each report is saved as a draft first, previewed, then submitted for the same review and verification as an online report."
    />

    <form method="GET" action="{{ route('lgu.monthlyReports.manualEntry.index') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-md border border-sand-200 bg-sand-0 p-4">
        <div>
            <label for="manual-entry-period" class="form-label">Reporting month</label>
            <select id="manual-entry-period" name="period" data-auto-submit class="form-input">
                @foreach ($monthOptions as $option)
                    <option value="{{ $option->format('Y-m') }}" @selected($option->isSameMonth($month))>{{ $option->format('F Y') }}</option>
                @endforeach
            </select>
        </div>
        <noscript><button type="submit" class="btn-secondary">Show</button></noscript>
    </form>

    @if ($onlineCount > 0)
        <p class="mt-4 flex items-center gap-2 text-xs text-sand-500">
            <i class="ti ti-device-laptop" aria-hidden="true"></i>
            {{ $onlineCount }} Online iTOUR {{ $onlineCount === 1 ? 'establishment submits its own report and is' : 'establishments submit their own reports and are' }} not listed here.
            <a href="{{ route('lgu.monthlyReports.index', ['period' => $period]) }}" class="font-semibold text-primary-700 hover:text-primary-900">See all in Monthly Reports</a>
        </p>
    @endif

    <section class="mt-4 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
        <h2 class="border-b border-sand-200 px-5 py-4 font-display text-base font-bold text-sand-900">Manual/Paper establishments · {{ $month->format('F Y') }}</h2>
        <table class="w-full min-w-[720px] border-collapse text-sm">
            <thead>
                <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                    <th class="px-4 py-3">Establishment</th>
                    <th class="px-4 py-3">Paper report</th>
                    <th class="px-4 py-3">Submitted</th>
                    <th class="px-4 py-3 text-right">Arrival total</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sand-100">
                @forelse ($rows as $row)
                    @php
                        $report = $row['report'];
                        $strStatus = $report ? $row['status'] : ($row['draftInProgress'] ? 'Online draft' : 'Not encoded');
                    @endphp
                    <tr class="hover:bg-sand-50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-sand-900">{{ $row['listing']->name }}</p>
                            <p class="text-xs text-sand-500">{{ $row['listing']->categoryName() }}</p>
                        </td>
                        <td class="px-4 py-3"><x-dashboard.status-badge :tone="$statusTone($strStatus)">{{ $strStatus }}</x-dashboard.status-badge></td>
                        <td class="px-4 py-3 text-sand-700">
                            @if ($report?->submitted_at)
                                {{ $report->submitted_at->format('M j, Y') }}
                                <span class="block text-xs text-sand-500">{{ $report->submitter->name ?? '—' }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-sand-800">{{ $report ? number_format($report->total_visitors) : '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($row['draftInProgress'])
                                <span class="text-xs text-sand-500">Draft from when it reported online</span>
                            @elseif (! $report)
                                <a href="{{ route('lgu.monthlyReports.manualEntry', ['listing' => $row['listing'], 'period' => $period]) }}" class="btn-primary btn-small">
                                    <i class="ti ti-pencil" aria-hidden="true"></i> Encode
                                </a>
                            @elseif ($report->status === \App\Enums\MonthlyReportStatus::Draft)
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('lgu.monthlyReports.manualEntry', ['listing' => $row['listing'], 'period' => $period]) }}" class="btn-secondary btn-small">Continue draft</a>
                                    <a href="{{ route('lgu.monthlyReports.show', ['monthlyArrivalReport' => $report, 'view' => 'a4']) }}" class="btn-primary btn-small">Preview &amp; submit</a>
                                </div>
                            @else
                                <a href="{{ route('lgu.monthlyReports.show', $report) }}" class="btn-secondary btn-small">View</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sand-500">
                            No Manual/Paper establishments in {{ $municipality }}. Establishments that report on paper are set under Tourism Directory → Establishments.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-layouts.dashboard>
