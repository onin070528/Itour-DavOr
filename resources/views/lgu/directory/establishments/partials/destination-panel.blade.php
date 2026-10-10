{{--
    Destination listing on the details page (R7/R8): its review state, the PTO's remarks
    when returned, and the LGU actions — "Request to feature as tourist destination" /
    "Resubmit to PTO" (Lgu\DirectoryController::submitToPto) and "Return to Establishment".
    Requesting never publishes; only the PTO can. The establishment, its reports, account,
    and QR stay fully manageable whatever this state is. Expects $listing.
--}}
@php
    $blnIsFirstRequest = in_array($listing->lst_status, ['DRAFT', 'UNPUBLISHED', 'FOR_LGU_REVIEW'], true);
    // A PTO-managed attraction is moved into review by the PTO, not the LGU (Objective 3, D10).
    $blnIsManagedByPto = $listing->isManagedByPto();
    $blnCanSubmitToPto = ! $blnIsManagedByPto && ($blnIsFirstRequest || $listing->isForCorrection());
    $blnIsAttraction = $listing->isDestinationOnly();
    // A destination-only record has no establishment to return it to.
    $blnCanReturn = ! $blnIsAttraction && in_array($listing->lst_status, ['DRAFT', 'FOR_LGU_REVIEW', 'FOR_PTO_REVIEW', \App\Models\Listing::STATUS_FOR_CORRECTION], true);
    $strSubmitUrl = $blnIsAttraction ? route('lgu.directory.attractions.submit', $listing) : route('lgu.directory.establishments.submit', $listing);
    $strEditUrl = $blnIsAttraction ? route('lgu.directory.attractions.edit', $listing) : route('lgu.directory.establishments.edit', $listing);
    $strRemarks = ($listing->isForCorrection() || $listing->hasReturnedPendingChanges()) ? $listing->lst_review_remarks : null;
@endphp

<section class="dashboard-panel">
    <h2 class="dashboard-panel-title">Destination listing</h2>

    <dl class="mt-4">
        <dt class="detail-term">Status</dt>
        <dd class="detail-value">{{ $listing->destinationListingLabel() }}</dd>
    </dl>
    <p class="form-hint">{{ $blnIsAttraction ? 'The attraction' : 'The establishment' }} appears on the public site as a tourist destination only after the Provincial Tourism Office approves it.</p>

    @if ($strRemarks)
        <div class="mt-3 rounded-sm border border-warning/30 bg-warning-bg px-3 py-2.5 text-xs text-warning">
            <p class="font-semibold">PTO remarks</p>
            <p class="mt-0.5">{{ $strRemarks }}</p>
        </div>
    @endif

    <div class="mt-4 flex flex-col gap-2">
        @if ($blnIsManagedByPto)
            <p class="text-xs text-sand-500">This attraction is managed by the Provincial Tourism Office. Its details, photos, and review are handled by the PTO.</p>
        @elseif ($blnCanSubmitToPto)
            <form method="POST" action="{{ $strSubmitUrl }}">
                @csrf
                @method('PATCH')
                <button
                    type="button"
                    data-confirm-trigger
                    data-confirm-title="{{ $blnIsFirstRequest ? 'Request to feature '.$listing->lst_name.'?' : 'Resubmit '.$listing->lst_name.' to PTO?' }}"
                    data-confirm-message="The Provincial Tourism Office will review it. This does not publish it — it goes live only if the PTO approves."
                    data-confirm-label="{{ $blnIsFirstRequest ? 'Send request' : 'Resubmit' }}"
                    data-confirm-tone="success"
                    class="btn-primary w-full justify-center"
                >
                    <i class="ti ti-send" aria-hidden="true"></i>
                    {{ $blnIsFirstRequest ? ($blnIsAttraction ? 'Submit to PTO for review' : 'Request to feature as tourist destination') : 'Resubmit to PTO' }}
                </button>
            </form>
            @if ($listing->isForCorrection())
                <a href="{{ $strEditUrl }}" class="btn-secondary w-full justify-center">
                    <i class="ti ti-pencil" aria-hidden="true"></i>
                    Correct details
                </a>
            @endif
        @elseif ($listing->isForPtoReview())
            <p class="text-xs text-sand-500">Waiting for the Provincial Tourism Office's decision.</p>
        @elseif ($listing->hasReturnedPendingChanges())
            <a href="{{ $strEditUrl }}" class="btn-primary w-full justify-center">
                <i class="ti ti-pencil" aria-hidden="true"></i>
                Correct and resubmit changes
            </a>
            <p class="text-xs text-sand-500">The published version stays live meanwhile.</p>
        @elseif ($listing->hasPendingChanges())
            <p class="text-xs text-sand-500">Your changes to the public listing are with the PTO. The published version stays live until they are approved.</p>
        @endif

        @if ($blnCanReturn)
            <button type="button" data-modal-open="return-establishment-{{ $listing->lst_id }}" class="btn-secondary w-full justify-center border-danger/30 text-danger hover:bg-danger-bg">
                Return to Establishment
            </button>
        @endif
    </div>
</section>

@if ($blnCanReturn)
    <x-dashboard.modal id="return-establishment-{{ $listing->lst_id }}" title="Return to Establishment">
        <form id="return-establishment-form-{{ $listing->lst_id }}" method="POST" action="{{ route('lgu.directory.establishments.return', $listing) }}" class="flex flex-col gap-3">
            @csrf
            @method('PATCH')
            <label for="return-reason-{{ $listing->lst_id }}" class="form-label">Reason <span class="text-danger" aria-hidden="true">*</span></label>
            <textarea id="return-reason-{{ $listing->lst_id }}" name="reason" rows="3" required maxlength="500" placeholder="What does the establishment need to fix?" class="form-input"></textarea>
        </form>
        <x-slot:footer>
            <button type="button" data-modal-close class="btn-secondary">Cancel</button>
            <button type="submit" form="return-establishment-form-{{ $listing->lst_id }}" class="inline-flex items-center gap-2 rounded-sm bg-danger px-4 py-2.5 text-sm font-semibold text-sand-0 hover:opacity-90">Return to Establishment</button>
        </x-slot:footer>
    </x-dashboard.modal>
@endif
