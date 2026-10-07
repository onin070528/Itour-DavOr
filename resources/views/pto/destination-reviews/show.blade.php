{{--
    System     : iTOUR - Integrated Tourism Information and Monitoring System
    Purpose    : PTO destination listing review screen — name, description, photos, location, municipality, and tourism
                 details, the proposed changes for a Published listing, and Approve & Publish / Return for Correction.
    Programmer : <name(s)>
    Copyright  : 2026 University of Mindanao. All rights reserved.
--}}
@php
    $blnIsChangeRequest = $listing->hasPendingChanges();
@endphp

<x-layouts.dashboard :user="$user" :nav-sections="$navSections" :page-title="$pageTitle" account-heading="System" :settings-href="route('pto.settings')">
    <x-dashboard.page-header
        :title="'Review '.$listing->name"
        :description="$blnIsChangeRequest
            ? 'The '.$listing->municipality.' LGU changed this published listing. The published version stays live until you approve the changes.'
            : 'Request to feature this establishment as a tourist destination, submitted by the '.$listing->municipality.' LGU.'"
    >
        <x-slot:actions>
            <a href="{{ route('pto.destinationReviews.index') }}" class="btn-secondary">
                <i class="ti ti-arrow-left" aria-hidden="true"></i>
                All reviews
            </a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <div class="mt-6 flex flex-wrap items-center gap-3 rounded-md border border-sand-200 bg-sand-0 px-4 py-3 text-sm">
        <span class="font-semibold text-sand-900">{{ $listing->destinationListingLabel() }}</span>
        @if ($lastSubmission)
            <span class="text-xs text-sand-500">Last submitted by {{ $lastSubmission->user->name ?? '—' }} · {{ $lastSubmission->created_at->format('M j, Y g:i A') }}</span>
        @endif
    </div>

    @if ($listing->lst_review_remarks && ! $blnIsAwaitingDecision)
        <div class="mt-4 rounded-md border border-warning/30 bg-warning-bg px-4 py-3 text-sm text-warning">
            <span class="font-semibold">Your remarks:</span> {{ $listing->lst_review_remarks }}
        </div>
    @endif

    @if ($changeRows->isNotEmpty())
        <section class="mt-6 overflow-x-auto rounded-md border border-sand-200 bg-sand-0 shadow-sm">
            <h2 class="border-b border-sand-200 px-5 py-4 font-display text-base font-bold text-sand-900">Proposed changes</h2>
            <table class="w-full min-w-[640px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-sand-200 bg-sand-50 text-left text-xs font-semibold tracking-wide text-sand-500 uppercase">
                        <th class="px-4 py-3">Field</th>
                        <th class="px-4 py-3">Live now</th>
                        <th class="px-4 py-3">Proposed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sand-100">
                    @foreach ($changeRows as $arrRow)
                        <tr>
                            <td class="px-4 py-3 font-medium text-sand-900">{{ $arrRow['label'] }}</td>
                            <td class="px-4 py-3 whitespace-pre-line text-sand-600">{{ $arrRow['current'] }}</td>
                            <td class="px-4 py-3 whitespace-pre-line font-semibold text-sand-900">{{ $arrRow['proposed'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-[1fr_320px]">
        <section class="dashboard-panel">
            <h2 class="dashboard-panel-title">{{ $blnIsChangeRequest ? 'Published listing' : 'Listing details' }}</h2>
            <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="detail-term">Name</dt>
                    <dd class="detail-value">{{ $listing->name }}</dd>
                </div>
                <div>
                    <dt class="detail-term">Category / Type</dt>
                    <dd class="detail-value">{{ $listing->categoryName() }}{{ $listing->type ? ' · '.$listing->type : '' }}</dd>
                </div>
                <div>
                    <dt class="detail-term">Municipality</dt>
                    <dd class="detail-value">{{ $listing->municipality }}</dd>
                </div>
                <div>
                    <dt class="detail-term">Barangay / Address</dt>
                    <dd class="detail-value">{{ $listing->barangay ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="detail-term">Map location</dt>
                    <dd class="detail-value">{{ $listing->lat !== null && $listing->lng !== null ? $listing->lat.', '.$listing->lng : 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="detail-term">Operating hours</dt>
                    <dd class="detail-value">{{ $listing->hours ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="detail-term">Contact</dt>
                    <dd class="detail-value">{{ collect([$listing->contact_phone, $listing->email])->filter()->implode(' · ') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="detail-term">Website / Social media</dt>
                    <dd class="detail-value">{{ $listing->website ?: '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="detail-term">Description</dt>
                    <dd class="detail-value whitespace-pre-line">{{ $listing->description ?: '—' }}</dd>
                </div>
            </dl>
        </section>

        <aside class="flex flex-col gap-5">
            @if ($blnIsAwaitingDecision)
                <section class="dashboard-panel">
                    <h2 class="dashboard-panel-title">Decision</h2>
                    <div class="mt-4 flex flex-col gap-2">
                        <form method="POST" action="{{ route('pto.directory.publish', $listing) }}">
                            @csrf
                            @method('PATCH')
                            <button
                                type="button"
                                data-confirm-trigger
                                data-confirm-title="{{ $blnIsChangeRequest ? 'Approve the changes to '.$listing->name.'?' : 'Approve & publish '.$listing->name.'?' }}"
                                data-confirm-message="{{ $blnIsChangeRequest ? 'The proposed changes replace the live content on the public site immediately.' : 'This makes the listing publicly visible as a tourist destination immediately.' }}"
                                data-confirm-label="Approve &amp; Publish"
                                data-confirm-tone="success"
                                class="btn-primary w-full justify-center"
                            >
                                <i class="ti ti-circle-check" aria-hidden="true"></i>
                                Approve &amp; Publish
                            </button>
                        </form>
                        <button type="button" data-modal-open="return-for-correction-modal" class="btn-secondary w-full justify-center border-danger/30 text-danger hover:bg-danger-bg">
                            <i class="ti ti-arrow-back-up" aria-hidden="true"></i>
                            Return for Correction
                        </button>
                    </div>
                </section>
            @endif

            <section class="dashboard-panel">
                <h2 class="dashboard-panel-title">Photos</h2>
                @if ($publishedImages->isEmpty())
                    <p class="mt-3 text-sm text-sand-500">No approved photos yet.</p>
                @else
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        @foreach ($publishedImages as $objImage)
                            <img src="{{ route('establishmentImages.file', [$objImage->img_id, 'thumbnail']) }}" alt="{{ $objImage->img_alt_text ?? '' }}" loading="lazy" class="h-24 w-full rounded-sm object-cover">
                        @endforeach
                    </div>
                @endif
                @if ($pendingImageCount > 0)
                    <p class="mt-3 text-xs text-sand-500">{{ $pendingImageCount }} more {{ \Illuminate\Support\Str::plural('photo', $pendingImageCount) }} waiting for photo approval.</p>
                @endif
                <a href="{{ route('pto.images.manage', $listing) }}" class="mt-3 inline-flex text-xs font-semibold text-primary-700 hover:text-primary-900">Manage photos</a>
            </section>

            @if ($history->isNotEmpty())
                <section class="dashboard-panel">
                    <h2 class="dashboard-panel-title">History</h2>
                    <ul class="mt-3 flex flex-col gap-2 text-xs text-sand-600">
                        @foreach ($history as $objEntry)
                            <li>
                                <span class="font-semibold text-sand-800">{{ ucfirst($objEntry->action) }}</span>
                                · {{ $objEntry->user->name ?? '—' }} · {{ $objEntry->created_at->format('M j, Y g:i A') }}
                                @if ($objEntry->reason)
                                    <span class="block text-sand-500">{{ $objEntry->reason }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>

    @if ($blnIsAwaitingDecision)
        <x-dashboard.modal id="return-for-correction-modal" title="Return for Correction" :open="$errors->has('reason')">
            <form id="return-for-correction-form" method="POST" action="{{ route('pto.directory.returnToLgu', $listing) }}" class="flex flex-col gap-3">
                @csrf
                @method('PATCH')
                <p class="text-sm text-sand-700">The {{ $listing->municipality }} LGU sees these remarks, corrects the listing, and resubmits it.{{ $blnIsChangeRequest ? ' The published version stays live meanwhile.' : '' }}</p>
                <label for="return-remarks" class="form-label">Remarks <span class="text-danger" aria-hidden="true">*</span></label>
                <textarea id="return-remarks" name="reason" rows="4" required maxlength="500" placeholder="What needs to be corrected?" class="form-input">{{ old('reason') }}</textarea>
                @error('reason') <p class="form-error">{{ $message }}</p> @enderror
            </form>
            <x-slot:footer>
                <button type="button" data-modal-close class="btn-secondary">Cancel</button>
                <button type="submit" form="return-for-correction-form" class="inline-flex items-center gap-2 rounded-sm bg-danger px-4 py-2.5 text-sm font-semibold text-sand-0 hover:opacity-90">Return for Correction</button>
            </x-slot:footer>
        </x-dashboard.modal>
    @endif
</x-layouts.dashboard>
