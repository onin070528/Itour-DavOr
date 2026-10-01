<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        title="Manual Entry — {{ $listing->name }}"
        description="Encode {{ $month->format('F Y') }}'s physical arrival report into iTOUR."
    >
        <x-slot:actions>
            <a href="{{ route('lgu.monthlyReports.index', ['period' => $month->format('Y-m')]) }}" class="inline-flex items-center gap-2 rounded-sm border border-sand-300 bg-sand-0 px-4 py-2.5 text-sm font-semibold text-sand-800 hover:border-primary-300">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Monthly Reports
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <form method="POST" action="{{ route('lgu.monthlyReports.manualEntry.store', $listing) }}" class="mt-6 rounded-md border border-sand-200 bg-sand-0 p-5">
        @csrf
        <input type="hidden" name="period_month" value="{{ $month->format('Y-m') }}">

        <p class="text-sm text-sand-600">Enter the visitor breakdown exactly as it appears on the physical report. Review the encoded numbers against the paper afterward, then mark the report Verified from its review page.</p>

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
                        value="{{ old($field, 0) }}"
                        required
                        class="w-full rounded-sm border border-sand-300 px-3 py-2.5 text-sm text-sand-900"
                    >
                    @error($field)
                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex items-center gap-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                <i class="ti ti-device-floppy" aria-hidden="true"></i>
                Save &amp; Mark For Review
            </button>
        </div>
    </form>
</x-layouts.dashboard>
