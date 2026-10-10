{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : LGU establishment details — information, photos, iTOUR adoption (reporting method, account, QR), and destination listing state.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('lgu.settings')">
    <x-dashboard.page-header
        :title="$listing->lst_name"
        description="{{ $listing->categoryName() }}{{ $listing->lst_type ? ' · '.$listing->lst_type : '' }} · {{ $listing->lst_barangay ? $listing->lst_barangay.', ' : '' }}{{ $listing->lst_municipality }}"
    >
        <x-slot:actions>
            <a href="{{ route('lgu.directory.establishments') }}" class="btn-secondary">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                Back to Establishments
            </a>
            <a href="{{ route('lgu.directory.establishments.edit', $listing) }}" class="btn-primary">
                <i class="ti ti-edit" aria-hidden="true"></i>
                Edit Establishment
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="flex flex-col gap-5 lg:col-span-2">
            @include('lgu.directory.establishments.partials.details')
            @include('lgu.directory.establishments.partials.photo-summary')
        </div>

        <div class="flex flex-col gap-5">
            @include('lgu.directory.establishments.partials.adoption-panel')
            @include('lgu.directory.establishments.partials.destination-panel')
        </div>
    </div>

    @if (session('accountCreated'))
        <x-dashboard.account-created-modal :account="session('accountCreated')" :resend-url="route('lgu.users.resendWelcomeEmail')" />
        @vite(['resources/js/user_account.js'])
    @endif
</x-layouts.dashboard>
