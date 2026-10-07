<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Shared Replace/Remove/Cover/Reorder/Credit actions behind the Establishment, LGU, and PTO
 * photo manager pages.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
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
use Illuminate\Support\Facades\Log;
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
        } catch (\Throwable $objException) {
            return $this->_imageActionFailed('replace an establishment image', $objException, $image);
        }

        return back()->with('toast', 'Replacement submitted.');
    }

    public function removeImage(Request $objRequest, EstablishmentImage $image, EstablishmentImageManager $objManager): RedirectResponse
    {
        abort_unless($objRequest->user()->can('manage', $image), 403);

        $arrData = $objRequest->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $objManager->remove($objRequest->user(), $image, $arrData['reason'] ?? null);
        } catch (\Throwable $objException) {
            return $this->_imageActionFailed('remove an establishment image', $objException, $image);
        }

        return back()->with('toast', 'Photo removed.');
    }

    public function setCoverImage(Request $objRequest, EstablishmentImage $image, EstablishmentImageManager $objManager): RedirectResponse
    {
        abort_unless($objRequest->user()->can('manage', $image), 403);

        try {
            $objManager->setCover($objRequest->user(), $image);
        } catch (\Throwable $objException) {
            return $this->_imageActionFailed('set an establishment cover image', $objException, $image);
        }

        return back()->with('toast', 'Cover photo updated.');
    }

    public function updateImageCredit(Request $objRequest, EstablishmentImage $image, EstablishmentImageManager $objManager): RedirectResponse
    {
        abort_unless($objRequest->user()->can('manage', $image), 403);

        $arrData = $objRequest->validate(['credit' => ['nullable', 'string', 'max:255']]);

        try {
            $objManager->updateCredit($objRequest->user(), $image, $arrData['credit'] ?? null);
        } catch (\Throwable $objException) {
            return $this->_imageActionFailed('update an establishment image credit', $objException, $image);
        }

        return back()->with('toast', 'Credit updated.');
    }

    public function reorderImages(Request $objRequest, Listing $listing, EstablishmentImageManager $objManager): RedirectResponse
    {
        abort_unless(app(ImagePolicy::class)->manageListing($objRequest->user(), $listing), 403);

        $arrData = $objRequest->validate(['order' => ['required', 'array']]);

        try {
            $objManager->reorder($objRequest->user(), $listing, array_map('intval', $arrData['order']));
        } catch (\Throwable $objException) {
            Log::error('Failed to reorder establishment images.', ['exception' => $objException, 'lst_id' => $listing->lst_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Photo order updated.');
    }

    /**
     * Logs a failed photo action and sends the user back with a plain error toast.
     */
    private function _imageActionFailed(string $strAction, \Throwable $objException, EstablishmentImage $objImage): RedirectResponse
    {
        Log::error('Failed to '.$strAction.'.', ['exception' => $objException, 'img_id' => $objImage->img_id]);

        return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
    }
}
