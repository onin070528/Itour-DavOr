<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Validates a listing coordinate — numeric, in range, paired with
 * its other half, and inside the Davao Oriental coordinate guard.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Server-side authority for every coordinate a listing form submits
 * (attraction, establishment, and PTO directory forms). The Mapbox map
 * picker only helps the user choose a point; this rule decides whether it
 * is accepted. The guard comes from config/tourism_directory.php
 * 'coordinate_bounds' — never hard-code it. Callers use latitudeRules() /
 * longitudeRules() so every form applies the same list.
 */
class WithinDavaoOrientalBounds implements DataAwareRule, ValidationRule
{
    public const AXIS_LATITUDE = 'latitude';

    public const AXIS_LONGITUDE = 'longitude';

    /**
     * All submitted form data — used to check the other half of the pair.
     *
     * @var array<string, mixed>
     */
    private array $arrData = [];

    public function __construct(
        private readonly string $strAxis,
        private readonly string $strPartnerField,
    ) {}

    /**
     * The full rule list for a latitude field whose longitude is submitted
     * as $strLongitudeField. Empty is allowed (a record may have no location
     * yet); a value must be numeric, -90..90, paired, and inside the guard.
     *
     * @return array<int, mixed>
     */
    public static function latitudeRules(string $strLongitudeField = 'lng'): array
    {
        return ['nullable', 'numeric', 'between:-90,90', new self(self::AXIS_LATITUDE, $strLongitudeField)];
    }

    /**
     * The full rule list for a longitude field whose latitude is submitted
     * as $strLatitudeField. Same rules as latitudeRules(), range -180..180.
     *
     * @return array<int, mixed>
     */
    public static function longitudeRules(string $strLatitudeField = 'lat'): array
    {
        return ['nullable', 'numeric', 'between:-180,180', new self(self::AXIS_LONGITUDE, $strLatitudeField)];
    }

    /**
     * Whether a point lies inside the configured Davao Oriental coordinate
     * guard — the single bounds check, shared by this rule and by callers
     * that must tell a visitor they are outside the province (Find Near Me)
     * instead of rejecting the request.
     */
    public static function isInside(float $fltLatitude, float $fltLongitude): bool
    {
        return self::_isOnAxisInside(self::AXIS_LATITUDE, $fltLatitude)
            && self::_isOnAxisInside(self::AXIS_LONGITUDE, $fltLongitude);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->arrData = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Summary comment: a non-numeric value is already reported by the
        // 'numeric' rule; nothing more to check here.
        if (! is_numeric($value)) {
            return;
        }

        $mixPartner = $this->arrData[$this->strPartnerField] ?? null;
        $blnHasPartner = $mixPartner !== null && $mixPartner !== '';

        if (! $blnHasPartner) {
            $fail('Set both latitude and longitude, or leave both empty.');

            return;
        }

        if (! self::_isOnAxisInside($this->strAxis, (float) $value)) {
            $fail('The selected location must be inside Davao Oriental. Move the map marker into the province.');
        }
    }

    /**
     * Whether one coordinate value lies within the guard on its axis, read
     * from config/tourism_directory.php 'coordinate_bounds'.
     */
    private static function _isOnAxisInside(string $strAxis, float $fltValue): bool
    {
        $arrBounds = config('tourism_directory.coordinate_bounds');
        $blnIsLatitude = $strAxis === self::AXIS_LATITUDE;
        $fltMinimum = (float) ($blnIsLatitude ? $arrBounds['min_latitude'] : $arrBounds['min_longitude']);
        $fltMaximum = (float) ($blnIsLatitude ? $arrBounds['max_latitude'] : $arrBounds['max_longitude']);

        return $fltValue >= $fltMinimum && $fltValue <= $fltMaximum;
    }
}
