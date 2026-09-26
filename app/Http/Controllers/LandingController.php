<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Renders the public landing page — featured destinations,
 * establishments, municipalities, visitor reviews, and the nearby-places map.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Http\Controllers;

use App\Support\TourismCatalog;
use Illuminate\View\View;

class LandingController extends Controller
{
    /**
     * Display the public iTOUR landing page.
     *
     * The Signature Experiences showcase (a curated mix of destinations and
     * establishments) comes from TourismCatalog, the single source of truth
     * shared with the /explore hub. Reviews are static frontend mock data —
     * shaped to match what an API resource will eventually return.
     */
    public function index(): View
    {
        return view('landing', [
            'signatureExperiences' => TourismCatalog::signatureExperiences(),
            'featuredEstablishments' => TourismCatalog::featuredEstablishments(4),
            'municipalities' => TourismCatalog::municipalities(),
            'reviews' => $this->reviews(),
            'nearbyPlaces' => $this->nearbyPlaces(),
        ]);
    }

    /**
     * Active listings that have coordinates, in the shape the "Find Places
     * Near You" Mapbox map on the landing page plots markers from.
     *
     * @return array<int, array{name: string, category: string, municipality: string, barangay: string, lat: float, lng: float}>
     */
    private function nearbyPlaces(): array
    {
        return collect(TourismCatalog::listings())
            ->where('status', 'Active')
            ->filter(fn ($listing) => $listing['lat'] !== null && $listing['lng'] !== null)
            ->map(fn ($listing) => [
                'name' => $listing['name'],
                'category' => $listing['category'],
                'categoryLabel' => TourismCatalog::categoryLabel($listing['category']),
                'municipality' => $listing['municipality'],
                'barangay' => $listing['barangay'],
                'lat' => $listing['lat'],
                'lng' => $listing['lng'],
                'href' => $listing['href'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, rating: int, subject: string, date: string, text: string}>
     */
    private function reviews(): array
    {
        return [
            [
                'name' => 'Rica M.',
                'rating' => 5,
                'subject' => 'Dahican Beach',
                'date' => 'July 28, 2026',
                'text' => 'Woke up early for the sunrise and had the whole shoreline to myself. The sand is so fine and the skimboard rentals right on the beach made it an easy first try.',
            ],
            [
                'name' => 'Josel T.',
                'rating' => 5,
                'subject' => 'Aliwagwag Falls Eco-Park',
                'date' => 'July 14, 2026',
                'text' => "Genuinely one of the most beautiful falls I've hiked to in Mindanao. The canopy walk gives you a view of nearly every tier — bring water shoes, the stairs get slippery.",
            ],
            [
                'name' => 'Grace A.',
                'rating' => 4,
                'subject' => 'Mount Hamiguitan Range Wildlife Sanctuary',
                'date' => 'June 30, 2026',
                'text' => 'The pygmy forest at the summit is unreal — centuries-old bonsai trees you can only find here. Trek is long, so book a guide through the tourism office in advance.',
            ],
            [
                'name' => 'Marco D.',
                'rating' => 5,
                'subject' => 'Botanika Nature Resort',
                'date' => 'June 9, 2026',
                'text' => 'Stayed two nights and did not want to leave. Garden villas were quiet, staff arranged a Pujada Bay island hop for us, and the farm-to-table breakfast was a highlight.',
            ],
        ];
    }
}
