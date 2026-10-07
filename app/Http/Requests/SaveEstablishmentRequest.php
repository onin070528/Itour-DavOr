<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Validates and authorizes the LGU Add/Edit Establishment form — own municipality only, Category -> Type, optional photos.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Listing;
use App\Rules\EstablishmentTypeBelongsToCategory;
use App\Support\SecurityLogger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used by Lgu\EstablishmentsController::store() and ::update(). The form
 * has no municipality field: the municipality always comes from the
 * signed-in LGU account, and any submitted `municipality`/`municipality_id`
 * is simply never read (it is not in rules(), so it never reaches
 * validated()). On edit, a listing whose destination request is with the
 * PTO (Listing::hasLockedPublicContent()) keeps its public destination
 * content (Listing::PUBLIC_CONTENT_FIELDS) out of the rules entirely, so
 * those values cannot change through this form. A Published listing's
 * public fields are validated here but held for PTO review by
 * Lgu\EstablishmentsController::update(), never applied directly.
 */
class SaveEstablishmentRequest extends FormRequest
{
    /**
     * LGU only; on edit, only an establishment in the LGU's own
     * municipality. A cross-municipality attempt is security-logged and
     * answered with 403 (CLAUDE.md), before any validation runs.
     */
    public function authorize(): bool
    {
        $objUser = $this->user();

        if ($objUser === null || ! $objUser->isLgu() || $objUser->municipality_id === null) {
            return false;
        }

        $objListing = $this->_routeListing();

        if ($objListing === null) {
            return true;
        }

        $blnIsOwnMunicipality = $objListing->municipality_id !== null && $objListing->municipality_id === $objUser->municipality_id;

        if (! $blnIsOwnMunicipality) {
            SecurityLogger::accessDenied($objUser, 'municipality_scope', Listing::class, $objListing->municipality_id);

            return false;
        }

        // A destination is not edited through the establishment form.
        abort_if($objListing->category === 'destinations', 404);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $objCategory = Category::query()->find($this->input('cat_id'));
        $blnIsTourGuide = Listing::isTourGuideType($this->input('type'));

        $arrRules = [
            'cat_id' => [
                'required',
                'integer',
                Rule::exists('tblcategories', 'cat_id')
                    ->where('cat_is_active', true)
                    ->whereNot('cat_name', Category::DESTINATION_CATEGORY_NAME),
            ],
            ...self::listingFieldRules($objCategory, $blnIsTourGuide),
        ];

        // Summary comment: public destination content stays as approved while with the PTO or live.
        $objListing = $this->_routeListing();

        if ($objListing !== null && $objListing->hasLockedPublicContent()) {
            $arrRules = array_diff_key($arrRules, array_flip(Listing::PUBLIC_CONTENT_FIELDS));
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
     * Establishment detail rules shared by the LGU form and the PTO
     * directory form (Pto\DirectoryController) — everything except the
     * category and municipality fields, which each caller scopes itself.
     * A tour guide is list-only: no coordinates, and a license number is
     * required.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function listingFieldRules(?Category $objCategory, bool $blnIsTourGuide): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => EstablishmentTypeBelongsToCategory::rulesFor($objCategory),
            'owner_name' => ['nullable', 'string', 'max:255'],
            'barangay' => [$blnIsTourGuide ? 'nullable' : 'required', 'string', 'max:255'],
            'lat' => [$blnIsTourGuide ? 'prohibited' : 'nullable', 'numeric', 'between:-90,90'],
            'lng' => [$blnIsTourGuide ? 'prohibited' : 'nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string'],
            'contact_office' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'hours' => ['nullable', 'string', 'max:255'],
            'license_number' => [$blnIsTourGuide ? 'required' : 'nullable', 'string', 'max:255'],
            'accreditation_status' => ['nullable', 'string', 'max:255'],
            'category_note' => [$objCategory?->isOthers() ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cat_id.required' => 'Please choose a category.',
            'cat_id.exists' => 'Please choose a valid establishment category.',
            'photos.max' => 'You can add up to :max photos.',
            ...UploadEstablishmentImageRequest::photoMessages(),
        ];
    }

    /**
     * The validated establishment fields only (never photos), with every
     * optional field present so a cleared input saves as null.
     *
     * @return array<string, mixed>
     */
    public function establishmentFields(): array
    {
        $arrFieldNames = array_keys(self::listingFieldRules(null, false));
        $arrFieldNames[] = 'cat_id';

        $arrAllowedFields = array_intersect($arrFieldNames, array_keys($this->rules()));
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
