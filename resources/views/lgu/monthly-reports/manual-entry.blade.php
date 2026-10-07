{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : Manual Entry form — encode one Manual/Paper establishment's paper monthly report (Save Draft, then Preview and Submit).
                 Fields come from App\Support\ManualReportForm (provisional until the official PTO paper form is provided).
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Manual Entry — {{ $listing->lst_name }}"
        description="Encode {{ $month->format('F Y') }}'s paper monthly report into iTOUR."
    >
        <x-slot:actions>
            <a href="{{ route('lgu.monthlyReports.manualEntry.index', ['period' => $month->format('Y-m')]) }}" class="btn-secondary">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Manual Entry
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <form method="POST" action="{{ route('lgu.monthlyReports.manualEntry.store', $listing) }}" class="dashboard-panel mt-6">
        @csrf
        <input type="hidden" name="period_month" value="{{ $month->format('Y-m') }}">

        <dl class="grid grid-cols-1 gap-3 rounded-sm border border-sand-200 bg-sand-50 p-3 text-sm sm:grid-cols-4">
            <div>
                <dt class="detail-term">Establishment</dt>
                <dd class="detail-value">{{ $listing->lst_name }}</dd>
            </div>
            <div>
                <dt class="detail-term">Reporting month</dt>
                <dd class="detail-value">{{ $month->format('F Y') }}</dd>
            </div>
            <div>
                <dt class="detail-term">Source</dt>
                <dd class="detail-value">Manual / Paper</dd>
            </div>
            <div>
                <dt class="detail-term">Status</dt>
                <dd class="detail-value">{{ $report ? $report->mar_status->label() : 'Not encoded yet' }}</dd>
            </div>
        </dl>

        @if ($blnIsProvisional)
            <p class="mt-4 flex items-start gap-2 rounded-sm border border-warning/30 bg-warning-bg px-3 py-2 text-xs text-warning">
                <i class="ti ti-info-circle mt-0.5" aria-hidden="true"></i>
                <span>Provisional fields: these follow the current iTOUR monthly report until the official PTO paper report form is confirmed.</span>
            </p>
        @endif

        <p class="mt-4 text-sm text-sand-600">Enter the figures exactly as they appear on the paper report. Saving keeps it as a draft — nothing is sent for review until you preview and submit it. The total is calculated for you (Male + Female).</p>

        <div class="mt-5 flex flex-col gap-5">
            @foreach ($fieldGroups as $strGroup => $arrFields)
                <fieldset>
                    <legend class="font-display text-sm font-bold text-sand-900">{{ $strGroup }}</legend>
                    <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($arrFields as $strField => $strLabel)
                            <div>
                                <label for="{{ $strField }}" class="form-label">{{ $strLabel }} <span class="text-danger" aria-hidden="true">*</span></label>
                                <input
                                    id="{{ $strField }}"
                                    type="number"
                                    name="{{ $strField }}"
                                    min="0"
                                    step="1"
                                    inputmode="numeric"
                                    value="{{ old($strField, $report?->{'mar_'.$strField} ?? 0) }}"
                                    required
                                    class="form-input"
                                >
                                @error($strField) <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-end gap-2 border-t border-sand-200 pt-4">
            <button type="submit" name="intent" value="draft" class="btn-secondary">
                <i class="ti ti-device-floppy" aria-hidden="true"></i>
                Save Draft
            </button>
            <button type="submit" name="intent" value="preview" class="btn-primary">
                <i class="ti ti-file-description" aria-hidden="true"></i>
                Save &amp; Preview
            </button>
        </div>
    </form>
</x-layouts.dashboard>
