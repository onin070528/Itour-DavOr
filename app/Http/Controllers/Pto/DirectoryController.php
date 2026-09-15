<?php

namespace App\Http\Controllers\Pto;

use App\Http\Controllers\Concerns\ManagesDestinationListings;
use App\Models\Listing;
use App\Support\TourismCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DirectoryController extends PtoController
{
    use ManagesDestinationListings;

    /**
     * Destinations: province-wide destination management.
     */
    public function destinations(Request $request): View
    {
        return $this->renderPto($request, 'pto.directory.destinations', 'directory.destinations', 'Destinations', [
            'destinations' => TourismCatalog::listings(),
            'categories' => TourismCatalog::categories(),
            'municipalities' => TourismCatalog::municipalities(),
        ]);
    }

    public function storeDestination(Request $request): RedirectResponse
    {
        $listing = $this->createDestination(
            $this->validatedDestinationFields($request),
            $this->validatedMunicipality($request),
        );

        return back()->with('toast', "{$listing->name} was added.");
    }

    public function updateDestination(Request $request, Listing $listing): RedirectResponse
    {
        abort_if($listing->category !== 'destinations', 404);

        $listing->update([
            ...$this->validatedDestinationFields($request),
            'municipality' => $this->validatedMunicipality($request),
        ]);

        return back()->with('toast', 'Destination saved.');
    }

    public function archiveDestination(Listing $listing): RedirectResponse
    {
        abort_if($listing->category !== 'destinations', 404);

        $listing->update(['status' => 'Archived']);

        return back()->with('toast', "{$listing->name} was archived.");
    }

    /**
     * Establishments: accredited tourism establishments, province-wide.
     */
    public function establishments(Request $request): View
    {
        // `status` (Active / Pending Review / Inactive) comes straight from
        // the listings table now — real accreditation state, editable via
        // Lgu\DirectoryController@verifyEstablishment.
        $listings = collect(TourismCatalog::listings())
            ->where('category', '!=', 'destinations')
            ->values();

        return $this->renderPto($request, 'pto.directory.establishments', 'directory.establishments', 'Establishments', [
            'listings' => $listings->all(),
            'categories' => TourismCatalog::categories(),
            'municipalities' => TourismCatalog::municipalities(),
        ]);
    }

    /**
     * Map: placeholder for the future Mapbox-backed tourism map.
     */
    public function map(Request $request): View
    {
        return $this->renderPto($request, 'pto.directory.map', 'directory.map', 'Tourism Map', [
            'listings' => TourismCatalog::listings(),
            'municipalities' => TourismCatalog::municipalities(),
        ]);
    }
}
