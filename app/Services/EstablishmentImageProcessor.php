<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Re-encodes an uploaded establishment photo (stripping EXIF/GPS), resizes it, and builds a thumbnail.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;

/**
 * The GD driver is deliberately used (not Imagick) — re-encoding through GD
 * discards EXIF/metadata, including GPS location, as an inherent side
 * effect of how GD reads and writes image data; nothing is explicitly
 * "stripped," there is simply nothing left to carry it. Covered by
 * tests/Feature/EstablishmentImageProcessingTest.php.
 */
class EstablishmentImageProcessor
{
    public function __construct(private readonly ImageManager $objImageManager = new ImageManager(Driver::class)) {}

    /**
     * Re-encodes and resizes $objUploadedFile to a maximum width of 1920px
     * (never upscaled), in its original format family.
     *
     * @return string Binary contents of the processed, full-size image.
     */
    public function processFull(UploadedFile $objUploadedFile): string
    {
        $intMaxWidth = (int) config('establishment_images.max_resized_width_px');

        $objImage = $this->objImageManager->decodePath($objUploadedFile->getRealPath());
        $objImage = $objImage->scaleDown(width: $intMaxWidth);

        return (string) $objImage->encode($this->_resolveEncoder($objUploadedFile));
    }

    /**
     * Builds a thumbnail from the ORIGINAL upload (not the already-resized
     * full copy) so a thumbnail is never upscaled above the source.
     *
     * @return string Binary contents of the thumbnail.
     */
    public function processThumbnail(UploadedFile $objUploadedFile): string
    {
        $intThumbnailWidth = (int) config('establishment_images.thumbnail_width_px');

        $objImage = $this->objImageManager->decodePath($objUploadedFile->getRealPath());
        $objImage = $objImage->scaleDown(width: $intThumbnailWidth);

        return (string) $objImage->encode($this->_resolveEncoder($objUploadedFile));
    }

    /**
     * Picks the encoder matching the upload's real (content-sniffed) MIME
     * type, so a re-encoded JPG stays a JPG and so on — never defaults to
     * JPEG for an unrecognized type, since RealImageMimeType has already
     * rejected anything outside JPG/PNG/WebP by the time this runs.
     */
    private function _resolveEncoder(UploadedFile $objUploadedFile): EncoderInterface
    {
        $objFileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $strRealMimeType = finfo_file($objFileInfo, $objUploadedFile->getRealPath());
        finfo_close($objFileInfo);

        return match ($strRealMimeType) {
            'image/png' => new PngEncoder,
            'image/webp' => new WebpEncoder(quality: 85),
            default => new JpegEncoder(quality: 85),
        };
    }
}
