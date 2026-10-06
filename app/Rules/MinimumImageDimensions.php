<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Validates an uploaded photo meets the configured minimum width/height (I2).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class MinimumImageDimensions implements ValidationRule
{
    public function validate(string $strAttribute, mixed $objValue, Closure $fnFail): void
    {
        if (! $objValue instanceof UploadedFile) {
            $fnFail('Please choose a photo to upload.');

            return;
        }

        $arrImageSize = @getimagesize($objValue->getRealPath());

        if ($arrImageSize === false) {
            $fnFail('This file is not a readable photo. Please use a different one.');

            return;
        }

        $intMinWidth = (int) config('establishment_images.min_width_px');
        $intMinHeight = (int) config('establishment_images.min_height_px');
        [$intWidth, $intHeight] = $arrImageSize;

        if ($intWidth < $intMinWidth || $intHeight < $intMinHeight) {
            $fnFail('This photo is too small. Please use a clearer one.');
        }
    }
}
