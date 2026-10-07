{{--
    iTOUR — Davao Oriental Tourism Information System

    Purpose: LGU correction form for a monthly arrival report.
    Programmer/s: iTOUR Development Team
    Copyright (c) 2026 iTOUR Development Team. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        :title="'Correct Report — '.$report->listing->lst_name"
        :description="$report->mar_period_month->format('F Y').' — adjust the encoded totals and record why.'"
    >
        <x-slot:actions>
            <a href="{{ route('lgu.monthlyReports.show', $report) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Report
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    @if ($report->mar_status->value === 'Verified')
        <div class="mt-6 flex items-center gap-2 rounded-md border border-warning/20 bg-warning-bg px-4 py-3 text-sm font-semibold text-warning">
            <i class="ti ti-alert-triangle" aria-hidden="true"></i>
            This report is already Verified — saving a correction will revert it to For Review so it can be checked again.
        </div>
    @endif

    <form method="POST" action="{{ route('lgu.monthlyReports.update', $report) }}" class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        @csrf
        @method('PUT')

        <p class="text-sm text-sand-600">Update the visitor breakdown to match the correct figures.</p>

        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                'party_male' => 'Male',
                'party_female' => 'Female',
                'party_adults' => 'Adults',
                'party_children' => 'Children',
                'party_seniors' => 'Seniors',
                'party_local' => 'Local',
                'party_foreign' => 'Foreign',
            ] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="mb-1 block text-xs font-semibold text-sand-700">{{ $label }}</label>
                    <input
                        id="{{ $field }}"
                        type="number"
                        name="{{ $field }}"
                        min="0"
                        value="{{ old($field, $report->$field) }}"
                        required
                        class="w-full rounded-sm border border-sand-300 px-3 py-2.5 text-sm text-sand-900"
                    >
                    @error($field)
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        <div class="mt-5">
            <label for="reason" class="mb-1 block text-xs font-semibold text-sand-700">Reason for Correction <span class="text-danger" aria-hidden="true">*</span></label>
            <textarea id="reason" name="reason" rows="3" required class="w-full rounded-sm border border-sand-300 px-3 py-2.5 text-sm text-sand-900">{{ old('reason') }}</textarea>
            @error('reason')
                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div class="mt-6 flex items-center gap-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                <i class="ti ti-device-floppy" aria-hidden="true"></i>
                Save Correction
            </button>
        </div>
    </form>
</x-layouts.dashboard>
