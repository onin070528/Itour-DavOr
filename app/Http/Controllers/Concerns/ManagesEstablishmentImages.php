<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Shared Replace/Remove/Cover/Reorder/Credit actions behind the Establishment, LGU, and PTO photo manager pages.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Http\Controllers\Concerns;

use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Policies\ImagePolicy;
use App\Rules\MinimumImageDimensions;
use App\Rules\RealImageMimeType;
use App\Services\EstablishmentImageManager;
use App\Services\EstablishmentImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * I4: remove, cover, order, and credit all apply immediately — no approval
 * — so these are plain authorize-then-act actions, unlike approve()/
 * return() which only PTO/LGU ever reach. Every method here is reached by
 * all three role controllers (Establishment, Lgu, Pto\DirectoryController's
 * photo manager), each gated by the SAME ImagePolicy::manage() check.
 */
trait ManagesEstablishmentImages
{
    public function replaceImage(Request $objRequest, EstablishmentImage $image, EstablishmentImageUploader $objUploader): RedirectResponse
    {
        abort_unless($objRequest->user()->can('manage', $image), 403);

        $arrData = $objRequest->validate([
            'photo' => ['required', 'file', 'max:'.(int) config('establishment_images.max_file_size_kb'),
                new RealImageMimeType, new MinimumImageDimensions],
            'credit' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $objUploader->replace($objRequest->user(), $image, $arrData['photo'], $arrData['credit'] ?? null);
        } catch (ValidationException $objException) {
            return back()->withErrors($objException->errors());
        }

        return back()->with('toast', 'Replacement submitted.');
    }

    public function removeImage(Request $objRequest, EstablishmentImage $image, EstablishmentImageManager $objManager): RedirectResponse
    {
        abort_unless($objRequest->user()->can('manage', $image), 403);

        $arrData = $objRequest->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $objManager->remove($objRequest->user(), $image, $arrData['reason'] ?? null);

        return back()->with('toast', 'Photo removed.');
    }

    public function setCoverImage(Request $objRequest, EstablishmentImage $image, EstablishmentImageManager $objManager): RedirectResponse
    {
        abort_unless($objRequest->user()->can('manage', $image), 403);

        $objManager->setCover($objRequest->user(), $image);

        return back()->with('toast', 'Cover photo updated.');
    }

    public function updateImageCredit(Request $objRequest, EstablishmentImage $image, EstablishmentImageManager $objManager): RedirectResponse
    {
        abort_unless($objRequest->user()->can('manage', $image), 403);

        $arrData = $objRequest->validate(['credit' => ['nullable', 'string', 'max:255']]);

        $objManager->updateCredit($objRequest->user(), $image, $arrData['credit'] ?? null);

        return back()->with('toast', 'Credit updated.');
    }

    public function reorderImages(Request $objRequest, Listing $listing, EstablishmentImageManager $objManager): RedirectResponse
    {
        abort_unless(app(ImagePolicy::class)->manageListing($objRequest->user(), $listing), 403);

        $arrData = $objRequest->validate(['order' => ['required', 'array']]);

        $objManager->reorder($objRequest->user(), $listing, array_map('intval', $arrData['order']));

        return back()->with('toast', 'Photo order updated.');
    }
}
