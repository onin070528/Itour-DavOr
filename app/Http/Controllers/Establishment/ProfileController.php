<?php

namespace App\Http\Controllers\Establishment;

use App\Models\Listing;
use App\Models\ListingImage;
use App\Support\EstablishmentMockData;
use App\Support\TourismCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        // The account's organization_name is how every EstablishmentMockData
        // lookup finds this listing back — keep it in sync so renaming the
        // listing here doesn't orphan the account from its own profile.
        if ($data['name'] !== $request->user()->organization_name) {
            $request->user()->update(['organization_name' => $data['name']]);
        }

        return back()->with('toast', 'Establishment profile updated.');
    }

    public function storeImage(Request $request): RedirectResponse
    {
        $listing = $this->ownListing($request);

        $data = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $path = $request->file('image')->store('itour-images', 'public');

        $listing->images()->create([
            'path' => basename($path),
            'caption' => null,
            'is_primary' => ! $listing->images()->exists(),
            'sort_order' => $listing->images()->count(),
        ]);

        return back()->with('toast', 'Photo added.');
    }

    public function setPrimaryImage(Request $request, ListingImage $image): RedirectResponse
    {
        $listing = $this->ownListing($request);
        abort_unless($image->listing_id === $listing->id, 403);

        $listing->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return back()->with('toast', 'Featured photo updated.');
    }

    public function destroyImage(Request $request, ListingImage $image): RedirectResponse
    {
        $listing = $this->ownListing($request);
        abort_unless($image->listing_id === $listing->id, 403);

        $wasPrimary = $image->is_primary;

        Storage::disk('public')->delete('itour-images/'.$image->path);
        $image->delete();

        // Removing the featured photo shouldn't leave the gallery with none
        // — promote whatever's left, if anything.
        if ($wasPrimary) {
            $listing->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
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

    private function ownListing(Request $request): Listing
    {
        return Listing::query()->where('name', $request->user()->organization_name)->firstOrFail();
    }
}
