<?php

namespace App\Http\Controllers\Pto;

use App\Support\TourismCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DirectoryController extends PtoController
{
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

    /**
     * Establishments: accredited tourism establishments, province-wide.
     */
    public function establishments(Request $request): View
    {
        // Accreditation status is mock data for now — a handful of
        // establishments are shown Pending/Inactive so the status
        // presentation has something to demonstrate.
        $pending = ['dahican-surf-guides', 'delicacies-hub'];
        $inactive = ['tourist-transport-terminal'];

        $listings = collect(TourismCatalog::listings())
            ->where('category', '!=', 'destinations')
            ->values()
            ->map(function (array $listing) use ($pending, $inactive) {
                $listing['status'] = match (true) {
                    in_array($listing['id'], $pending, true) => 'Pending Review',
                    in_array($listing['id'], $inactive, true) => 'Inactive',
                    default => 'Active',
                };

                return $listing;
            });

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
