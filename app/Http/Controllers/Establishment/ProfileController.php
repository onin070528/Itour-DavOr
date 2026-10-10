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

use App\Models\Category;
use App\Models\Listing;
use App\Models\OperationLog;
use App\Rules\EstablishmentTypeBelongsToCategory;
use App\Services\ListingPublishWorkflow;
use App\Services\QrCodeService;
use App\Support\ListingReadinessChecklist;
use App\Support\OperationLogger;
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
            'categories' => Category::query()->active()->forEstablishments()->get(),
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

        $arrData = $this->validateDetails($objRequest, $objListing);

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

        $arrData = $this->validateDetails($objRequest, $objListing);

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
     * Category -> Type uses the same single source and server-side rule as
     * the LGU establishment form (config/establishment_categories.php,
     * EstablishmentTypeBelongsToCategory). Fields stay optional so "Save
     * draft" never blocks; when a category is chosen, its type is required.
     * A type submitted on its own is checked against the current category.
     *
     * @return array{name: ?string, cat_id: ?int, type: ?string, address: ?string, description: ?string, phone: ?string, hours: ?string, email: ?string, website: ?string}
     */
    private function validateDetails(Request $objRequest, Listing $objListing): array
    {
        $objCategory = $objRequest->filled('cat_id')
            ? Category::query()->forEstablishments()->find($objRequest->input('cat_id'))
            : $objListing->categoryRecord;

        return $objRequest->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'cat_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_categories', 'cat_id')
                    ->where('cat_is_active', true)
                    ->whereNot('cat_name', Category::DESTINATION_CATEGORY_NAME),
            ],
            'type' => ['nullable', 'required_with:cat_id', 'string', new EstablishmentTypeBelongsToCategory($objCategory)],
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
        $objCategory = isset($arrData['cat_id']) ? Category::query()->find($arrData['cat_id']) : null;

        try {
            $objListing->update([
                'lst_name' => $arrData['name'] ?? $objListing->lst_name,
                // cat_id and the legacy `lst_category` slug always change together.
                'cat_id' => $objCategory?->cat_id ?? $objListing->cat_id,
                'lst_category' => $objCategory?->legacySlug() ?? $objListing->lst_category,
                'lst_type' => $arrData['type'] ?? $objListing->lst_type,
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
            // The PTO returned it to the LGU, which corrects and resubmits it.
            'FOR_CORRECTION' => 'With your LGU for correction',
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
            'FOR_CORRECTION' => 'warning',
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
     * arrival self-registration form. Built by App\Services\QrCodeService
     * from the listing's uuid check-in URL, so every establishment gets its
     * own distinct, stable code. Shown only while the listing is accepting
     * registrations (Listing::isAcceptingRegistrations()) — otherwise the
     * page explains why instead of offering a code that would not work.
     */
    public function qr(Request $objRequest, QrCodeService $qrCodeService): View
    {
        abort_if($objRequest->user()->lst_id === null, 403, 'Your account is not linked to an establishment yet.');
        $objListing = $objRequest->user()->establishment()->firstOrFail();
        $blnIsAcceptingRegistrations = $objListing->isAcceptingRegistrations();

        return $this->renderEstablishment($objRequest, 'establishment.qr', 'establishment.qr', 'QR Code', [
            'establishmentName' => $objListing->lst_name,
            'isAcceptingRegistrations' => $blnIsAcceptingRegistrations,
            'checkinUrl' => $blnIsAcceptingRegistrations ? $qrCodeService->buildCheckinUrl($objListing) : null,
            'qrSvg' => $blnIsAcceptingRegistrations ? $qrCodeService->generateSvg($objListing) : null,
            // The on/off switch only matters while everything else about the
            // listing allows QR check-in (eligible category, Online iTOUR, active account).
            'isQrSwitchedOff' => $objListing->isQrEnabled() && $objListing->lst_is_qr_enabled === false,
            'canManageQr' => $objRequest->user()->can('manageQr', $objListing),
            'qrStatusUrl' => route('qrCodes.updateStatus', $objListing),
            'qrDownloadUrl' => route('qrCodes.download', $objListing),
            'qrPosterUrl' => route('qrCodes.poster', $objListing),
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
