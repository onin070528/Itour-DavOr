<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Renders the public Privacy Notice page — what iTOUR collects, why,
 * who sees it, and visitor rights under the Data Privacy Act.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers;

use Illuminate\View\View;

class PrivacyController extends Controller
{
    public function show(): View
    {
        return view('privacy');
    }
}
