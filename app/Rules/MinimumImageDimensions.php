<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Validates an uploaded photo meets the configured minimum width/height (I2).
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class MinimumImageDimensions implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('Please choose a photo to upload.');

            return;
        }

        $arrImageSize = @getimagesize($value->getRealPath());

        if ($arrImageSize === false) {
            $fail('This file is not a readable photo. Please use a different one.');

            return;
        }

        $intMinWidth = (int) config('establishment_images.min_width_px');
        $intMinHeight = (int) config('establishment_images.min_height_px');
        [$intWidth, $intHeight] = $arrImageSize;

        if ($intWidth < $intMinWidth || $intHeight < $intMinHeight) {
            $fail('This photo is too small. Please use a clearer one.');
        }
    }
}
