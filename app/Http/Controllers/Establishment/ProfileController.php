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
        $name = $request->user()->usr_organization_name;

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
                'lst_name' => $data['name'],
                'lst_category' => $data['category'],
                'lst_barangay' => $data['address'],
                'lst_description' => $data['description'] ?? null,
                'lst_contact_phone' => $data['phone'],
                'lst_hours' => $data['hours'],
                'lst_email' => $data['email'] ?? null,
                'lst_website' => $data['website'] ?? null,
            ]);

            // The account's usr_organization_name is a display label only (the
            // join to $listing is via lst_id, not this string) —
            // still kept in sync so EstablishmentMockData's name-keyed
            // lookups (profile/gallery/arrivals reads) don't go stale.
            if ($data['name'] !== $request->user()->usr_organization_name) {
                $request->user()->update(['usr_organization_name' => $data['name']]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to update establishment profile.', ['exception' => $e, 'lst_id' => $listing->lst_id]);

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
                'lsi_path' => basename($path),
                'lsi_caption' => null,
                'lsi_is_primary' => ! $listing->images()->exists(),
                'lsi_sort_order' => $listing->images()->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to store establishment photo.', ['exception' => $e, 'lst_id' => $listing->lst_id]);

            return back()->with('toast', 'Something went wrong while uploading the photo. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Photo added.');
    }

    public function setPrimaryImage(Request $request, ListingImage $image): RedirectResponse
    {
        $listing = $this->ownListing($request);
        abort_unless($image->lst_id === $listing->lst_id, 403);

        try {
            $listing->images()->update(['lsi_is_primary' => false]);
            $image->update(['lsi_is_primary' => true]);
        } catch (\Throwable $e) {
            Log::error('Failed to set primary establishment photo.', ['exception' => $e, 'lst_id' => $listing->lst_id, 'image_id' => $image->lsi_id]);

            return back()->with('toast', 'Something went wrong while saving. Please try again.')->with('toast_tone', 'danger');
        }

        return back()->with('toast', 'Featured photo updated.');
    }

    public function destroyImage(Request $request, ListingImage $image): RedirectResponse
    {
        $listing = $this->ownListing($request);
        abort_unless($image->lst_id === $listing->lst_id, 403);

        $wasPrimary = $image->lsi_is_primary;

        try {
            Storage::disk('public')->delete('itour-images/'.$image->lsi_path);
            $image->delete();

            // Removing the featured photo shouldn't leave the gallery with none
            // — promote whatever's left, if anything.
            if ($wasPrimary) {
                $listing->images()->orderBy('lsi_sort_order')->first()?->update(['lsi_is_primary' => true]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to remove establishment photo.', ['exception' => $e, 'lst_id' => $listing->lst_id, 'image_id' => $image->lsi_id]);

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
        $name = $request->user()->usr_organization_name;
        $profile = EstablishmentMockData::profile($name);

        return $this->renderEstablishment($request, 'establishment.qr', 'establishment.qr', 'QR Code', [
            'profile' => $profile,
            'checkinUrl' => $profile ? route('lgu.establishmentQr', ['establishment' => $profile['id']]) : null,
        ]);
    }

    /**
     * Resolved via the account's lst_id FK — not by matching
     * Listing.name against usr_organization_name, which is a mutable display
     * string an account could otherwise rename to collide with a different
     * establishment's listing.
     */
    private function ownListing(Request $request): Listing
    {
        abort_if($request->user()->lst_id === null, 403, 'Your account is not linked to an establishment yet.');

        return $request->user()->establishment()->firstOrFail();
    }
}
