<?php

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
