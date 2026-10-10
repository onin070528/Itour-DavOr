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
    public function edit(Request $objRequest): View
    {
        $objListing = $this->ownListing($objRequest);

        return $this->renderEstablishment($objRequest, 'establishment.profile', 'establishment.profile', 'Establishment Profile', [
            'listing' => $objListing,
            'images' => $objListing->establishmentImages,
            'categories' => TourismCatalog::categories(),
            'blnIsReadOnly' => $this->isReadOnly($objListing),
            'strStatusLabel' => $this->statusLabel($objListing),
            'strStatusTone' => $this->statusTone($objListing),
            'strReturnReason' => $this->returnReason($objListing),
        ]);
    }

    /**
     * Photos page for a destination's account, which has no details form
     * (its LGU manages those) — only the photo manager. Photos go to the LGU
     * for approval exactly like an establishment's.
     */
    public function photos(Request $objRequest): View
    {
        $objListing = $this->ownListing($objRequest);
        abort_unless($objListing->lst_category === 'destinations', 404);

        return $this->renderEstablishment($objRequest, 'establishment.photos', 'establishment.photos', 'Photos', [
            'listing' => $objListing,
            'images' => $objListing->establishmentImages,
            'blnIsReadOnly' => $objListing->lst_status === 'Archived',
        ]);
    }

    /**
     * "Save draft" — saves the details form without validating required
     * fields and never changes status. Always available in DRAFT/UNPUBLISHED.
     */
    public function update(Request $objRequest): RedirectResponse
    {
        $objListing = $this->ownListing($objRequest);
        abort_unless($objRequest->user()->can('update', $objListing), 403);

        $arrData = $this->validateDetails($objRequest);

        if (! $this->saveDetails($objRequest, $objListing, $arrData)) {
            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Draft saved.');
    }

    /**
     * "Save and submit to LGU" — saves the details form, runs the Ready-
     * to-publish checklist, and only submits (DRAFT/UNPUBLISHED →
     * FOR_LGU_REVIEW) when everything required is present.
     */
    public function submit(Request $objRequest, ListingPublishWorkflow $objWorkflow): RedirectResponse
    {
        $objListing = $this->ownListing($objRequest);
        abort_unless($objRequest->user()->can('update', $objListing), 403);
        abort_unless($objRequest->user()->can('submitToLgu', $objListing), 403);

        $arrData = $this->validateDetails($objRequest);

        if (! $this->saveDetails($objRequest, $objListing, $arrData)) {
            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        $arrMissingFields = ListingReadinessChecklist::missingFields($objListing->fresh());

        if ($arrMissingFields !== []) {
            return back()->with('arrMissingFields', $arrMissingFields)->with('toast', 'A few things are missing before this can be submitted.')->with('toast_tone', 'danger');
        }

        try {
            $objWorkflow->submitToLgu($objRequest->user(), $objListing);
        } catch (\Throwable $objException) {
            Log::error('Failed to submit establishment profile to LGU.', ['exception' => $objException, 'listing_id' => $objListing->lst_id]);

            return back()->with('toast', 'Something went wrong while submitting. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Submitted to your LGU tourism office for review.');
    }

    /**
     * @return array{name: string, category: string, address: string, description: ?string, phone: ?string, hours: ?string, email: ?string, website: ?string}
     */
    private function validateDetails(Request $objRequest): array
    {
        return $objRequest->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(collect(TourismCatalog::categories())->pluck('slug')->reject(fn ($strSlug) => $strSlug === 'destinations'))],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:255'],
            'hours' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $arrData
     */
    private function saveDetails(Request $objRequest, Listing $objListing, array $arrData): bool
    {
        $arrBefore = $objListing->getOriginal();

        try {
            $objListing->update([
                'lst_name' => $arrData['name'] ?? $objListing->lst_name,
                'lst_category' => $arrData['category'] ?? $objListing->lst_category,
                'lst_barangay' => $arrData['address'] ?? $objListing->lst_barangay,
                'lst_description' => $arrData['description'] ?? null,
                'lst_contact_phone' => $arrData['phone'] ?? null,
                'lst_hours' => $arrData['hours'] ?? null,
                'lst_email' => $arrData['email'] ?? null,
                'lst_website' => $arrData['website'] ?? null,
            ]);

            // The account's usr_organization_name is a display label only (the
            // join to $objListing is via lst_id, not this string) —
            // still kept in sync so EstablishmentMockData's name-keyed
            // lookups (arrivals/feedback reads) don't go stale.
            if ($objListing->lst_name !== $objRequest->user()->usr_organization_name) {
                $objRequest->user()->update(['usr_organization_name' => $objListing->lst_name]);
            }
        } catch (\Throwable $objException) {
            Log::error('Failed to save establishment profile.', ['exception' => $objException, 'listing_id' => $objListing->lst_id]);

            return false;
        }

        OperationLogger::updated($objRequest->user(), 'establishment', $objListing->lst_id, $objListing->mun_id, $objListing->lst_id, OperationLogger::diff($arrBefore, $objListing));

        return true;
    }

    /**
     * Read-only once the package leaves the establishment's hands — only
     * DRAFT/UNPUBLISHED are editable (matches ListingPolicy::update()).
     */
    private function isReadOnly(Listing $objListing): bool
    {
        return ! in_array($objListing->lst_status, ['DRAFT', 'UNPUBLISHED'], true);
    }

    private function statusLabel(Listing $objListing): string
    {
        if ($this->returnReason($objListing) !== null) {
            return 'Returned';
        }

        return match ($objListing->lst_status) {
            'DRAFT', 'UNPUBLISHED' => 'Draft',
            'FOR_LGU_REVIEW' => 'Waiting for LGU Review',
            'FOR_PTO_REVIEW' => 'Waiting for PTO',
            'PUBLISHED' => 'Live',
            default => $objListing->lst_status,
        };
    }

    private function statusTone(Listing $objListing): string
    {
        if ($this->returnReason($objListing) !== null) {
            return 'warning';
        }

        return match ($objListing->lst_status) {
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
    private function returnReason(Listing $objListing): ?string
    {
        if (! in_array($objListing->lst_status, ['DRAFT', 'UNPUBLISHED'], true)) {
            return null;
        }

        $objLastTransition = OperationLog::query()
            ->where('opl_entity_type', 'establishment')
            ->where('opl_entity_id', $objListing->lst_id)
            ->whereIn('opl_action', ['submit', 'return', 'publish', 'unpublish'])
            ->latest('opl_id')
            ->first();

        return $objLastTransition?->opl_action === 'return' ? $objLastTransition->opl_reason : null;
    }

    /**
     * QR Code: the establishment-specific QR tourists scan to reach the
     * arrival self-registration form. Encodes a unique check-in URL keyed
     * on this establishment's directory listing id, so every establishment
     * gets its own distinct, scannable code (rendered client-side — see
     * resources/js/establishment.js).
     */
    public function qr(Request $objRequest): View
    {
        abort_if($objRequest->user()->lst_id === null, 403, 'Your account is not linked to an establishment yet.');
        $objListing = $objRequest->user()->establishment()->firstOrFail();

        return $this->renderEstablishment($objRequest, 'establishment.qr', 'establishment.qr', 'QR Code', [
            'establishmentName' => $objListing->lst_name,
            'checkinUrl' => $objListing->requiresArrivalRecords() ? route('lgu.establishmentQr', ['establishment' => $objListing->lst_uuid]) : null,
            'feedbackUrl' => $objListing->isFeedbackQrEnabled() ? route('feedback.form', ['listing' => $objListing->lst_uuid]) : null,
        ]);
    }

    /**
     * Resolved via the account's lst_id FK — not by matching
     * Listing.name against usr_organization_name, which is a mutable display
     * string an account could otherwise rename to collide with a different
     * establishment's listing.
     */
    private function ownListing(Request $objRequest): Listing
    {
        abort_if($objRequest->user()->lst_id === null, 403, 'Your account is not linked to an establishment yet.');

        return $objRequest->user()->establishment()->firstOrFail();
    }
}
