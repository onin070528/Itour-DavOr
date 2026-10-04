<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : LGU photo upload on behalf of paper/no-account establishments, and the LGU's approval queue.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Http\Controllers\Lgu;

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Http\Controllers\Concerns\ManagesEstablishmentImages;
use App\Http\Controllers\Concerns\ReviewsEstablishmentImageQueue;
use App\Http\Requests\UploadEstablishmentImageRequest;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Policies\ImagePolicy;
use App\Services\EstablishmentImageReviewer;
use App\Services\EstablishmentImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ImagesController extends LguController
{
    use ManagesEstablishmentImages;
    use ReviewsEstablishmentImageQueue;

    /**
     * Photos page: "All photos" (upload-on-behalf — same minimal form as
     * every role: one or more photos, the ownership checkbox, an optional
     * credit field, first choosing which eligible establishment it's for
     * per I1) and "Waiting for approval" (the merged former Photo
     * Approvals queue) as two tabs on one page.
     */
    public function index(Request $objRequest): View
    {
        $objUser = $objRequest->user();

        $objQueuedImages = EstablishmentImage::query()
            ->with(['listing', 'uploadedBy', 'replaces'])
            ->where('img_status', ImageStatus::Pending->value)
            ->where('img_source_role', ImageSourceRole::Establishment->value)
            ->whereHas('listing', fn ($query) => $query->where('municipality_id', $objUser->municipality_id))
            ->orderBy('img_created_at')
            ->get();

        return $this->renderLgu($objRequest, 'lgu.images.index', 'images.index', 'Photos', [
            'listings' => $this->_eligibleListings($objRequest),
            'cards' => $this->_queueCards($objQueuedImages),
        ]);
    }

    public function store(UploadEstablishmentImageRequest $objRequest, EstablishmentImageUploader $objUploader): RedirectResponse
    {
        $arrData = $objRequest->validated();
        $objListing = Listing::query()->findOrFail($arrData['listing_id']);

        $arrUploadedImages = $objUploader->upload($objRequest->user(), $objListing, $arrData['photos'], $arrData['credit'] ?? null);

        return back()->with('toast', count($arrUploadedImages) === 1
            ? 'The photo was submitted for approval.'
            : count($arrUploadedImages).' photos were submitted for approval.');
    }

    /**
     * Photo management page for one establishment — thumbnails, status,
     * Add/Replace/Remove/Set Cover/reorder. Reached from the LGU's
     * Establishments directory list.
     */
    public function manage(Request $objRequest, Listing $listing, ImagePolicy $objPolicy): View
    {
        abort_unless($objRequest->user()->municipality_id === $listing->municipality_id, 403);

        return $this->renderLgu($objRequest, 'lgu.images.manage', 'images.index', 'Photos', [
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
        return redirect()->route('lgu.images.index', ['tab' => 'approval']);
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
     * "Approve all" on one establishment's card — approves every id in
     * $arrImageIds that is still Pending and passes a fresh per-image
     * authorization re-check; anything else is silently skipped.
     */
    public function approveBatch(Request $objRequest, Listing $listing, EstablishmentImageReviewer $objReviewer): RedirectResponse
    {
        abort_unless($objRequest->user()->municipality_id === $listing->municipality_id, 403);

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
        abort_unless($objRequest->user()->municipality_id === $listing->municipality_id, 403);

        $arrData = $objRequest->validate([
            'image_ids' => ['required', 'array', 'min:1'],
            'image_ids.*' => ['integer'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $arrResult = $objReviewer->returnBatch($objRequest->user(), $listing, $arrData['image_ids'], $arrData['reason']);

        return back()->with('toast', $this->_batchOutcomeMessage($arrResult, 'returned'));
    }

    /**
     * @return Collection<int, Listing>
     */
    private function _eligibleListings(Request $objRequest): Collection
    {
        $objUser = $objRequest->user();

        return Listing::query()
            ->where('municipality_id', $objUser->municipality_id)
            ->where(function ($query) {
                $query->whereDoesntHave('establishmentUser')->orWhere('reporting_mode', 'PAPER_LGU');
            })
            ->orderBy('name')
            ->get();
    }
}
