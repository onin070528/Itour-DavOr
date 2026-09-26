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

class BusinessHours
{
    public const OPEN_24_HOURS = '24h';

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
     * Every half hour of the day, keyed by 24-hour "HH:MM".
     *
     * @return array<string, string>
     */
    public static function times(): array
    {
        $times = [];

        for ($minutes = 0; $minutes < 24 * 60; $minutes += 30) {
            $key = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            $times[$key] = date('g:i A', strtotime($key));
        }

        return $times;
    }

    /**
     * Builds the stored `hours` string from the dropdown values, or null
     * when no days were chosen.
     */
    public static function format(?string $days, ?string $opens, ?string $closes): ?string
    {
        if (! $days || ! $opens) {
            return null;
        }

        $dayLabel = self::days()[$days];

        if ($opens === self::OPEN_24_HOURS) {
            return "{$dayLabel}, Open 24 hours";
        }

        return "{$dayLabel}, ".self::times()[$opens].' – '.self::times()[$closes];
    }

    /**
     * Splits a stored `hours` string back into dropdown values so the Edit
     * modal can pre-select them. Strings that weren't produced by format()
     * (e.g. free text saved before the dropdowns existed) yield nulls.
     *
     * @return array{days: ?string, opens: ?string, closes: ?string}
     */
    public static function parse(?string $hours): array
    {
        $empty = ['days' => null, 'opens' => null, 'closes' => null];

        if (! $hours || ! preg_match('/^(.+?), (.+)$/u', $hours, $parts)) {
            return $empty;
        }

        $days = array_search($parts[1], self::days(), true);

        if ($days === false) {
            return $empty;
        }

        if ($parts[2] === 'Open 24 hours') {
            return ['days' => $days, 'opens' => self::OPEN_24_HOURS, 'closes' => null];
        }

        $range = explode(' – ', $parts[2]);
        $opens = array_search($range[0], self::times(), true);
        $closes = array_search($range[1] ?? '', self::times(), true);

        if ($opens === false || $closes === false) {
            return $empty;
        }

        return ['days' => $days, 'opens' => $opens, 'closes' => $closes];
    }
}
