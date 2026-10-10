{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: Pop-up reminder that the establishment's monthly report is almost due, due, or overdue.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
@props(['user'])

@php
    $arrReminder = null;
    $strSeenKey = null;

    if ($user->usr_role === \App\Enums\UserRole::Establishment && $user->lst_id !== null) {
        $objListing = $user->establishment;
        $arrReminder = $objListing ? \App\Support\MonthlyReportReminder::forListing($objListing) : null;

        // Shown once per session per stage per day, so it never nags on every page load.
        $strSeenKey = $arrReminder ? 'monthly_report_reminder.'.$arrReminder['stage'].'.'.now()->toDateString() : null;

        if ($strSeenKey && session()->has($strSeenKey)) {
            $arrReminder = null;
        } elseif ($strSeenKey) {
            session()->put($strSeenKey, true);
        }
    }
@endphp

@if ($arrReminder)
    @php
        $strMonth = $arrReminder['period']->format('F Y');
        $strDue = $arrReminder['due']->format('F j');
        $blnUpcoming = $arrReminder['stage'] === 'upcoming';
        $intDays = $arrReminder['daysLeft'];
    @endphp
    <div id="monthly-report-reminder-modal" data-modal class="fixed inset-0 z-50">
        <div data-modal-backdrop class="flex min-h-full items-center justify-center bg-sand-900/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-sand-0 p-5 shadow-md" role="dialog" aria-labelledby="monthly-report-reminder-title">
                <div class="flex items-start gap-3">
                    <span @class(['grid h-10 w-10 shrink-0 place-items-center rounded-full text-xl', 'bg-warning/15 text-warning' => $blnUpcoming, 'bg-danger/15 text-danger' => ! $blnUpcoming])>
                        <i class="ti {{ $blnUpcoming ? 'ti-bell-ringing' : 'ti-alert-triangle' }}" aria-hidden="true"></i>
                    </span>
                    <div>
                        <p id="monthly-report-reminder-title" class="font-display text-base font-bold text-sand-900">
                            @if ($blnUpcoming)
                                Your {{ $strMonth }} report is due in {{ $intDays }} {{ \Illuminate\Support\Str::plural('day', $intDays) }}
                            @elseif ($arrReminder['stage'] === 'due')
                                Your {{ $strMonth }} report is due today
                            @else
                                Your {{ $strMonth }} report is overdue
                            @endif
                        </p>
                        <p class="mt-1.5 text-sm text-sand-600">
                            @if ($blnUpcoming)
                                Please send your {{ $strMonth }} tourist-arrival record to your LGU by {{ $strDue }}. Make sure every guest is already logged under Record Arrival.
                            @else
                                Your {{ $strMonth }} tourist-arrival report was due on {{ $strDue }} and has not been sent to your LGU yet. Please submit it now so it can be reviewed and forwarded to the PTO.
                            @endif
                        </p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                        Remind me later
                    </button>
                    <a href="{{ route('establishment.arrivals.monthly') }}" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                        <i class="ti ti-send" aria-hidden="true"></i>
                        Send to LGU
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script>document.body.classList.add('overflow-hidden');</script>
@endif
