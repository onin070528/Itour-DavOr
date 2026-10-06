<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Orchestrates an establishment photo upload — processing, storage, duplicate checking,
 * and the approval-routing status.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Services;

use App\Enums\ImageSourceRole;
use App\Enums\ImageStatus;
use App\Enums\UserRole;
use App\Models\EstablishmentImage;
use App\Models\Listing;
use App\Models\User;
use App\Support\OperationLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * One upload call processes every file in the batch or none of them — a
 * partial upload (some photos accepted, one silently dropped) would be a
 * confusing result on a screen this simple.
 */
class EstablishmentImageUploader
{
    private const DISK = 'local';

    public function __construct(
        private readonly EstablishmentImageProcessor $objProcessor = new EstablishmentImageProcessor,
        private readonly EstablishmentImageReviewer $objReviewer = new EstablishmentImageReviewer,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $arrUploadedFiles
     * @return array<int, EstablishmentImage>
     *
     * @throws ValidationException When the live-image cap would be
     *                             exceeded or a photo duplicates one already on this establishment.
     */
    public function upload(User $objUploader, Listing $objListing, array $arrUploadedFiles, ?string $strCredit): array
    {
        // Fast, unlocked pre-check: fails obviously-over-the-cap requests
        // before spending time processing images. Not itself the guarantee
        // against a race — that's the locked re-check inside the
        // transaction below.
        $strEarlyRejectionMessage = $objListing->remainingSlotsErrorMessage(count($arrUploadedFiles));

        if ($strEarlyRejectionMessage !== null) {
            throw ValidationException::withMessages(['photos' => $strEarlyRejectionMessage]);
        }

        $objSourceRole = $this->_resolveSourceRole($objUploader);
        $objStatus = $objSourceRole->isAutoPublished() ? ImageStatus::Published : ImageStatus::Pending;

        // Processed first (outside the DB transaction — file I/O isn't
        // transactional), so a storage failure never leaves a half-written
        // database row behind.
        $arrPreparedImages = [];
        foreach ($arrUploadedFiles as $objUploadedFile) {
            $arrPreparedImages[] = $this->_prepareImage($objListing, $objUploadedFile);
        }

        $arrCreatedImages = [];

        try {
            DB::transaction(function () use ($objListing, $objUploader, $objSourceRole, $objStatus, $strCredit, $arrPreparedImages, &$arrCreatedImages) {
                // Locks the establishment's own row so two simultaneous
                // uploads for the same listing serialize here — the second
                // transaction blocks until the first commits (or rolls
                // back), then re-reads the now-current live count. This is
                // the authoritative cap check; the pre-check above is only
                // a fast-fail convenience.
                $objLockedListing = Listing::query()->lockForUpdate()->findOrFail($objListing->lst_id);

                $strLockedRejectionMessage = $objLockedListing->remainingSlotsErrorMessage(count($arrPreparedImages));

                if ($strLockedRejectionMessage !== null) {
                    throw ValidationException::withMessages(['photos' => $strLockedRejectionMessage]);
                }

                $intSortOrder = (int) $objLockedListing->establishmentImages()->max('img_sort_order') + 1;

                foreach ($arrPreparedImages as $arrPrepared) {
                    $arrCreatedImages[] = EstablishmentImage::query()->create([
                        'lst_id' => $objListing->lst_id,
                        'img_path' => $arrPrepared['path'],
                        'img_thumbnail_path' => $arrPrepared['thumbnailPath'],
                        'img_alt_text' => $objListing->lst_name,
                        'img_credit' => $strCredit,
                        'img_source_role' => $objSourceRole,
                        'img_status' => $objStatus,
                        'img_is_cover' => false,
                        'img_sort_order' => $intSortOrder++,
                        'img_hash' => $arrPrepared['hash'],
                        'img_uploaded_by' => $objUploader->usr_id,
                        'img_has_ownership_declared' => true,
                    ]);
                }
            });
        } catch (ValidationException $objValidationException) {
            // The cap (or a duplicate-hash check inside _prepareImage,
            // thrown before the transaction) rejected this batch — clean
            // up any files already written and surface the exact message.
            foreach ($arrPreparedImages as $arrPrepared) {
                Storage::disk(self::DISK)->delete([$arrPrepared['path'], $arrPrepared['thumbnailPath']]);
            }

            throw $objValidationException;
        } catch (\Throwable $objException) {
            Log::error('Failed to save establishment image upload.', ['exception' => $objException, 'listing_id' => $objListing->lst_id]);

            foreach ($arrPreparedImages as $arrPrepared) {
                Storage::disk(self::DISK)->delete([$arrPrepared['path'], $arrPrepared['thumbnailPath']]);
            }

            throw ValidationException::withMessages([
                'photos' => 'Something went wrong while saving your photos. Please try again.',
            ]);
        }

        foreach ($arrCreatedImages as $objCreatedImage) {
            OperationLogger::created($objUploader, 'establishment_image', $objCreatedImage->img_id, $objListing->mun_id, $objListing->lst_id, [
                'img_status' => $objCreatedImage->img_status->value,
                'img_source_role' => $objCreatedImage->img_source_role->value,
            ]);
        }

        return $arrCreatedImages;
    }

    /**
     * Replace: a brand-new Pending row pointed at $objOldImage via
     * img_replaces_id. The old image stays Published exactly as it is
     * until the replacement is approved (or Returned, leaving the old one
     * untouched — see EstablishmentImageReviewer). "Approval routing is the
     * same as a first upload," so a PTO replace publishes — and swaps in —
     * immediately, the same as a PTO upload would.
     *
     * @throws ValidationException When $objOldImage isn't Published, already
     *                             has a pending replacement, or the new photo fails validation/duplicate checks.
     */
    public function replace(User $objUploader, EstablishmentImage $objOldImage, UploadedFile $objUploadedFile, ?string $strCredit): EstablishmentImage
    {
        if (! $objOldImage->isPublished()) {
            throw ValidationException::withMessages(['photos' => 'Only a live photo can be replaced.']);
        }

        if ($objOldImage->replacedBy()->whereIn('img_status', [ImageStatus::Pending->value])->exists()) {
            throw ValidationException::withMessages(['photos' => 'This photo already has a replacement waiting for approval.']);
        }

        $objListing = $objOldImage->listing;
        $objSourceRole = $this->_resolveSourceRole($objUploader);
        $arrPrepared = $this->_prepareImage($objListing, $objUploadedFile);

        $objNewImage = EstablishmentImage::query()->create([
            'lst_id' => $objListing->lst_id,
            'img_path' => $arrPrepared['path'],
            'img_thumbnail_path' => $arrPrepared['thumbnailPath'],
            'img_alt_text' => $objOldImage->img_alt_text,
            'img_credit' => $strCredit,
            'img_source_role' => $objSourceRole,
            'img_status' => ImageStatus::Pending,
            'img_is_cover' => false,
            // Placed right after the image it would replace — its real,
            // inherited sort order only applies once approved.
            'img_sort_order' => $objOldImage->img_sort_order,
            'img_hash' => $arrPrepared['hash'],
            'img_uploaded_by' => $objUploader->usr_id,
            'img_has_ownership_declared' => true,
            'img_replaces_id' => $objOldImage->img_id,
        ]);

        OperationLogger::replaced($objUploader, 'establishment_image', $objNewImage->img_id, $objListing->mun_id, $objListing->lst_id, [
            'replaces_image_id' => $objOldImage->img_id,
        ]);

        if ($objSourceRole->isAutoPublished()) {
            $this->objReviewer->approve($objUploader, $objNewImage);
        }

        return $objNewImage;
    }

    private function _resolveSourceRole(User $objUploader): ImageSourceRole
    {
        return match ($objUploader->usr_role) {
            UserRole::Establishment => ImageSourceRole::Establishment,
            UserRole::Lgu => ImageSourceRole::Lgu,
            UserRole::PtoAdministrator => ImageSourceRole::Pto,
            default => throw ValidationException::withMessages(['photos' => 'Your account cannot upload photos.']),
        };
    }

    /**
     * Processes, hashes, duplicate-checks, and stores one file — returns
     * the data a database row needs, without writing one yet.
     *
     * @return array{path: string, thumbnailPath: string, hash: string}
     */
    private function _prepareImage(Listing $objListing, UploadedFile $objUploadedFile): array
    {
        $strProcessedFull = $this->objProcessor->processFull($objUploadedFile);
        $strProcessedThumbnail = $this->objProcessor->processThumbnail($objUploadedFile);
        $strHash = hash('sha256', $strProcessedFull);

        $this->_guardAgainstDuplicateHash($objListing, $strHash);

        $strExtension = $this->_extensionForUpload($objUploadedFile);
        // Random — never the uploaded filename — per I2/7B.
        $strRandomName = Str::random(40);
        $strPath = "establishment-images/{$objListing->lst_id}/{$strRandomName}.{$strExtension}";
        $strThumbnailPath = "establishment-images/{$objListing->lst_id}/{$strRandomName}_thumb.{$strExtension}";

        Storage::disk(self::DISK)->put($strPath, $strProcessedFull);
        Storage::disk(self::DISK)->put($strThumbnailPath, $strProcessedThumbnail);

        return ['path' => $strPath, 'thumbnailPath' => $strThumbnailPath, 'hash' => $strHash];
    }

    private function _guardAgainstDuplicateHash(Listing $objListing, string $strHash): void
    {
        $blnIsDuplicate = EstablishmentImage::query()
            ->where('lst_id', $objListing->lst_id)
            ->where('img_hash', $strHash)
            ->exists();

        if ($blnIsDuplicate) {
            throw ValidationException::withMessages([
                'photos' => 'This photo has already been uploaded for this establishment.',
            ]);
        }
    }

    private function _extensionForUpload(UploadedFile $objUploadedFile): string
    {
        $objFileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $strRealMimeType = finfo_file($objFileInfo, $objUploadedFile->getRealPath());
        finfo_close($objFileInfo);

        return match ($strRealMimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
