<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Look of the establishment check-in QR code — one place, read only by App\Services\QrCodeService.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Branded Or Plain
    |--------------------------------------------------------------------------
    |
    | true: iTOUR colors, rounded modules, circle eyes, and the logo in the
    | center. false: a plain black-on-white QR. Branding errors always fall
    | back to the plain QR automatically, whatever this is set to.
    |
    */

    'is_branded' => (bool) env('QR_IS_BRANDED', true),

    /*
    |--------------------------------------------------------------------------
    | Brand Colors
    |--------------------------------------------------------------------------
    |
    | Taken from the theme tokens in resources/css/app.css. Modules and eyes
    | stay dark on a white background (no inverted or pale QR) so phones can
    | read them reliably.
    |
    */

    'module_color' => '#0a3e3b',     // --color-primary-900
    'eye_color' => '#125d5a',        // --color-primary-700
    'background_color' => '#ffffff', // --color-sand-0
    'logo_backing_color' => '#f8f6ef', // --color-sand-50 (matches the logo's own background)

    /*
    |--------------------------------------------------------------------------
    | Module Roundness
    |--------------------------------------------------------------------------
    |
    | simple-qrcode's style('round', x) — 0 is square, closer to 1 is rounder.
    | Kept moderate so neighboring modules still read as solid blocks.
    |
    */

    'module_roundness' => 0.5,

    /*
    |--------------------------------------------------------------------------
    | Center Logo
    |--------------------------------------------------------------------------
    |
    | The iTOUR wordmark (git-tracked under storage/app/public/itour-images).
    | logo_width_ratio is the logo backing's width as a share of the QR's
    | width; with the wordmark's wide shape its area stays far below the ~20%
    | ceiling error correction level H can absorb.
    |
    */

    'logo_path' => storage_path('app/public/itour-images/itour.jpg'),

    'logo_width_ratio' => 0.34,

];
