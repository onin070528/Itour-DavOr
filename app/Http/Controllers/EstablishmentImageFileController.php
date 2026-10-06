<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: The only route that ever serves an establishment image file — enforces who may see what.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers;

use App\Models\EstablishmentImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files live on the 'local' disk (storage/app/private — outside the public
 * webroot, never directly reachable, never executed) and are only ever
 * reachable through this one controlled route. A PUBLISHED image's file is
 * served to anyone with long cache headers; anything else (Pending,
 * Returned, Archived) only to a signed-in user App\Policies\ImagePolicy
 * allows to see it.
 */
class EstablishmentImageFileController extends Controller
{
    /**
     * $image/$variant — not the usual Hungarian-prefixed $objImage/$strVariant
     * — because Laravel's implicit route-model binding matches a controller
     * parameter to its route segment by name (framework-required; ITD
     * naming is exempt here).
     */
    public function show(Request $objRequest, EstablishmentImage $image, string $variant): StreamedResponse
    {
        abort_unless(in_array($variant, ['full', 'thumbnail'], true), 404);

        if (! $image->isPublished()) {
            abort_unless($objRequest->user()?->can('view', $image), 404);
        }

        // 404 for a Published image whose establishment isn't publicly
        // visible right now (Suspended/Archived/UNPUBLISHED/not yet
        // published) — "all their images are hidden on public pages"
        // applies here too, not only to the listing pages that link to them.
        if ($image->isPublished() && ! $image->listing->isPubliclyVisible() && ! $objRequest->user()?->can('view', $image)) {
            abort(404);
        }

        $strPath = $variant === 'thumbnail' ? $image->img_thumbnail_path : $image->img_path;

        abort_unless(Storage::disk('local')->exists($strPath), 404);

        $arrHeaders = $image->isPublished()
            ? ['Cache-Control' => 'public, max-age=604800, immutable']
            : ['Cache-Control' => 'private, no-store'];

        return Storage::disk('local')->response($strPath, null, $arrHeaders);
    }
}
