<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Public province-wide hotline directory, grouped by agency type.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers;

use App\Models\Hotline;
use Illuminate\View\View;

class HotlinesController extends Controller
{
    /**
     * Province-wide emergency hotlines, grouped by agency type — PTO-
     * managed only (see Pto\HotlinesController). Cached offline by the
     * service worker configured in vite.config.js (VitePWA).
     */
    public function index(): View
    {
        $hotlines = Hotline::query()->active()->get()->groupBy('hot_agency_type');

        return view('hotlines', [
            'hotlines' => $hotlines,
        ]);
    }
}
