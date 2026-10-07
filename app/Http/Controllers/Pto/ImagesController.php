<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: PTO photo upload for any establishment (publishes immediately), and the PTO's approval
 * queue.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Http\Controllers\Concerns\ManagesEstablishmentImages;
use App\Http\Controllers\Concerns\ReviewsEstablishmentImageQueue;
use App\Http\Requests\UploadEstablishmentImageRequest;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Models\Municipality;
use App\Policies\ImagePolicy;
use App\Services\EstablishmentImageReviewer;
use App\Services\EstablishmentImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ImagesController extends PtoController
{
    use ManagesEstablishmentImages;
    use ReviewsEstablishmentImageQueue;

    /**
     * Photos page: "All photos" (upload screen — same minimal form as every
     * role, for any establishment province-wide; a PTO upload publishes
     * immediately, no approval step) and "Waiting for approval" (the
     * merged former Photo Approvals queue, with a municipality filter) as
     * two tabs on one page.
     */
    public function index(Request $objRequest): View
    {
        $intSelectedMunicipalityId = $objRequest->filled('municipality') ? $objRequest->integer('municipality') : null;

        return $this->renderPto($objRequest, 'pto.images.index', 'images.index', 'Photos', [
            'listings' => Listing::query()->orderBy('lst_name')->get(),
            'cards' => $this->_queueCards($this->_pendingLguSourcedImages($intSelectedMunicipalityId)),
            'municipalities' => Municipality::query()->orderBy('mun_name')->get(),
            'selectedMunicipalityId' => $intSelectedMunicipalityId,
        ]);
    }

    public function store(UploadEstablishmentImageRequest $objRequest, EstablishmentImageUploader $objUploader): RedirectResponse
    {
        $arrData = $objRequest->validated();
        $objListing = Listing::query()->findOrFail($arrData['listing_id']);

        $arrUploadedImages = $objUploader->upload($objRequest->user(), $objListing, $arrData['photos'], $arrData['credit'] ?? null);

        return back()->with('toast', count($arrUploadedImages) === 1
            ? 'The photo is now live.'
            : count($arrUploadedImages).' photos are now live.');
    }

    /**
     * Photo management page for one establishment — thumbnails, status,
     * Add/Replace/Remove/Set Cover/reorder. Reached from the PTO Tourism
     * Directory list. Unlike the LGU equivalent, there is no municipality
     * restriction: the PTO may manage any establishment.
     */
    public function manage(Request $objRequest, Listing $listing, ImagePolicy $objPolicy): View
    {
        return $this->renderPto($objRequest, 'pto.images.manage', 'images.index', 'Photos', [
            'listing' => $listing,
            'images' => $listing->establishmentImages,
            'canUpload' => $objPolicy->uploadFor($objRequest->user(), $listing),
        ]);
    }

    /**
     * The old, separate Photo Approvals page no longer exists — it's the
     * "Waiting for approval" tab on the merged Photos page now.
     */
    public function queue(): RedirectResponse
    {
        return redirect()->route('pto.images.index', ['tab' => 'approval']);
    }

    /**
     * $image — not $objImage — matches the {image} route segment so
     * Laravel's implicit model binding resolves it (framework-required;
     * ITD naming is exempt here).
     */
    public function approve(Request $objRequest, EstablishmentImage $image, EstablishmentImageReviewer $objReviewer): RedirectResponse
    {
        abort_unless($objRequest->user()->can('approve', $image), 403);

        $objReviewer->approve($objRequest->user(), $image);

        return back()->with('toast', 'Photo approved — it is now live.');
    }

    public function return(Request $objRequest, EstablishmentImage $image, EstablishmentImageReviewer $objReviewer): RedirectResponse
    {
        abort_unless($objRequest->user()->can('approve', $image), 403);

        $arrData = $objRequest->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $objReviewer->returnImage($objRequest->user(), $image, $arrData['reason']);

        return back()->with('toast', 'Photo returned to the uploader.');
    }

    /**
     * "Approve all" on one establishment's card — no municipality
     * restriction, unlike the LGU equivalent, since PTO may approve for
     * any establishment.
     */
    public function approveBatch(Request $objRequest, Listing $listing, EstablishmentImageReviewer $objReviewer): RedirectResponse
    {
        $arrData = $objRequest->validate([
            'image_ids' => ['required', 'array', 'min:1'],
            'image_ids.*' => ['integer'],
        ]);

        $arrResult = $objReviewer->approveBatch($objRequest->user(), $listing, $arrData['image_ids']);

        return back()->with('toast', $this->_batchOutcomeMessage($arrResult, 'approved'));
    }

    /**
     * "Return all" (every pending id on the card) and "Return this one" (a
     * single id) both post here — the reviewer always supplies a reason.
     */
    public function returnBatch(Request $objRequest, Listing $listing, EstablishmentImageReviewer $objReviewer): RedirectResponse
    {
        $arrData = $objRequest->validate([
            'image_ids' => ['required', 'array', 'min:1'],
            'image_ids.*' => ['integer'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $arrResult = $objReviewer->returnBatch($objRequest->user(), $listing, $arrData['image_ids'], $arrData['reason']);

        return back()->with('toast', $this->_batchOutcomeMessage($arrResult, 'returned'));
    }

    /**
     * Every PENDING, LGU-sourced image routed to PTO — province-wide, or
     * narrowed to one municipality when $intMunicipalityId is given.
     *
     * @return Collection<int, EstablishmentImage>
     */
    private function _pendingLguSourcedImages(?int $intMunicipalityId): Collection
    {
        return EstablishmentImage::query()
            ->with(['listing', 'uploadedBy', 'replaces'])
            ->where('img_status', ImageStatus::Pending->value)
            ->where('img_source_role', ImageSourceRole::Lgu->value)
            ->when($intMunicipalityId !== null, fn ($objQuery) => $objQuery->whereHas('listing', fn ($objListingQuery) => $objListingQuery->where('mun_id', $intMunicipalityId)))
            ->orderBy('img_created_at')
            ->get();
    }
}
