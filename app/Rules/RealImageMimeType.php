<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Validates an uploaded file's real, content-sniffed MIME type — never the filename extension.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
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
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('Please choose a photo to upload.');

            return;
        }

        $objFileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $strRealMimeType = finfo_file($objFileInfo, $value->getRealPath());
        finfo_close($objFileInfo);

        $arrAllowedMimeTypes = config('establishment_images.allowed_mime_types');

        if (! in_array($strRealMimeType, $arrAllowedMimeTypes, true)) {
            $fail('Please use a JPG, PNG, or WebP photo.');
        }
    }
}
