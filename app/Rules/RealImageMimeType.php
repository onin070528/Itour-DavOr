<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Validates an uploaded file's real, content-sniffed MIME type — never the filename
 * extension.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * A script renamed to .jpg still reports a real MIME type of
 * text/x-php (or similar) once its actual bytes are sniffed — this rule
 * reads the file's magic bytes via PHP's fileinfo extension, never the
 * client-supplied filename/extension, so that trick is rejected.
 */
class RealImageMimeType implements ValidationRule
{
    public function validate(string $strAttribute, mixed $objValue, Closure $fnFail): void
    {
        if (! $objValue instanceof UploadedFile) {
            $fnFail('Please choose a photo to upload.');

            return;
        }

        $objFileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $strRealMimeType = finfo_file($objFileInfo, $objValue->getRealPath());
        finfo_close($objFileInfo);

        $arrAllowedMimeTypes = config('establishment_images.allowed_mime_types');

        if (! in_array($strRealMimeType, $arrAllowedMimeTypes, true)) {
            $fnFail('Please use a JPG, PNG, or WebP photo.');
        }
    }
}
