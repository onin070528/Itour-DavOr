<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : The single definition of the Manual/Paper monthly report fields — labels, validation, and totals.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace App\Support;

/**
 * PROVISIONAL. The official PTO paper monthly report form has not been
 * provided yet, so Manual Entry temporarily reuses the breakdown columns a
 * digital report already stores on monthly_arrival_reports (sex, age
 * group, residence). Those columns feed verification, consolidation, and
 * the official A4 report, so they are kept as-is for compatibility.
 *
 * When the official form arrives, update the groups below (and add any
 * new columns with a migration); the Manual Entry form, its validation,
 * and the stored totals all read from here, so nothing else needs to
 * change to pick the new fields up.
 */
final class ManualReportForm
{
    public const IS_PROVISIONAL = true;

    /**
     * Field groups as shown on the form: group label => [column => label].
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'By sex' => [
                'party_male' => 'Male',
                'party_female' => 'Female',
            ],
            'By age group' => [
                'party_adults' => 'Adults',
                'party_children' => 'Children',
                'party_seniors' => 'Seniors',
            ],
            'By residence' => [
                'party_local' => 'Local',
                'party_foreign' => 'Foreign',
            ],
        ];
    } // end groups

    /**
     * @return array<string, string> column => label, in form order.
     */
    public static function fields(): array
    {
        return array_merge(...array_values(self::groups()));
    } // end fields

    /**
     * Server-side rules: every field is a required whole number.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return array_map(fn () => ['required', 'integer', 'min:0', 'max:1000000'], self::fields());
    } // end rules

    /**
     * The columns to store from validated input, plus total_visitors —
     * derived on the server (Male + Female, the same rule as a digital
     * report), never typed in.
     *
     * @param  array<string, mixed>  $arrValidated
     * @return array<string, int>
     */
    public static function figures(array $arrValidated): array
    {
        $arrFigures = [];

        foreach (array_keys(self::fields()) as $strColumn) {
            $arrFigures[$strColumn] = (int) $arrValidated[$strColumn];
        }

        $arrFigures['total_visitors'] = $arrFigures['party_male'] + $arrFigures['party_female'];

        return $arrFigures;
    } // end figures
}
