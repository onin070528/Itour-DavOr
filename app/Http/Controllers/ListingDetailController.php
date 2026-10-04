<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Public detail page for one destination or establishment — cover
 * image, a simple gallery of its PUBLISHED photos, and the listing's info.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Support\TourismCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListingDetailController extends Controller
{
    /**
     * A publicly visible listing is reachable by anyone — same rule Explore
     * applies to its own list (see ExploreController). A signed-in LGU/PTO
     * user allowed to view this listing (App\Policies\ListingPolicy::view())
     * may still open it even before it's published — PTO's "Preview as
     * public" link on a FOR_PTO_REVIEW listing relies on this.
     */
    public function show(Request $objRequest, Listing $listing): View
    {
        $blnCanPreview = $objRequest->user()?->can('view', $listing) ?? false;

        abort_unless($listing->isPubliclyVisible() || $blnCanPreview, 404);

        return view('listing-detail', [
            'listing' => $listing,
            'categoryLabel' => TourismCatalog::categoryLabel($listing->category),
            'categoryIcon' => TourismCatalog::categoryIcon($listing->category),
            'coverImageUrl' => $listing->publicCoverImageUrl(),
            'galleryImages' => $listing->publishedGalleryImages(),
        ]);
    }
}
