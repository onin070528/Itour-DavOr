<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Validates an establishment photo upload — real content type, size, dimensions, and the
 * ownership checkbox.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Requests;

use App\Models\Listing;
use App\Policies\ImagePolicy;
use App\Rules\MinimumImageDimensions;
use App\Rules\RealImageMimeType;
use App\Support\SecurityLogger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Upload screen is deliberately minimal (one or more photos, the ownership
 * checkbox, an optional credit field) — this is every rule behind it.
 * `listing_id` is always present, even for an Establishment user uploading
 * for its own record, so authorize() can check App\Policies\ImagePolicy
 * the same way for every role instead of three different code paths.
 */
class UploadEstablishmentImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $objListing = Listing::query()->find($this->input('listing_id'));

        if ($objListing === null) {
            return false;
        }

        $blnIsAllowed = app(ImagePolicy::class)->uploadFor($this->user(), $objListing);

        // ImagePolicy is called directly (not through the Gate), so a
        // denial is security-logged explicitly, as Gate::after logs others.
        if (! $blnIsAllowed) {
            SecurityLogger::lguPolicyDenied($this->user(), 'uploadFor', $objListing);
        }

        return $blnIsAllowed;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $intMinLiveImages = (int) config('establishment_images.min_live_images_per_listing');

        return [
            'listing_id' => ['required', 'integer', 'exists:tbl_listings,lst_id'],
            'photos' => ['required', 'array', 'min:'.$intMinLiveImages],
            'photos.*' => self::photoFileRules(),
            'ownership_declared' => ['required', 'accepted'],
            'credit' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photos.required' => 'Please select at least 1 photo.',
            'photos.min' => 'Please select at least 1 photo.',
            ...self::photoMessages(),
        ];
    }

    /**
     * The per-file rules for one uploaded photo — real (content-sniffed)
     * image type, size cap, minimum dimensions. The single definition,
     * also used by the LGU Add Establishment form's Photos section
     * (App\Http\Requests\SaveEstablishmentRequest).
     *
     * @return array<int, mixed>
     */
    public static function photoFileRules(): array
    {
        $intMaxFileSizeKb = (int) config('establishment_images.max_file_size_kb');

        return [
            'required',
            'file',
            'max:'.$intMaxFileSizeKb,
            new RealImageMimeType,
            new MinimumImageDimensions,
        ];
    }

    /**
     * Messages shared with every form that uses photoFileRules().
     *
     * @return array<string, string>
     */
    public static function photoMessages(): array
    {
        return [
            'photos.*.max' => 'This photo is too large. Please use a file under 5 MB.',
            'ownership_declared.required' => 'Please confirm you have permission to use this photo.',
            'ownership_declared.required_with' => 'Please confirm you have permission to use this photo.',
            'ownership_declared.accepted' => 'Please confirm you have permission to use this photo.',
        ];
    }

    /**
     * Fast feedback on the live-image cap (I2/L1/L2) before the file fields
     * are even touched — rejects the whole selection with one plain
     * message, same wording as the Service's authoritative, transaction-
     * locked re-check (App\Models\Listing::remainingSlotsErrorMessage()).
     */
    public function withValidator(Validator $objValidator): void
    {
        $objValidator->after(function (Validator $objValidator) {
            $objListing = Listing::query()->find($this->input('listing_id'));

            if ($objListing === null) {
                return;
            }

            $intSelectedCount = count($this->file('photos', []));
            $strRejectionMessage = $objListing->remainingSlotsErrorMessage($intSelectedCount);

            if ($strRejectionMessage !== null) {
                $objValidator->errors()->add('photos', $strRejectionMessage);
            }
        });
    }
}
