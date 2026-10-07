{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: One establishment monthly report — both the review step before
    sending (Save Draft -> Review -> Submit to LGU) and the permanent record
    of a past report. Two tabs: Report Details and Report Preview (the
    official A4 document, with Print / Download PDF). Read-only: the figures are the
    saved, server-computed totals. Submit to LGU is offered only while the
    report is a Draft or returned For Correction; verified reports cannot
    be edited.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@php
    $isForCorrection = $report->mar_status === \App\Enums\MonthlyReportStatus::ForCorrection;
    $periodValue = $report->mar_period_month->format('Y-m');
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('establishment.settings')">
    <x-dashboard.page-header
        :title="'Monthly Report — '.$report->mar_period_month->format('F Y')"
        :description="$canSubmit ? 'Check the figures below. If they are right, press Submit to LGU at the bottom of the page.' : 'Your saved report record. It can no longer be changed from your side.'"
    >
        <x-slot:actions>
            <a href="{{ route('establishment.arrivals.monthly', ['year' => $report->mar_period_month->year, 'tab' => 'records']) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Monthly Records
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    @if ($isForCorrection)
        <div id="correction-remarks" class="mt-6 rounded-md border border-danger/30 bg-danger-bg px-4 py-3 text-sm text-danger">
            <p class="flex items-center gap-2 font-semibold"><i class="ti ti-alert-triangle" aria-hidden="true"></i> Returned by the LGU for correction</p>
            <p class="mt-1">What to fix: {{ $report->mar_remarks ?: '—' }}</p>
            <p class="mt-2 text-xs">To correct it: record any missing guests, press <span class="font-semibold">Update Totals</span>, check the report, then press <span class="font-semibold">Submit to LGU</span> again.</p>
        </div>
    @endif

    <x-dashboard.tabs sync="view" panel-host="#monthly-report-panels" :tabs="[
        ['label' => 'Report Details', 'panel' => 'details', 'active' => $activeView === 'details', 'icon' => 'ti-list-details'],
        ['label' => 'Report Preview', 'panel' => 'a4', 'active' => $activeView === 'a4', 'icon' => 'ti-file-description'],
    ]" />

    <div id="monthly-report-panels">
    <div data-tab-panel="details" @class(['hidden' => $activeView !== 'details'])>
    <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs font-semibold text-sand-500 uppercase">Establishment</dt>
                <dd class="mt-1 text-sm text-sand-800">
                    {{ $report->listing->name ?? $establishmentName }}
                    <span class="block text-xs text-sand-500">{{ $report->listing->categoryRecord?->cat_name ?? 'Uncategorized' }} · {{ trim(($report->listing->barangay ?? '').', '.($report->listing->municipality ?? ''), ', ') }}</span>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-sand-500 uppercase">Reporting Month</dt>
                <dd class="mt-1 text-sm text-sand-800">{{ $report->mar_period_month->format('F Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-sand-500 uppercase">Report Source</dt>
                <dd class="mt-1 text-sm text-sand-800">{{ $report->mar_submission_source->label() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-sand-500 uppercase">Status</dt>
                <dd class="mt-1"><x-dashboard.status-badge :tone="$report->mar_status->badgeTone()">{{ $report->mar_status->label() }}</x-dashboard.status-badge></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-sand-500 uppercase">Submitted</dt>
                <dd class="mt-1 text-sm text-sand-800">
                    @if ($report->mar_submitted_at)
                        {{ $report->submitter->name ?? '—' }} · {{ $report->mar_submitted_at->format('M j, Y g:i A') }}
                    @else
                        Not yet submitted
                    @endif
                </dd>
            </div>
            @if ($report->mar_verified_at)
                <div>
                    <dt class="text-xs font-semibold text-sand-500 uppercase">Verified</dt>
                    <dd class="mt-1 text-sm text-sand-800">{{ $report->verifier->name ?? '—' }} · {{ $report->mar_verified_at->format('M j, Y g:i A') }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-xs font-semibold text-sand-500 uppercase">Last Saved</dt>
                <dd class="mt-1 text-sm text-sand-800">{{ $report->mar_updated_at->format('M j, Y g:i A') }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
        <table class="w-full min-w-[640px] border-collapse text-sm">
            <thead>
                <tr class="border-b border-sand-200 bg-sand-50 text-right text-xs font-semibold tracking-wide text-sand-500 uppercase">
                    <th class="px-4 py-3">Male</th>
                    <th class="px-4 py-3">Female</th>
                    <th class="px-4 py-3">Adults</th>
                    <th class="px-4 py-3">Children</th>
                    <th class="px-4 py-3">Seniors</th>
                    <th class="px-4 py-3">Local</th>
                    <th class="px-4 py-3">Foreign</th>
                    <th class="px-4 py-3">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr class="text-right text-sand-800">
                    <td class="px-4 py-3">{{ number_format($report->mar_party_male) }}</td>
                    <td class="px-4 py-3">{{ number_format($report->mar_party_female) }}</td>
                    <td class="px-4 py-3">{{ number_format($report->mar_party_adults) }}</td>
                    <td class="px-4 py-3">{{ number_format($report->mar_party_children) }}</td>
                    <td class="px-4 py-3">{{ number_format($report->mar_party_seniors) }}</td>
                    <td class="px-4 py-3">{{ number_format($report->mar_party_local) }}</td>
                    <td class="px-4 py-3">{{ number_format($report->mar_party_foreign) }}</td>
                    <td class="px-4 py-3 font-bold text-sand-900">{{ number_format($report->mar_total_visitors) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if ($originBreakdown['withinProvince'] > 0 || $originBreakdown['outsideProvince'] > 0 || $originBreakdown['topForeignCountries']->isNotEmpty())
        <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Where Your Guests Came From</h2>
            <dl class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-xs font-semibold text-sand-500 uppercase">Within Davao Oriental</dt>
                    <dd class="mt-1 text-sm text-sand-800">{{ number_format($originBreakdown['withinProvince']) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-sand-500 uppercase">Outside Davao Oriental</dt>
                    <dd class="mt-1 text-sm text-sand-800">{{ number_format($originBreakdown['outsideProvince']) }}</dd>
                </div>
                @if ($originBreakdown['topOriginPlaces']->isNotEmpty())
                    <div>
                        <dt class="text-xs font-semibold text-sand-500 uppercase">Top Provinces</dt>
                        <dd class="mt-1 text-sm text-sand-800">{{ $originBreakdown['topOriginPlaces']->map(fn ($count, $place) => $place.' ('.number_format($count).')')->implode(', ') }}</dd>
                    </div>
                @endif
                @if ($originBreakdown['topForeignCountries']->isNotEmpty())
                    <div>
                        <dt class="text-xs font-semibold text-sand-500 uppercase">Top Countries</dt>
                        <dd class="mt-1 text-sm text-sand-800">{{ $originBreakdown['topForeignCountries']->map(fn ($count, $country) => $country.' ('.number_format($count).')')->implode(', ') }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    @endif

    @if ($report->mar_status === \App\Enums\MonthlyReportStatus::Verified)
        <div class="mt-6 flex items-center gap-2 rounded-md border border-success/20 bg-success-bg px-4 py-3 text-sm font-semibold text-success">
            <i class="ti ti-circle-check" aria-hidden="true"></i>
            Verified by {{ $report->verifier->name ?? 'the LGU' }} on {{ $report->mar_verified_at?->format('F j, Y') }}. This report is final and part of your official record.
        </div>
    @elseif (in_array($report->mar_status, \App\Enums\MonthlyReportStatus::awaitingReview(), true))
        <div class="mt-6 flex items-center gap-2 rounded-md border border-sand-300 bg-sand-100 px-4 py-3 text-sm font-semibold text-sand-700">
            <i class="ti ti-clock" aria-hidden="true"></i>
            Sent to the LGU. They are checking it now — you don't need to do anything.
        </div>
    @endif

    {{-- The guest arrivals this report was added up from. --}}
    <div class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
        <div class="border-b border-sand-200 px-5 py-4">
            <h2 class="font-display text-base font-bold text-sand-900">Guest Arrivals Included ({{ $arrivals->count() }})</h2>
            <p class="text-xs text-sand-500">The recorded arrivals this report's totals were added up from.</p>
        </div>
        @if ($arrivals->isEmpty())
            <p class="px-5 py-4 text-sm text-sand-500">
                {{ $report->mar_submission_source === \App\Enums\ReportSubmissionSource::ManualPaper ? 'This report was encoded by the LGU from a paper report, so it has no individual arrival records.' : 'No guest arrivals were recorded for this month — this is a zero-arrival report.' }}
            </p>
        @else
            <table class="w-full min-w-[560px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Visit Type</th>
                        <th class="px-4 py-3 text-right">Guests</th>
                        <th class="px-4 py-3">Recorded Through</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach ($arrivals as $arrival)
                        <tr>
                            <td class="px-4 py-2.5 text-sand-700">{{ $arrival->arr_date->format('M j, Y') }}</td>
                            <td class="px-4 py-2.5 text-sand-700">{{ $arrival->arr_visit_type ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold text-sand-800">{{ number_format($arrival->arr_party_size) }}</td>
                            <td class="px-4 py-2.5 text-sand-700">{{ $arrival->arr_source?->label() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    </div>

    {{-- Report Preview: the official A4 document, with Print / Download PDF. --}}
    <div data-tab-panel="a4" @class(['hidden' => $activeView !== 'a4'])>
        <x-dashboard.report-preview-frame
            frame-id="report-preview-frame"
            :preview-url="route('establishment.arrivals.monthly.preview', $report)"
            :pdf-url="route('establishment.arrivals.monthly.pdf', $report)"
        />
    </div>
    </div>

    @if ($canSubmit)
        <div class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
            <h2 class="font-display text-base font-bold text-sand-900">Ready to send?</h2>
            <p class="mt-1 text-sm text-sand-600">
                Check the totals and the Report Preview. Missing a guest? Press Record Arrival, then Update Totals. When everything is right, press <span class="font-semibold">Submit to LGU</span>. After that, the report can no longer be changed from your side.
            </p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <a href="{{ route('establishment.arrivals.record') }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                    <i class="ti ti-user-plus" aria-hidden="true"></i>
                    Record Arrival
                </a>

                <form method="POST" action="{{ route('establishment.arrivals.monthly.draft') }}">
                    @csrf
                    <input type="hidden" name="period_month" value="{{ $periodValue }}">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                        <i class="ti ti-refresh" aria-hidden="true"></i>
                        Update Totals
                    </button>
                </form>

                <form method="POST" action="{{ route('establishment.arrivals.monthly.submit') }}">
                    @csrf
                    <input type="hidden" name="period_month" value="{{ $periodValue }}">
                    <button
                        type="button"
                        data-confirm-trigger
                        data-confirm-title="Submit this report to the LGU?"
                        data-confirm-message="Once submitted, this report can no longer be edited from your side. The LGU will review and verify it."
                        data-confirm-label="Submit to LGU"
                        class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900"
                    >
                        <i class="ti ti-send" aria-hidden="true"></i>
                        Submit to LGU
                    </button>
                </form>
            </div>
        </div>
    @endif
</x-layouts.dashboard>
