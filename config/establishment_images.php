<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Establishment photo upload limits (I2) — one place, not Settings.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed File Types
    |--------------------------------------------------------------------------
    |
    | Checked against the file's real, content-sniffed MIME type — never the
    | uploaded filename's extension. See App\Rules\RealImageMimeType.
    |
    */

    'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],

    /*
    |--------------------------------------------------------------------------
    | Size And Dimension Limits
    |--------------------------------------------------------------------------
    */

    'max_file_size_kb' => 5120,

    'min_width_px' => 1200,

    'min_height_px' => 800,

    /*
    |--------------------------------------------------------------------------
    | Live Image Cap
    |--------------------------------------------------------------------------
    |
    | "Live" counts both PUBLISHED and PENDING rows — an establishment can't
    | dodge the cap by stacking up pending uploads. A pending Replace is
    | excluded (see App\Models\Listing::liveImageCount()) since it swaps an
    | existing photo rather than adding one.
    |
    | L1: this single flat limit applies to every role, including the PTO.
    | A future per-role or per-category limit would live here as additional
    | keys (e.g. a 'max_live_images_per_category' map) — not built now.
    |
    */

    'max_live_images_per_listing' => 5,

    /*
    |--------------------------------------------------------------------------
    | Live Image Minimum
    |--------------------------------------------------------------------------
    |
    | Not actively enforced beyond "an upload must contain at least 1 file"
    | (App\Http\Requests\UploadEstablishmentImageRequest) — removing an
    | establishment's last photo is always allowed, for every role, with no
    | approval needed; public pages fall back to the category placeholder.
    | Kept here only so this number, too, is never hardcoded elsewhere.
    |
    */

    'min_live_images_per_listing' => 1,

    /*
    |--------------------------------------------------------------------------
    | Processing Output
    |--------------------------------------------------------------------------
    */

    'max_resized_width_px' => 1920,

    'thumbnail_width_px' => 400,

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Uploads per user per minute — see the 'establishment-image-upload'
    | limiter in App\Providers\AppServiceProvider.
    |
    */

    'uploads_per_minute' => 10,

    /*
    |--------------------------------------------------------------------------
    | Archive Retention
    |--------------------------------------------------------------------------
    |
    | I3: an Archived image's file is purged this many months after
    | img_archived_at — the database row itself is kept.
    |
    */

    'archive_retention_months' => 12,

];
