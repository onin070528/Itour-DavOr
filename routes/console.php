<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Console route definitions and scheduled commands.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// I3: files of Archived establishment images are purged once past
// retention — daily is frequent enough for a monthly-scale retention
// window (config('establishment_images.archive_retention_months')).
Schedule::command('establishment-images:purge')->daily();

// Establishments are reminded 3 days before, on, and after the 15th when
// last month's report has not been sent to their LGU yet.
Schedule::command('reports:send-reminders')->dailyAt('08:00');
