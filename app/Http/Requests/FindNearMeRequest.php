<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Validation for Find Near Me — the visitor's temporary location,
 * radius, category, and page — checked before any database search.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Requests;

use App\Models\Category;
use App\Rules\WithinDavaoOrientalBounds;
use App\Services\NearbySearchService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Public (no account). The location arrives in a POST JSON body only and is
 * rounded here to the configured precision (about 4 decimal places) before
 * it is used. A failure always answers with JSON — never the usual
 * redirect, which would flash the submitted coordinates into the session
 * as "old input". Error messages never repeat the submitted values.
 */
class FindNearMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', Rule::in(config('tourism_directory.nearby.radius_options_km'))],
            'category' => ['nullable', 'string', Rule::in(self::categorySlugs())],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.*' => 'We could not read your location. Please try again.',
            'longitude.*' => 'We could not read your location. Please try again.',
            'radius.in' => 'Choose one of the listed distances.',
            'category.in' => 'Choose one of the listed categories.',
        ];
    }

    /**
     * After the basic checks pass: the rounded location must lie inside the
     * Davao Oriental coordinate guard (the same check the listing forms
     * use), so no search runs for a point outside the province.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $objValidator) {
                if ($objValidator->errors()->isNotEmpty()) {
                    return;
                }

                if (! WithinDavaoOrientalBounds::isInside($this->roundedLatitude(), $this->roundedLongitude())) {
                    $objValidator->errors()->add('location', 'You appear to be outside Davao Oriental, so there are no nearby places to show. You can still browse the full directory.');
                }
            },
        ];
    }

    /**
     * The visitor's latitude rounded to the configured precision — the only
     * form of it the search ever uses.
     */
    public function roundedLatitude(): float
    {
        return app(NearbySearchService::class)->roundCoordinate((float) $this->input('latitude'));
    }

    /**
     * The visitor's longitude rounded to the configured precision.
     */
    public function roundedLongitude(): float
    {
        return app(NearbySearchService::class)->roundCoordinate((float) $this->input('longitude'));
    }

    /**
     * The chosen category's tbl_categories record, if any.
     */
    public function selectedCategory(): ?Category
    {
        $strSlug = $this->validated('category');

        return $strSlug === null
            ? null
            : Category::query()->active()->get()->first(fn (Category $objCategory) => $objCategory->legacySlug() === $strSlug);
    }

    /**
     * Always JSON, never a redirect with flashed input (which would put the
     * submitted location in the session), and never cached.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()
                ->json(['message' => 'The location search could not be completed.', 'errors' => $validator->errors()], 422)
                ->header('Cache-Control', 'no-store, private')
        );
    }

    /**
     * Category slugs a visitor may filter by (the active tbl_categories).
     *
     * @return array<int, string>
     */
    private static function categorySlugs(): array
    {
        return Category::query()->active()->get()->map(fn (Category $objCategory) => $objCategory->legacySlug())->all();
    }
}
