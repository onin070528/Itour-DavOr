<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Handles the Establishment role's merged Profile & Photos page —
 * editing profile details, managing the reviewed photo gallery, and the
 * self-review submission to the LGU (DRAFT/UNPUBLISHED → FOR_LGU_REVIEW).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Establishment;

use App\Models\Listing;
use App\Models\OperationLog;
use App\Services\ListingPublishWorkflow;
use App\Support\ListingReadinessChecklist;
use App\Support\OperationLogger;
use App\Support\TourismCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends EstablishmentController
{
    /**
     * Establishment Profile: the merged page — status banner, details
     * form, and the reviewed photo manager, in that order. Read-only
     * (both fields and photo management) once the package has been
     * submitted, until it's returned.
     */
    public function edit(Request $request): View
    {
        $listing = $this->ownListing($request);

        return $this->renderEstablishment($request, 'establishment.profile', 'establishment.profile', 'Establishment Profile', [
            'listing' => $listing,
            'images' => $listing->establishmentImages,
            'categories' => TourismCatalog::categories(),
            'blnIsReadOnly' => $this->isReadOnly($listing),
            'strStatusLabel' => $this->statusLabel($listing),
            'strStatusTone' => $this->statusTone($listing),
            'strReturnReason' => $this->returnReason($listing),
        ]);
    }

    /**
     * "Save draft" — saves the details form without validating required
     * fields and never changes status. Always available in DRAFT/UNPUBLISHED.
     */
    public function update(Request $request): RedirectResponse
    {
        $listing = $this->ownListing($request);
        abort_unless($request->user()->can('update', $listing), 403);

        $data = $this->validateDetails($request);

        if (! $this->saveDetails($request, $listing, $data)) {
            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Draft saved.');
    }

    /**
     * "Save and submit to LGU" — saves the details form, runs the Ready-
     * to-publish checklist, and only submits (DRAFT/UNPUBLISHED →
     * FOR_LGU_REVIEW) when everything required is present.
     */
    public function submit(Request $request, ListingPublishWorkflow $objWorkflow): RedirectResponse
    {
        $listing = $this->ownListing($request);
        abort_unless($request->user()->can('update', $listing), 403);
        abort_unless($request->user()->can('submitToLgu', $listing), 403);

        $data = $this->validateDetails($request);

        if (! $this->saveDetails($request, $listing, $data)) {
            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        $arrMissingFields = ListingReadinessChecklist::missingFields($listing->fresh());

        if ($arrMissingFields !== []) {
            return back()->with('arrMissingFields', $arrMissingFields)->with('toast', 'A few things are missing before this can be submitted.')->with('toast_tone', 'danger');
        }

        try {
            $objWorkflow->submitToLgu($request->user(), $listing);
        } catch (\Throwable $e) {
            Log::error('Failed to submit establishment profile to LGU.', ['exception' => $e, 'listing_id' => $listing->id]);

            return back()->with('toast', 'Something went wrong while submitting. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Submitted to your LGU tourism office for review.');
    }

    /**
     * @return array{name: string, category: string, address: string, description: ?string, phone: ?string, hours: ?string, email: ?string, website: ?string}
     */
    private function validateDetails(Request $request): array
    {
        return $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(collect(TourismCatalog::categories())->pluck('slug')->reject(fn ($slug) => $slug === 'destinations'))],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:255'],
            'hours' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveDetails(Request $request, Listing $listing, array $data): bool
    {
        $before = $listing->getOriginal();

        try {
            $listing->update([
                'name' => $data['name'] ?? $listing->name,
                'category' => $data['category'] ?? $listing->category,
                'barangay' => $data['address'] ?? $listing->barangay,
                'description' => $data['description'] ?? null,
                'contact_phone' => $data['phone'] ?? null,
                'hours' => $data['hours'] ?? null,
                'email' => $data['email'] ?? null,
                'website' => $data['website'] ?? null,
            ]);

            // The account's organization_name is a display label only (the
            // join to $listing is via establishment_id, not this string) —
            // still kept in sync so EstablishmentMockData's name-keyed
            // lookups (arrivals/feedback reads) don't go stale.
            if ($listing->name !== $request->user()->organization_name) {
                $request->user()->update(['organization_name' => $listing->name]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to save establishment profile.', ['exception' => $e, 'listing_id' => $listing->id]);

            return false;
        }

        OperationLogger::updated($request->user(), 'establishment', $listing->id, $listing->municipality_id, $listing->id, OperationLogger::diff($before, $listing));

        return true;
    }

    /**
     * Read-only once the package leaves the establishment's hands — only
     * DRAFT/UNPUBLISHED are editable (matches ListingPolicy::update()).
     */
    private function isReadOnly(Listing $listing): bool
    {
        return ! in_array($listing->status, ['DRAFT', 'UNPUBLISHED'], true);
    }

    private function statusLabel(Listing $listing): string
    {
        if ($this->returnReason($listing) !== null) {
            return 'Returned';
        }

        return match ($listing->status) {
            'DRAFT', 'UNPUBLISHED' => 'Draft',
            'FOR_LGU_REVIEW' => 'Waiting for LGU Review',
            'FOR_PTO_REVIEW' => 'Waiting for PTO',
            'PUBLISHED' => 'Live',
            default => $listing->status,
        };
    }

    private function statusTone(Listing $listing): string
    {
        if ($this->returnReason($listing) !== null) {
            return 'warning';
        }

        return match ($listing->status) {
            'FOR_LGU_REVIEW', 'FOR_PTO_REVIEW' => 'info',
            'PUBLISHED' => 'success',
            default => 'neutral',
        };
    }

    /**
     * Derives "was this DRAFT/UNPUBLISHED status caused by a return, and
     * why" from the audit trail — no extra database column. Only the most
     * recent relevant transition matters: a later submit/publish means the
     * listing has moved on since the return and the reason is stale.
     */
    private function returnReason(Listing $listing): ?string
    {
        if (! in_array($listing->status, ['DRAFT', 'UNPUBLISHED'], true)) {
            return null;
        }

        $objLastTransition = OperationLog::query()
            ->where('entity_type', 'establishment')
            ->where('entity_id', $listing->id)
            ->whereIn('action', ['submit', 'return', 'publish', 'unpublish'])
            ->latest('id')
            ->first();

        return $objLastTransition?->action === 'return' ? $objLastTransition->reason : null;
    }

    /**
     * QR Code: the establishment-specific QR tourists scan to reach the
     * arrival self-registration form. Encodes a unique check-in URL keyed
     * on this establishment's directory listing id, so every establishment
     * gets its own distinct, scannable code (rendered client-side — see
     * resources/js/establishment.js).
     */
    public function qr(Request $request): View
    {
        abort_if($request->user()->establishment_id === null, 403, 'Your account is not linked to an establishment yet.');
        $listing = $request->user()->establishment()->firstOrFail();

        return $this->renderEstablishment($request, 'establishment.qr', 'establishment.qr', 'QR Code', [
            'establishmentName' => $listing->name,
            'checkinUrl' => route('lgu.establishmentQr', ['establishment' => $listing->uuid]),
        ]);
    }

    /**
     * Resolved via the account's establishment_id FK — not by matching
     * Listing.name against organization_name, which is a mutable display
     * string an account could otherwise rename to collide with a different
     * establishment's listing.
     */
    private function ownListing(Request $request): Listing
    {
        abort_if($request->user()->establishment_id === null, 403, 'Your account is not linked to an establishment yet.');

        return $request->user()->establishment()->firstOrFail();
    }
}
