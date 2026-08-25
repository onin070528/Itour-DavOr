<?php

namespace App\Http\Controllers\Lgu;

use App\Support\LguMockData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DirectoryController extends LguController
{
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
}
