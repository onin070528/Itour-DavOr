{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : PTO Photos page — "All photos" (upload for any establishment) and "Waiting for approval" (the merged former Photo Approvals queue, with a municipality filter) in one page.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
@php
    $blnApprovalTabActive = request('tab') === 'approval';
@endphp
<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header title="Photos" description="Upload for any establishment province-wide, or review photos waiting for your decision." />

    <div data-tabs class="mt-6 flex items-center gap-1 border-b border-sand-200">
        <button type="button" data-tab-target="all" aria-selected="{{ $blnApprovalTabActive ? 'false' : 'true' }}" @class(['px-3 py-2.5 text-sm font-semibold border-b-2', 'border-primary-700 text-primary-700' => ! $blnApprovalTabActive, 'border-transparent text-sand-500' => $blnApprovalTabActive])>
            All photos
        </button>
        <button type="button" data-tab-target="approval" aria-selected="{{ $blnApprovalTabActive ? 'true' : 'false' }}" @class(['px-3 py-2.5 text-sm font-semibold border-b-2', 'border-primary-700 text-primary-700' => $blnApprovalTabActive, 'border-transparent text-sand-500' => ! $blnApprovalTabActive])>
            Waiting for approval
            @if ($cards->isNotEmpty())
                <span class="ml-1 rounded-full bg-accent-500 px-1.5 py-0.5 text-[10px] font-bold text-sand-0">{{ $cards->count() }}</span>
            @endif
        </button>
    </div>

    <div data-tab-panel="all" class="mt-6 max-w-lg rounded-md border border-sand-200 bg-sand-0 p-5 {{ $blnApprovalTabActive ? 'hidden' : '' }}">
        <form method="POST" action="{{ route('pto.images.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
            @csrf

            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Establishment <span class="text-danger" aria-hidden="true">*</span></label>
                <select name="listing_id" required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                    @foreach ($listings as $listing)
                        <option value="{{ $listing->id }}">{{ $listing->name }}</option>
                    @endforeach
                </select>
                @error('listing_id')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Photos <span class="text-danger" aria-hidden="true">*</span></label>
                <input name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
                @error('photos')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
                @error('photos.0')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold text-sand-700">Credit (optional)</label>
                <input name="credit" type="text" maxlength="255" placeholder="e.g. Photo by Juan Dela Cruz" class="w-full rounded-sm border border-sand-300 px-3 py-2 text-sm">
            </div>

            <label class="flex items-start gap-2">
                <input type="checkbox" name="ownership_declared" value="1" required class="mt-0.5 h-4 w-4 rounded border-sand-300 text-primary-700 focus:ring-primary-500">
                <span class="text-sm text-sand-800">I have permission to use this photo.</span>
            </label>
            @error('ownership_declared')
                <p class="text-xs text-danger">{{ $message }}</p>
            @enderror

            <button type="submit" class="rounded-sm bg-primary-700 px-4 py-2.5 text-sm font-semibold text-sand-0 hover:bg-primary-900">
                Upload
            </button>
        </form>
    </div>

    <div data-tab-panel="approval" class="mt-6 {{ $blnApprovalTabActive ? '' : 'hidden' }}">
        <form method="GET" action="{{ route('pto.images.index') }}" class="flex items-center gap-2">
            <input type="hidden" name="tab" value="approval">
            <select name="municipality" onchange="this.form.submit()" class="rounded-sm border border-sand-300 bg-sand-0 px-3 py-2 text-sm text-sand-700">
                <option value="">All Municipalities</option>
                @foreach ($municipalities as $municipality)
                    <option value="{{ $municipality->id }}" @selected($selectedMunicipalityId === $municipality->id)>{{ $municipality->name }}</option>
                @endforeach
            </select>
        </form>

        <div class="mt-4 flex flex-col gap-4">
            @forelse ($cards as $card)
                <x-dashboard.photo-approval-card :card="$card" approve-all-route-name="pto.images.approveBatch" return-batch-route-name="pto.images.returnBatch" />
            @empty
                <x-dashboard.empty-state icon="ti-photo-check" title="Nothing waiting for approval" description="Photos LGUs upload on behalf of establishments will show up here." />
            @endforelse
        </div>
    </div>
</x-layouts.dashboard>
