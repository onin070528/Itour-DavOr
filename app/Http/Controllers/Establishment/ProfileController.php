<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Handles the Establishment role's public tourism profile — editing
 * profile details and managing the photo gallery (upload, feature, remove).
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers\Establishment;

use App\Models\Listing;
use App\Models\ListingImage;
use App\Support\EstablishmentMockData;
use App\Support\TourismCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends EstablishmentController
{
    /**
     * Establishment Profile: view/edit the account's own public tourism
     * profile. The municipality is fixed to the account's assignment.
     */
    public function edit(Request $request): View
    {
        $name = $request->user()->organization_name;

        return $this->renderEstablishment($request, 'establishment.profile', 'establishment.profile', 'Establishment Profile', [
            'profile' => EstablishmentMockData::profile($name),
            'gallery' => EstablishmentMockData::galleryImages($name),
            'categories' => TourismCatalog::categories(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $listing = $this->ownListing($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(collect(TourismCatalog::categories())->pluck('slug')->reject(fn ($slug) => $slug === 'destinations'))],
            'address' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phone' => ['required', 'string', 'max:255'],
            'hours' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $listing->update([
                'name' => $data['name'],
                'category' => $data['category'],
                'barangay' => $data['address'],
                'description' => $data['description'] ?? null,
                'contact_phone' => $data['phone'],
                'hours' => $data['hours'],
                'email' => $data['email'] ?? null,
                'website' => $data['website'] ?? null,
            ]);

            // The account's organization_name is a display label only (the
            // join to $listing is via establishment_id, not this string) —
            // still kept in sync so EstablishmentMockData's name-keyed
            // lookups (profile/gallery/arrivals reads) don't go stale.
            if ($data['name'] !== $request->user()->organization_name) {
                $request->user()->update(['organization_name' => $data['name']]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to update establishment profile.', ['exception' => $e, 'listing_id' => $listing->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Establishment profile updated.');
    }

    public function storeImage(Request $request): RedirectResponse
    {
        $listing = $this->ownListing($request);

        $data = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        try {
            $path = $request->file('image')->store('itour-images', 'public');

            $listing->images()->create([
                'path' => basename($path),
                'caption' => null,
                'is_primary' => ! $listing->images()->exists(),
                'sort_order' => $listing->images()->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to store establishment photo.', ['exception' => $e, 'listing_id' => $listing->id]);

            return back()->with('toast', 'Something went wrong while uploading the photo. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Photo added.');
    }

    public function setPrimaryImage(Request $request, ListingImage $image): RedirectResponse
    {
        $listing = $this->ownListing($request);
        abort_unless($image->listing_id === $listing->id, 403);

        try {
            $listing->images()->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        } catch (\Throwable $e) {
            Log::error('Failed to set primary establishment photo.', ['exception' => $e, 'listing_id' => $listing->id, 'image_id' => $image->id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Featured photo updated.');
    }

    public function destroyImage(Request $request, ListingImage $image): RedirectResponse
    {
        $listing = $this->ownListing($request);
        abort_unless($image->listing_id === $listing->id, 403);

        $wasPrimary = $image->is_primary;

        try {
            Storage::disk('public')->delete('itour-images/'.$image->path);
            $image->delete();

            // Removing the featured photo shouldn't leave the gallery with none
            // — promote whatever's left, if anything.
            if ($wasPrimary) {
                $listing->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to remove establishment photo.', ['exception' => $e, 'listing_id' => $listing->id, 'image_id' => $image->id]);

            return back()->with('toast', 'Something went wrong while removing the photo. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Photo removed.');
    }

    /**
     * QR Code: the establishment-specific QR tourists scan to reach the
     * arrival self-registration form. Encodes a unique check-in URL keyed
     * on this establishment's directory listing id, so every establishment
     * gets its own distinct, scannable code (rendered client-side — see
     * resources/js/establishment.js).
     */
    public function qr(Request $request): View
    {
        $name = $request->user()->organization_name;
        $profile = EstablishmentMockData::profile($name);

        return $this->renderEstablishment($request, 'establishment.qr', 'establishment.qr', 'QR Code', [
            'profile' => $profile,
            'checkinUrl' => $profile ? route('lgu.establishmentQr', ['establishment' => $profile['id']]) : null,
        ]);
    }

    /**
     * Resolved via the account's establishment_id FK — not by matching
     * Listing.name against organization_name, which is a mutable display
     * string an account could otherwise rename to collide with a different
     * establishment's listing.
     */
    private function ownListing(Request $request): Listing
    {
        abort_if($request->user()->establishment_id === null, 403, 'Your account is not linked to an establishment yet.');

        return $request->user()->establishment()->firstOrFail();
    }
}
