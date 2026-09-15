<?php

namespace App\Http\Controllers\Lgu;

use App\Http\Controllers\Concerns\ManagesDestinationListings;
use App\Models\Listing;
use App\Support\LguMockData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DirectoryController extends LguController
{
    use ManagesDestinationListings;

    /**
     * Destinations: full management access, scoped to this municipality.
     * New/edited destinations are always saved under the LGU's own
     * municipality — there is no municipality selector in the form.
     */
    public function destinations(Request $request): View
    {
        $municipality = $request->user()->organization_subtitle;

        return $this->renderLgu($request, 'lgu.directory.destinations', 'directory.destinations', 'Destinations', [
            'municipality' => $municipality,
            'destinations' => LguMockData::destinations($municipality),
        ]);
    }

    public function storeDestination(Request $request): RedirectResponse
    {
        $listing = $this->createDestination(
            $this->validatedDestinationFields($request),
            $request->user()->organization_subtitle,
        );

        return back()->with('toast', "{$listing->name} was added.");
    }

    public function updateDestination(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorizeOwnMunicipality($request, $listing);
        abort_if($listing->category !== 'destinations', 404);

        $listing->update($this->validatedDestinationFields($request));

        return back()->with('toast', 'Destination saved.');
    }

    public function archiveDestination(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorizeOwnMunicipality($request, $listing);
        abort_if($listing->category !== 'destinations', 404);

        $listing->update(['status' => 'Archived']);

        return back()->with('toast', "{$listing->name} was archived.");
    }

    /**
     * Establishments: view/monitor access only — no edit or delete controls.
     */
    public function establishments(Request $request): View
    {
        $municipality = $request->user()->organization_subtitle;

        return $this->renderLgu($request, 'lgu.directory.establishments', 'directory.establishments', 'Establishments', [
            'municipality' => $municipality,
            'listings' => LguMockData::establishments($municipality),
        ]);
    }

    public function verifyEstablishment(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorizeOwnMunicipality($request, $listing);

        abort_if($listing->category === 'destinations', 404);

        $listing->update(['status' => 'Active']);

        return back()->with('toast', "{$listing->name} marked as verified.");
    }

    /**
     * Every write here must stay inside the account's own municipality —
     * this is the LGU directory's whole reason for having a separate
     * controller from PTO's (province-wide) equivalent.
     */
    private function authorizeOwnMunicipality(Request $request, Listing $listing): void
    {
        abort_unless($listing->municipality === $request->user()->organization_subtitle, 403);
    }
}
