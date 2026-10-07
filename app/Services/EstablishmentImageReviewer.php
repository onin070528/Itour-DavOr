<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Approves or returns a pending establishment image, including the Replace workflow's
 * cover/sort-order handoff.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Services;

use App\Enums\ImageStatus;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\EstablishmentImageApproved;
use App\Notifications\EstablishmentImageBatchDecided;
use App\Notifications\EstablishmentImageReturned;
use App\Support\OperationLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EstablishmentImageReviewer
{
    /**
     * Approving a plain upload just publishes it. Approving a Replace
     * request does both halves in the SAME transaction — the new image
     * becomes Published (inheriting the old one's cover flag and sort
     * order), and the old one becomes Archived — and archives the OLD row
     * FIRST, before publishing the new one, so the database's
     * one-published-cover-per-establishment partial unique index is never
     * violated even for a single instant in between.
     */
    public function approve(User $objReviewer, EstablishmentImage $objImage): void
    {
        $arrBefore = $objImage->getOriginal();

        try {
            DB::transaction(function () use ($objReviewer, $objImage) {
                $this->_applyApproval($objReviewer, $objImage);
            });
        } catch (\Throwable $objException) {
            Log::error('Failed to approve establishment image.', ['exception' => $objException, 'image_id' => $objImage->img_id]);

            throw ValidationException::withMessages(['image' => 'Something went wrong while saving. Please try again.']);
        }

        OperationLogger::approved(
            $objReviewer,
            'establishment_image',
            $objImage->img_id,
            $objImage->listing->mun_id,
            OperationLogger::diff($arrBefore, $objImage),
        );

        $objImage->uploadedBy?->notify(new EstablishmentImageApproved($objImage));
    }

    /**
     * Returning leaves the old (if this was a Replace request) or
     * surrounding images untouched — only this one row moves to Returned,
     * with the reviewer's note attached for the uploader to see.
     */
    public function returnImage(User $objReviewer, EstablishmentImage $objImage, string $strReason): void
    {
        $arrBefore = $objImage->getOriginal();

        try {
            $this->_applyReturn($objReviewer, $objImage, $strReason);
        } catch (\Throwable $objException) {
            Log::error('Failed to return establishment image.', ['exception' => $objException, 'image_id' => $objImage->img_id]);

            throw ValidationException::withMessages(['reason' => 'Something went wrong while saving. Please try again.']);
        }

        OperationLogger::returned(
            $objReviewer,
            'establishment_image',
            $objImage->img_id,
            $strReason,
            $objImage->listing->mun_id,
            OperationLogger::diff($arrBefore, $objImage),
        );

        $objImage->uploadedBy?->notify(new EstablishmentImageReturned($objImage));
    }

    /**
     * Approves every still-PENDING id in $arrImageIds belonging to
     * $objListing, in ONE transaction — all succeed or none do (a genuine
     * failure rolls everything back and throws). An id that is no longer
     * PENDING by the time its turn comes (decided by someone else since
     * the page was loaded, or simply stale) — or that fails a fresh
     * per-image App\Policies\ImagePolicy::approve() re-check, since a
     * tampered-in id must never be trusted just because it rode along with
     * a listing the reviewer legitimately has access to — is skipped, not
     * treated as a failure. Every uploader whose photo(s) were actually
     * decided gets exactly one combined notification afterward.
     *
     * @param  array<int, int>  $arrImageIds
     * @return array{decided: int, skipped: int}
     */
    public function approveBatch(User $objReviewer, Listing $objListing, array $arrImageIds): array
    {
        $objDecidedImages = new Collection;
        $intSkipped = 0;

        try {
            DB::transaction(function () use ($objReviewer, $objListing, $arrImageIds, &$objDecidedImages, &$intSkipped) {
                foreach ($arrImageIds as $intImageId) {
                    $objImage = EstablishmentImage::query()->lockForUpdate()->find($intImageId);

                    if (! $this->_isEligibleForBatchDecision($objImage, $objListing, $objReviewer)) {
                        $intSkipped++;

                        continue;
                    }

                    $arrBefore = $objImage->getOriginal();
                    $this->_applyApproval($objReviewer, $objImage);

                    OperationLogger::approved(
                        $objReviewer,
                        'establishment_image',
                        $objImage->img_id,
                        $objImage->listing->mun_id,
                        OperationLogger::diff($arrBefore, $objImage),
                    );

                    $objDecidedImages->push($objImage);
                }
            });
        } catch (\Throwable $objException) {
            Log::error('Failed to approve a batch of establishment images.', ['exception' => $objException, 'listing_id' => $objListing->lst_id]);

            throw ValidationException::withMessages([
                'photos' => 'Something went wrong while saving your decision. Please try again.',
            ]);
        }

        $this->_notifyUploadersOfBatchDecision($objDecidedImages, fn (int $intCount) => sprintf(
            '%d %s approved.',
            $intCount,
            Str::plural('photo', $intCount),
        ));

        return ['decided' => $objDecidedImages->count(), 'skipped' => $intSkipped];
    }

    /**
     * Returns every still-PENDING id in $arrImageIds belonging to
     * $objListing with the same reason, in ONE transaction — used for both
     * "Return all" (every pending id on the card) and "Return this one" (a
     * single id). Same eligibility/skip/notification rules as
     * approveBatch() above.
     *
     * @param  array<int, int>  $arrImageIds
     * @return array{decided: int, skipped: int}
     */
    public function returnBatch(User $objReviewer, Listing $objListing, array $arrImageIds, string $strReason): array
    {
        $objDecidedImages = new Collection;
        $intSkipped = 0;

        try {
            DB::transaction(function () use ($objReviewer, $objListing, $arrImageIds, $strReason, &$objDecidedImages, &$intSkipped) {
                foreach ($arrImageIds as $intImageId) {
                    $objImage = EstablishmentImage::query()->lockForUpdate()->find($intImageId);

                    if (! $this->_isEligibleForBatchDecision($objImage, $objListing, $objReviewer)) {
                        $intSkipped++;

                        continue;
                    }

                    $arrBefore = $objImage->getOriginal();
                    $this->_applyReturn($objReviewer, $objImage, $strReason);

                    OperationLogger::returned(
                        $objReviewer,
                        'establishment_image',
                        $objImage->img_id,
                        $strReason,
                        $objImage->listing->mun_id,
                        OperationLogger::diff($arrBefore, $objImage),
                    );

                    $objDecidedImages->push($objImage);
                }
            });
        } catch (\Throwable $objException) {
            Log::error('Failed to return a batch of establishment images.', ['exception' => $objException, 'listing_id' => $objListing->lst_id]);

            throw ValidationException::withMessages([
                'reason' => 'Something went wrong while saving your decision. Please try again.',
            ]);
        }

        $this->_notifyUploadersOfBatchDecision($objDecidedImages, fn (int $intCount) => sprintf(
            '%d %s returned: %s',
            $intCount,
            Str::plural('photo', $intCount),
            $strReason,
        ));

        return ['decided' => $objDecidedImages->count(), 'skipped' => $intSkipped];
    }

    /**
     * Whether $objImage may be acted on right now as part of a batch
     * decision for $objListing — null (id didn't resolve), a foreign
     * listing_id (a tampered-in id), no-longer-PENDING (raced), or a
     * failed policy re-check all make it ineligible, and the caller skips
     * it rather than trusting the batch's listing id alone.
     */
    private function _isEligibleForBatchDecision(?EstablishmentImage $objImage, Listing $objListing, User $objReviewer): bool
    {
        if ($objImage === null || $objImage->lst_id !== $objListing->lst_id || ! $objImage->isPending()) {
            return false;
        }

        return $objReviewer->can('approve', $objImage);
    }

    /**
     * The actual approve mutation, with no transaction/logging/notification
     * of its own — approve() wraps this for a single image, approveBatch()
     * calls it once per decided image inside ONE outer transaction.
     */
    private function _applyApproval(User $objReviewer, EstablishmentImage $objImage): void
    {
        $objOldImage = $objImage->img_replaces_id !== null
            ? EstablishmentImage::query()->lockForUpdate()->find($objImage->img_replaces_id)
            : null;

        if ($objOldImage !== null) {
            // Captured before the old row is mutated below — otherwise the
            // inherited values would read back the just-archived
            // (false/already-overwritten) ones instead of the originals.
            $blnInheritedCover = $objOldImage->img_is_cover;
            $intInheritedSortOrder = $objOldImage->img_sort_order;

            $objOldImage->update([
                'img_status' => ImageStatus::Archived,
                'img_is_cover' => false,
                'img_archived_at' => now(),
            ]);

            $objImage->update([
                'img_status' => ImageStatus::Published,
                'img_is_cover' => $blnInheritedCover,
                'img_sort_order' => $intInheritedSortOrder,
                'img_reviewed_by' => $objReviewer->usr_id,
                'img_reviewed_at' => now(),
            ]);
        } else {
            // Part C: "the first approved image becomes the cover if none
            // exists" — a plain (non-Replace) upload is never marked cover
            // at upload time, so without this a listing's very first photo
            // would publish with no cover at all. Checked AFTER acquiring
            // no lock of its own, but still inside the caller's
            // transaction, against every OTHER image on the same listing.
            $blnListingHasNoPublishedCover = ! EstablishmentImage::query()
                ->where('lst_id', $objImage->lst_id)
                ->where('img_id', '!=', $objImage->img_id)
                ->where('img_status', ImageStatus::Published->value)
                ->where('img_is_cover', true)
                ->exists();

            $objImage->update([
                'img_status' => ImageStatus::Published,
                'img_is_cover' => $blnListingHasNoPublishedCover,
                'img_reviewed_by' => $objReviewer->usr_id,
                'img_reviewed_at' => now(),
            ]);
        }
    }

    /**
     * The actual return mutation, with no logging/notification of its own —
     * returnImage() wraps this for a single image, returnBatch() calls it
     * once per decided image inside ONE outer transaction.
     */
    private function _applyReturn(User $objReviewer, EstablishmentImage $objImage, string $strReason): void
    {
        $objImage->update([
            'img_status' => ImageStatus::Rejected,
            'img_review_note' => $strReason,
            'img_reviewed_by' => $objReviewer->usr_id,
            'img_reviewed_at' => now(),
        ]);
    }

    /**
     * One App\Notifications\EstablishmentImageBatchDecided per uploader
     * represented in $objDecidedImages, each counting only that uploader's
     * own share of the batch — never one notification per photo.
     */
    private function _notifyUploadersOfBatchDecision(Collection $objDecidedImages, \Closure $fnMessageForCount): void
    {
        $objDecidedImages->groupBy('img_uploaded_by')->each(function (Collection $objImagesForUploader) use ($fnMessageForCount) {
            $objFirstImage = $objImagesForUploader->first();
            $objUploader = $objFirstImage->uploadedBy;

            if ($objUploader === null) {
                return;
            }

            $objUploader->notify(new EstablishmentImageBatchDecided(
                $objFirstImage->listing,
                $fnMessageForCount($objImagesForUploader->count()),
            ));
        });
    }
}
