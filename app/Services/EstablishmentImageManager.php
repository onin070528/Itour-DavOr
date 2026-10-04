<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Remove, cover, order, and credit actions — all apply immediately, no approval, each audit-logged.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Services;

use App\Enums\ImageStatus;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Models\User;
use App\Support\OperationLogger;
use Illuminate\Support\Facades\DB;

class EstablishmentImageManager
{
    /**
     * Archives $objImage immediately (I4: no approval needed). If it was
     * the cover, the next live (Published) image — by sort order — is
     * promoted; if none remain, the listing simply has no cover and public
     * pages fall back to the category placeholder.
     */
    public function remove(User $objActor, EstablishmentImage $objImage, ?string $strReason): void
    {
        $arrBefore = $objImage->getOriginal();
        $blnWasCover = $objImage->img_is_cover;
        $objListing = $objImage->listing;

        DB::transaction(function () use ($objImage, $blnWasCover, $objListing) {
            $objImage->update([
                'img_status' => ImageStatus::Archived,
                'img_is_cover' => false,
                'img_archived_at' => now(),
            ]);

            if ($blnWasCover) {
                $objNextCover = $objListing->establishmentImages()
                    ->where('img_status', ImageStatus::Published->value)
                    ->where('img_id', '!=', $objImage->img_id)
                    ->orderBy('img_sort_order')
                    ->first();

                $objNextCover?->update(['img_is_cover' => true]);
            }
        });

        OperationLogger::updated($objActor, 'establishment_image', $objImage->img_id, $objListing->municipality_id, $objListing->id, OperationLogger::diff($arrBefore, $objImage), $strReason);
    }

    /**
     * Makes $objImage the cover, demoting whichever Published image was
     * the cover before it — the database's partial unique index only ever
     * allows one PUBLISHED+cover row per establishment, so the old cover
     * must be cleared first in the same transaction.
     */
    public function setCover(User $objActor, EstablishmentImage $objImage): void
    {
        if (! $objImage->isPublished()) {
            return;
        }

        $arrBefore = $objImage->getOriginal();
        $objListing = $objImage->listing;

        DB::transaction(function () use ($objImage, $objListing) {
            $objListing->establishmentImages()
                ->where('img_is_cover', true)
                ->where('img_id', '!=', $objImage->img_id)
                ->update(['img_is_cover' => false]);

            $objImage->update(['img_is_cover' => true]);
        });

        OperationLogger::updated($objActor, 'establishment_image', $objImage->img_id, $objListing->municipality_id, $objListing->id, OperationLogger::diff($arrBefore, $objImage));
    }

    /**
     * @param  array<int, int>  $arrOrderedImageIds  img_id values in their new display order.
     */
    public function reorder(User $objActor, Listing $objListing, array $arrOrderedImageIds): void
    {
        $objImagesById = $objListing->establishmentImages()->whereIn('img_id', $arrOrderedImageIds)->get()->keyBy('img_id');

        DB::transaction(function () use ($objImagesById, $arrOrderedImageIds, $objActor, $objListing) {
            $intSortOrder = 1;

            foreach ($arrOrderedImageIds as $intImageId) {
                $objImage = $objImagesById->get($intImageId);

                if ($objImage === null) {
                    continue;
                }

                $arrBefore = $objImage->getOriginal();
                $objImage->update(['img_sort_order' => $intSortOrder]);

                if ($arrBefore['img_sort_order'] !== $intSortOrder) {
                    OperationLogger::updated($objActor, 'establishment_image', $objImage->img_id, $objListing->municipality_id, $objListing->id, OperationLogger::diff($arrBefore, $objImage));
                }

                $intSortOrder++;
            }
        });
    }

    public function updateCredit(User $objActor, EstablishmentImage $objImage, ?string $strCredit): void
    {
        $arrBefore = $objImage->getOriginal();

        $objImage->update(['img_credit' => $strCredit]);

        OperationLogger::updated($objActor, 'establishment_image', $objImage->img_id, $objImage->listing->municipality_id, $objImage->listing_id, OperationLogger::diff($arrBefore, $objImage));
    }
}
