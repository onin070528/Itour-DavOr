<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Shared validation rules and saving for one arrival (group) — used by both the public QR check-in and staff Arrival Recording.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Services;

use App\Enums\ArrivalOriginScope;
use App\Enums\ArrivalSource;
use App\Models\Arrival;
use App\Models\Listing;
use App\Models\Municipality;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One group = one arrival row: a lead visitor plus everyone with them as
 * counts (Local/Foreign x Male/Female x Adults/Kids/Seniors, rolled up by
 * the form into seven flat totals). The QR form (CheckinController) and
 * the staff form (Establishment\ArrivalsController) each add their own
 * fields (date, name/contact) but share these headcount/origin rules and
 * this save, so both paths always store arrivals the same way.
 */
class ArrivalRecorder
{
    /** The seven flat headcount totals both forms submit. */
    public const HEADCOUNT_FIELDS = ['male', 'female', 'adults', 'children', 'seniors', 'local', 'foreign'];

    /** Visit types, stored as-is in arrivals.visit_type. */
    public const VISIT_TYPES = ['Daytour', 'Overnight'];

    /** Upper bound per headcount field — far above any real group, blocks absurd values. */
    public const MAX_COUNT = 999;

    /**
     * Validation rules for the visit type, headcount, origin, and the
     * optional visitor name, contact number, and remarks. Origin is required:
     * local guests must say whether they come from within Davao Oriental
     * (naming one of its municipalities) or from outside it (naming their
     * home province), and foreign guests must name their country. Each origin
     * field is only accepted when that part of the group is non-empty.
     *
     * @return array<string, array<int, mixed>>
     */
    public function getRules(Request $objRequest): array
    {
        $strOriginScope = $objRequest->input('localOriginScope');
        $blnIsWithinProvince = $strOriginScope === ArrivalOriginScope::WithinProvince->value;
        $blnIsOutsideProvince = $strOriginScope === ArrivalOriginScope::OutsideProvince->value;

        $blnHasLocalGuests = (int) $objRequest->input('local', 0) > 0;
        $blnHasForeignGuests = (int) $objRequest->input('foreign', 0) > 0;

        $arrRules = [
            'visitorName' => ['nullable', 'string', 'max:255'],
            'visitorContact' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'visitType' => ['required', Rule::in(self::VISIT_TYPES)],
            'localOriginScope' => [
                'nullable',
                Rule::requiredIf($blnHasLocalGuests),
                Rule::enum(ArrivalOriginScope::class),
                Rule::prohibitedIf((int) $objRequest->input('local', 0) <= 0),
            ],
            'localOriginPlace' => array_values(array_filter([
                'nullable',
                'string',
                'max:100',
                Rule::requiredIf($blnIsWithinProvince || $blnIsOutsideProvince),
                Rule::prohibitedIf(! $blnIsWithinProvince && ! $blnIsOutsideProvince),
                $blnIsWithinProvince ? Rule::in($this->getProvinceMunicipalityNames()->all()) : null,
            ])),
            'foreignCountry' => [
                'nullable',
                Rule::requiredIf($blnHasForeignGuests),
                'string',
                'max:100',
                Rule::prohibitedIf(! $blnHasForeignGuests),
            ],
        ];

        // Summary comment: every headcount total is a whole number from 0 to MAX_COUNT.
        foreach (self::HEADCOUNT_FIELDS as $strField) {
            $arrRules[$strField] = ['nullable', 'integer', 'min:0', 'max:'.self::MAX_COUNT];
        } // end foreach headcount field

        return $arrRules;
    }

    /**
     * Friendly messages for the origin rules, shared by every form that
     * validates with getRules().
     *
     * @return array<string, string>
     */
    public function getMessages(): array
    {
        return [
            'localOriginScope.required' => 'Please tell us whether your local guests are from within or outside Davao Oriental.',
            'localOriginPlace.required' => 'Please choose the municipality or enter the home province of your local guests.',
            'foreignCountry.required' => 'Please enter the home country of your foreign guests.',
        ];
    }

    /**
     * Counting rules, as an after-validation hook. The grid includes the
     * lead visitor, so the group's total (male + female) must be at least
     * 1. Every guest is counted once per breakdown, so the age groups and
     * the Local/Foreign split must add up to that same total — the same
     * rule OfficialReportBuilder enforces before generating a report, so an
     * inconsistent row can never get saved and block a report later.
     */
    public function checkHeadcount(Validator $objValidator, Request $objRequest): void
    {
        $intGenderTotal = $this->_sumOf($objRequest, ['male', 'female']);
        $intAgeTotal = $this->_sumOf($objRequest, ['adults', 'children', 'seniors']);
        $intOriginTotal = $this->_sumOf($objRequest, ['local', 'foreign']);

        if ($intGenderTotal < 1) {
            $objValidator->errors()->add('male', 'Add at least one guest to the headcount.');

            return;
        }

        $blnIsConsistent = $intAgeTotal === $intGenderTotal && $intOriginTotal === $intGenderTotal;

        if (! $blnIsConsistent) {
            $objValidator->errors()->add('male', 'The headcount does not add up: Male + Female, the age groups, and Local + Foreign must all give the same total.');
        }
    }

    /**
     * Sum of the given headcount inputs, each read as a whole number.
     *
     * @param  array<int, string>  $arrFields
     */
    private function _sumOf(Request $objRequest, array $arrFields): int
    {
        $intSum = 0;

        foreach ($arrFields as $strField) {
            $intSum += (int) $objRequest->input($strField, 0);
        } // end foreach field

        return $intSum;
    }

    /**
     * Saves one validated arrival against $objListing.
     *
     * @param  array<string, mixed>  $arrData  validated input (getRules() fields plus visitorName/visitorContact)
     */
    public function record(Listing $objListing, array $arrData, ArrivalSource $objSource, ?int $intRecordedBy, string $strDate): Arrival
    {
        $arrCounts = [];

        foreach (self::HEADCOUNT_FIELDS as $strField) {
            $arrCounts[$strField] = (int) ($arrData[$strField] ?? 0);
        } // end foreach headcount field

        return $objListing->arrivals()->create([
            'arr_source' => $objSource,
            'recorded_by' => $intRecordedBy,
            'arr_date' => $strDate,
            'arr_visitor_name' => $arrData['visitorName'] ?? null,
            'arr_visitor_contact' => $arrData['visitorContact'] ?? null,
            'arr_visit_type' => $arrData['visitType'],
            'arr_remarks' => $arrData['remarks'] ?? null,
            'arr_party_male' => $arrCounts['male'],
            'arr_party_female' => $arrCounts['female'],
            'arr_party_adults' => $arrCounts['adults'],
            'arr_party_children' => $arrCounts['children'],
            'arr_party_seniors' => $arrCounts['seniors'],
            'arr_party_local' => $arrCounts['local'],
            'arr_party_foreign' => $arrCounts['foreign'],
            'arr_party_size' => $arrCounts['male'] + $arrCounts['female'],
            'arr_local_origin_scope' => $arrData['localOriginScope'] ?? null,
            'arr_local_origin_place' => $arrData['localOriginPlace'] ?? null,
            'arr_foreign_country' => $arrData['foreignCountry'] ?? null,
            'arr_status' => 'Recorded',
        ]);
    }

    /**
     * Davao Oriental's municipalities/city, for the "Within Davao Oriental"
     * picker and its validation (one list, from the municipalities table).
     *
     * @return Collection<int, string>
     */
    public function getProvinceMunicipalityNames(): Collection
    {
        return Municipality::query()->orderBy('mun_name')->pluck('mun_name');
    }
}
