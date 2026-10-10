<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Dropdown options for an establishment's business hours, and
 * conversion between those dropdown values and the single `listings.hours`
 * display string (e.g. "Mon–Sun, 8:00 AM – 5:00 PM").
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessHours
{
    public const OPEN_24_HOURS = '24h';

    private const EARLIEST_OPENING_MINUTES = 6 * 60;

    /**
     * @return array<string, string>
     */
    public static function days(): array
    {
        return [
            'mon-sun' => 'Mon–Sun',
            'mon-fri' => 'Mon–Fri',
            'mon-sat' => 'Mon–Sat',
            'sat-sun' => 'Sat–Sun',
        ];
    }

    /**
     * Every half hour from 6:00 AM to 11:30 PM, keyed by 24-hour "HH:MM".
     * Overnight operations use the "Open 24 hours" option instead.
     *
     * @return array<string, string>
     */
    public static function times(): array
    {
        $arrTimes = [];

        for ($intMinutes = self::EARLIEST_OPENING_MINUTES; $intMinutes < 24 * 60; $intMinutes += 30) {
            $strKey = sprintf('%02d:%02d', intdiv($intMinutes, 60), $intMinutes % 60);
            $arrTimes[$strKey] = date('g:i A', strtotime($strKey));
        }

        return $arrTimes;
    }

    /**
     * Validation rules for the three business-hours dropdowns, shared by
     * every form that has them (LGU Accounts: establishment and destination).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(Request $objRequest): array
    {
        return [
            'hoursDays' => ['nullable', 'required_with:hoursOpen', Rule::in(array_keys(self::days()))],
            'hoursOpen' => ['nullable', 'required_with:hoursDays', Rule::in([...array_keys(self::times()), self::OPEN_24_HOURS])],
            'hoursClose' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $objRequest->filled('hoursOpen') && $objRequest->input('hoursOpen') !== self::OPEN_24_HOURS),
                Rule::in(array_keys(self::times())),
                'different:hoursOpen',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'hoursDays.required_with' => 'Choose which days it is open.',
            'hoursOpen.required_with' => 'Choose an opening time.',
            'hoursClose.required' => 'Choose a closing time.',
            'hoursClose.different' => 'The closing time must be different from the opening time.',
        ];
    }

    /**
     * Builds the stored `hours` string from the dropdown values, or null
     * when no days were chosen.
     */
    public static function format(?string $strDays, ?string $strOpens, ?string $strCloses): ?string
    {
        if (! $strDays || ! $strOpens) {
            return null;
        }

        $strDayLabel = self::days()[$strDays];

        if ($strOpens === self::OPEN_24_HOURS) {
            return "{$strDayLabel}, Open 24 hours";
        }

        return "{$strDayLabel}, ".self::times()[$strOpens].' – '.self::times()[$strCloses];
    }

    /**
     * Splits a stored `hours` string back into dropdown values so the Edit
     * modal can pre-select them. Strings that weren't produced by format()
     * (e.g. free text saved before the dropdowns existed) yield nulls.
     *
     * @return array{days: ?string, opens: ?string, closes: ?string}
     */
    public static function parse(?string $strHours): array
    {
        $arrEmpty = ['days' => null, 'opens' => null, 'closes' => null];

        if (! $strHours || ! preg_match('/^(.+?), (.+)$/u', $strHours, $parts)) {
            return $arrEmpty;
        }

        $strDays = array_search($parts[1], self::days(), true);

        if ($strDays === false) {
            return $arrEmpty;
        }

        if ($parts[2] === 'Open 24 hours') {
            return ['days' => $strDays, 'opens' => self::OPEN_24_HOURS, 'closes' => null];
        }

        $arrRange = explode(' – ', $parts[2]);
        $strOpens = array_search($arrRange[0], self::times(), true);
        $strCloses = array_search($arrRange[1] ?? '', self::times(), true);

        if ($strOpens === false || $strCloses === false) {
            return $arrEmpty;
        }

        return ['days' => $strDays, 'opens' => $strOpens, 'closes' => $strCloses];
    }
}
