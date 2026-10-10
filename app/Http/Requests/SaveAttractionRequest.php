<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Validation and authorization for the LGU Add/Edit Tourist Attraction (destination-only) form.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Http\Requests;

use App\Models\Listing;
use App\Rules\WithinDavaoOrientalBounds;
use App\Services\AttractionRecordService;
use App\Support\SecurityLogger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Used by Lgu\AttractionsController::store() and ::update(). There is no
 * municipality, category, account, reporting, or QR field: the
 * municipality comes from the signed-in LGU account and a destination-only
 * record has none of the others. While a request is with the PTO, the
 * public destination content is left out of the rules (locked).
 */
class SaveAttractionRequest extends FormRequest
{
    /**
     * LGU only; on edit, only an LGU-managed attraction in the LGU's own
     * municipality. A cross-municipality attempt is security-logged and
     * answered with 403 (CLAUDE.md), before any validation runs; so is an
     * edit of a PTO-managed destination (ListingPolicy::update(), logged by
     * Gate::after in AppServiceProvider).
     */
    public function authorize(): bool
    {
        $objUser = $this->user();

        if ($objUser === null || ! $objUser->isLgu() || $objUser->mun_id === null) {
            return false;
        }

        $objListing = $this->_routeListing();

        if ($objListing === null) {
            return true;
        }

        $blnIsOwnMunicipality = $objListing->mun_id !== null && $objListing->mun_id === $objUser->mun_id;

        if (! $blnIsOwnMunicipality) {
            SecurityLogger::accessDenied($objUser, 'municipality_scope', Listing::class, $objListing->mun_id);

            return false;
        }

        // An establishment is not edited through the attraction form.
        abort_unless($objListing->isDestinationOnly(), 404);

        return $objUser->can('update', $objListing);
    }

    /**
     * Destination type: optional, one of config/tourism_directory.php
     * 'destination_types'. Shared with the PTO directory form.
     *
     * @return array<int, mixed>
     */
    public static function destinationTypeRules(): array
    {
        return ['nullable', 'string', Rule::in(config('tourism_directory.destination_types'))];
    }

    /**
     * Visitor information and entrance fee — destination content reviewed
     * by the PTO (Listing::PUBLIC_CONTENT_FIELDS). Shared with the PTO
     * directory form.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function visitorFieldRules(): array
    {
        return [
            'visitor_information' => ['nullable', 'string', 'max:5000'],
            'entrance_fee' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $arrRules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => self::destinationTypeRules(),
            'barangay' => ['required', 'string', 'max:255'],
            'lat' => WithinDavaoOrientalBounds::latitudeRules(),
            'lng' => WithinDavaoOrientalBounds::longitudeRules(),
            'description' => ['nullable', 'string', 'max:5000'],
            ...self::visitorFieldRules(),
            'contact_office' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'hours' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            // The existing lst_accreditation_status free-text field, with the establishment rule.
            'accreditation_status' => ['nullable', 'string', 'max:255'],
        ];

        $objListing = $this->_routeListing();

        // Summary comment: public destination content stays as submitted while the PTO reviews it.
        if ($objListing !== null && $objListing->hasLockedPublicContent()) {
            $arrLockedFields = array_map(fn (string $strField) => Str::startsWith($strField, 'lst_') ? Str::after($strField, 'lst_') : $strField, Listing::PUBLIC_CONTENT_FIELDS);
            $arrRules = array_diff_key($arrRules, array_flip($arrLockedFields));
        }

        // Summary comment: the Photos section exists on the Add form only —
        // existing photos are managed with the photo manager on the edit page.
        if ($objListing === null) {
            $arrRules['photos'] = ['nullable', 'array', 'max:'.(int) config('establishment_images.max_live_images_per_listing')];
            $arrRules['photos.*'] = UploadEstablishmentImageRequest::photoFileRules();
            // `accepted` is an implicit rule, so skip it entirely when no photos are sent.
            $arrRules['ownership_declared'] = ['exclude_without:photos', 'required', 'accepted'];
            $arrRules['credit'] = ['nullable', 'string', 'max:255'];
        }

        return $arrRules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'barangay.required' => 'Enter the barangay or address of the attraction.',
            'type.in' => 'Choose a destination type from the list.',
            'photos.max' => 'You can add up to :max photos.',
            ...UploadEstablishmentImageRequest::photoMessages(),
        ];
    }

    /**
     * The validated attraction fields only (never photos), with every
     * allowed field present so a cleared input saves as null.
     *
     * @return array<string, mixed>
     */
    public function attractionFields(): array
    {
        $arrAllowedFields = array_intersect(AttractionRecordService::FIELDS, array_keys($this->rules()));
        $arrValidated = $this->validated();
        $arrFields = [];

        foreach ($arrAllowedFields as $strField) {
            $arrFields[$strField] = $arrValidated[$strField] ?? null;
        } // end foreach allowed field

        return $arrFields;
    }

    /**
     * The {listing} being edited, or null on the Add form.
     */
    private function _routeListing(): ?Listing
    {
        $objListing = $this->route('listing');

        return $objListing instanceof Listing ? $objListing : null;
    }
}
