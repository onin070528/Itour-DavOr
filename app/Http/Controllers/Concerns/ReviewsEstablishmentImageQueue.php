<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Shared queue-card grouping and batch-outcome messaging behind the LGU and PTO photo
 * approval queues.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Concerns;

use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One card per establishment (not one per photo) — both ImagesController's
 * queue() methods fetch a flat list of Pending images (already filtered to
 * the right approver routing and jurisdiction) and hand it to
 * _queueCards() to group.
 */
trait ReviewsEstablishmentImageQueue
{
    /**
     * Groups a flat, already-filtered list of Pending images by listing —
     * one card per establishment — with the fields the queue view needs:
     * the establishment, its images (oldest first), who most recently
     * uploaded, and when.
     *
     * @param  Collection<int, EstablishmentImage>  $objPendingImages
     * @return Collection<int, array{listing: Listing, images: Collection<int, EstablishmentImage>, uploader: ?User, latestUploadAt: Carbon}>
     */
    private function _queueCards(Collection $objPendingImages): Collection
    {
        return $objPendingImages
            ->groupBy('lst_id')
            ->map(function (Collection $objImagesForListing) {
                $objMostRecentImage = $objImagesForListing->sortByDesc('img_created_at')->first();

                return [
                    'listing' => $objMostRecentImage->listing,
                    'images' => $objImagesForListing->values(),
                    'uploader' => $objMostRecentImage->uploadedBy,
                    'latestUploadAt' => $objMostRecentImage->img_created_at,
                ];
            })
            ->values();
    }

    /**
     * One plain sentence summarizing a batch decision — e.g. "2 photos
     * approved." or, when some ids were skipped (already decided by
     * someone else, or didn't pass a fresh authorization check), "2 photos
     * approved. 1 photo was already decided and was skipped."
     *
     * @param  array{decided: int, skipped: int}  $arrResult
     */
    private function _batchOutcomeMessage(array $arrResult, string $strVerb): string
    {
        $intDecided = $arrResult['decided'];
        $intSkipped = $arrResult['skipped'];

        $strMessage = $intDecided === 0
            ? "No photos were {$strVerb}."
            : sprintf('%d %s %s.', $intDecided, Str::plural('photo', $intDecided), $strVerb);

        if ($intSkipped > 0) {
            $strWasWere = $intSkipped === 1 ? 'was' : 'were';
            $strMessage .= sprintf(' %d %s %s already decided by someone else and %s skipped.', $intSkipped, Str::plural('photo', $intSkipped), $strWasWere, $strWasWere);
        }

        return $strMessage;
    }
}
