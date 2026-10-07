<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Province-wide Tourism Directory — destinations, establishments,
 * and Travel & Tours guides in one category-driven list/map view.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Pto;

use App\Http\Requests\SaveEstablishmentRequest;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Services\ListingPublishWorkflow;
use App\Support\OperationLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DirectoryController extends PtoController
{
    /**
     * One category-driven list/map view replacing the old separate
     * Destinations / Establishments / Map pages. Every filter (the
     * Categories panel, the list columns, the map pins) reads from
     * tblcategories and Listing::isQrEnabled() — adding or renaming a
     * category needs no code change here.
     */
    public function index(Request $request): View
    {
        $categories = Category::query()->orderBy('cat_sort_order')->get();

        $listings = Listing::query()
            ->with(['categoryRecord', 'establishmentUser', 'establishmentImages' => fn ($query) => $query->where('img_status', 'PUBLISHED')])
            ->orderBy('name')
            ->get();

        $categoryCounts = $listings->countBy(fn (Listing $listing) => $listing->cat_id);

        return $this->renderPto($request, 'pto.directory.index', 'directory', 'Tourism Directory', [
            'listings' => $listings,
            'categories' => $categories,
            'categoryCounts' => $categoryCounts,
            'municipalities' => Municipality::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $fields = $this->validatedListingFields($request);
        $municipalityId = $this->municipalityIdByName($fields['municipality']);
        $category = $this->legacyCategorySlug($fields['cat_id']);

        try {
            $listing = Listing::query()->create([
                ...$fields,
                'slug' => $this->uniqueListingSlug($fields['name']),
                'category' => $category,
                'municipality_id' => $municipalityId,
                // The PTO is the final authority in the publish workflow —
                // an establishment it creates itself needs no review queue
                // of its own, so it's PUBLISHED immediately. Destinations
                // keep the unchanged Active/Suspended/Archived vocabulary.
                'status' => $category === 'destinations' ? 'Active' : 'PUBLISHED',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create PTO directory listing.', ['exception' => $e]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::created($request->user(), 'establishment', $listing->id, $municipalityId, null, [
            'name' => $listing->name,
            'category' => $listing->categoryRecord?->cat_name,
            'municipality' => $listing->municipality,
        ]);

        return back()->with('toast', "{$listing->name} was added.");
    }

    public function update(Request $request, Listing $listing): RedirectResponse
    {
        $fields = $this->validatedListingFields($request);
        $municipalityId = $this->municipalityIdByName($fields['municipality']);
        $before = $listing->getOriginal();

        try {
            $listing->update([
                ...$fields,
                'category' => $this->legacyCategorySlug($fields['cat_id']),
                'municipality_id' => $municipalityId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to update PTO directory listing.', ['exception' => $e, 'listing_id' => $listing->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        // A4/A3: the PTO can edit LGU-owned records; every such edit is
        // audit-logged with the old and new values via OperationLogger.
        OperationLogger::updated($request->user(), 'establishment', $listing->id, $municipalityId, $listing->id, OperationLogger::diff($before, $listing));

        return back()->with('toast', 'Listing saved.');
    }

    /**
     * Activate/suspend/archive a destination, or suspend/archive an
     * establishment — always requires a reason, written into the operation
     * log alongside the old/new status. An establishment's PUBLISHED/
     * UNPUBLISHED state is never set here: that's App\Services\
     * ListingPublishWorkflow's job (publish()/unpublish()/
     * returnToLgu()), each gated by App\Policies\ListingPolicy::publish()
     * (P1 — only the PTO publishes).
     */
    public function updateStatus(Request $request, Listing $listing): RedirectResponse
    {
        $arrAllowedStatuses = $listing->category === 'destinations'
            ? ['Active', 'Suspended', 'Archived']
            : ['Suspended', 'Archived'];

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in($arrAllowedStatuses)],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $before = $listing->getOriginal();

        try {
            $listing->update(['status' => $data['status']]);
        } catch (\Throwable $e) {
            Log::error('Failed to update PTO directory listing status.', ['exception' => $e, 'listing_id' => $listing->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        OperationLogger::updated(
            $request->user(),
            'establishment',
            $listing->id,
            $listing->municipality_id,
            $listing->id,
            OperationLogger::diff($before, $listing),
            $data['reason'],
        );

        return back()->with('toast', "{$listing->name} is now {$data['status']}.");
    }

    /**
     * "Approve & Publish": FOR_PTO_REVIEW → PUBLISHED, or held changes to a
     * Published listing applied to the live listing, in one transaction.
     * P1: only the PTO may do this — see App\Policies\ListingPolicy::publish().
     */
    public function publish(Request $request, Listing $listing, ListingPublishWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('publish', $listing), 403);

        $blnIsChangeRequest = $listing->hasPendingChanges();

        try {
            $workflow->publish($request->user(), $listing);
        } catch (ValidationException $e) {
            return back()->with('toast', $e->validator->errors()->first())->with('toast_tone', 'danger');
        }

        return back()->with('toast', $blnIsChangeRequest ? "The changes to {$listing->name} are now live." : "{$listing->name} is now live.");
    }

    /**
     * "Return for Correction" (remarks required): FOR_PTO_REVIEW →
     * FOR_CORRECTION, or held changes to a Published listing returned while
     * the published version stays live. Every LGU user in that
     * municipality is notified and sees the remarks.
     */
    public function returnToLgu(Request $request, Listing $listing, ListingPublishWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('publish', $listing), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $workflow->returnToLgu($request->user(), $listing, $data['reason']);
        } catch (ValidationException $e) {
            return back()->with('toast', $e->validator->errors()->first())->with('toast_tone', 'danger');
        }

        return back()->with('toast', "{$listing->name} was returned to the LGU for correction.");
    }

    /**
     * PUBLISHED → UNPUBLISHED, with a reason — pulls a live listing back
     * for revision (distinct from Suspend/Archive, see updateStatus()).
     */
    public function unpublish(Request $request, Listing $listing, ListingPublishWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('publish', $listing), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $workflow->unpublish($request->user(), $listing, $data['reason']);
        } catch (ValidationException $e) {
            return back()->with('toast', $e->validator->errors()->first())->with('toast_tone', 'danger');
        }

        return back()->with('toast', "{$listing->name} was unpublished.");
    }

    /**
     * Category/type-dependent validation: coordinates are never accepted
     * for a Tour Guide (so it can never collect a QR identifier either — see
     * Listing::isQrEnabled()), a note is required for the "Others" category,
     * and a license number is required for a Tour Guide.
     *
     * @return array<string, mixed>
     */
    private function validatedListingFields(Request $request): array
    {
        $category = Category::query()->find($request->input('cat_id'));
        $isGuide = Listing::isTourGuideType($request->input('type'));

        // Summary comment: the establishment detail rules (including R13's
        // Category -> Type check) are shared with the LGU form; PTO adds the
        // unrestricted category and its municipality picker.
        $data = $request->validate([
            ...SaveEstablishmentRequest::listingFieldRules($category, $isGuide),
            'cat_id' => ['required', 'integer', 'exists:tblcategories,cat_id'],
            'municipality' => ['required', 'string', Rule::in(Municipality::query()->pluck('name'))],
        ]);

        $data['owner_name'] ??= null;
        $data['category_note'] ??= null;
        $data['type'] ??= null;
        $data['license_number'] ??= null;
        $data['accreditation_status'] ??= null;
        $data['lat'] ??= null;
        $data['lng'] ??= null;

        return $data;
    }

    private function municipalityIdByName(string $municipality): ?int
    {
        return Municipality::query()->where('name', $municipality)->value('id');
    }

    /**
     * Keeps the legacy free-text `category` column in sync with the new
     * `cat_id` relation (still read by App\Support\TourismCatalog and the
     * public Explore page until those callers move onto the relation).
     */
    private function legacyCategorySlug(int $categoryId): string
    {
        return Category::query()->find($categoryId)?->legacySlug() ?? 'others';
    }

    private function uniqueListingSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'listing';
        $slug = $base;
        $suffix = 2;

        while (Listing::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
