<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LandingController extends Controller
{
    /**
     * Display the public iTOUR landing page.
     *
     * All content below is static frontend mock data. It is shaped to match
     * what the eventual Eloquent models/API resources will return, so this
     * method's body is what gets replaced once the tourism directory,
     * destinations, and reviews are backed by the database.
     */
    public function index(): View
    {
        return view('landing', [
            'categories' => $this->categories(),
            'destinations' => $this->destinations(),
            'establishments' => $this->establishments(),
            'reviews' => $this->reviews(),
        ]);
    }

    /**
     * @return array<int, array{icon: string, label: string, description: string, href: string, tone: string}>
     */
    private function categories(): array
    {
        return [
            [
                'icon' => 'ti-map-pin',
                'label' => 'Destinations',
                'description' => 'Beaches, waterfalls, and heritage sites across the province.',
                'href' => '#destinations',
                'tone' => 'primary',
            ],
            [
                'icon' => 'ti-bed',
                'label' => 'Accommodations',
                'description' => 'Resorts, lodges, and homestays for every kind of stay.',
                'href' => '#directory',
                'tone' => 'secondary',
            ],
            [
                'icon' => 'ti-tools-kitchen-2',
                'label' => 'Restaurants',
                'description' => 'Local eateries and seafront dining serving Davao Oriental cuisine.',
                'href' => '#directory',
                'tone' => 'accent',
            ],
            [
                'icon' => 'ti-mountain',
                'label' => 'Activities',
                'description' => 'Surfing, trekking, island hopping, and eco-adventures.',
                'href' => '#near-you',
                'tone' => 'primary',
            ],
            [
                'icon' => 'ti-building-store',
                'label' => 'Tourism Establishments',
                'description' => 'Travel services and accredited establishments province-wide.',
                'href' => '#directory',
                'tone' => 'secondary',
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, municipality: string, description: string, rating: float, tags: array<int, string>, href: string}>
     */
    private function destinations(): array
    {
        return [
            [
                'name' => 'Dahican Beach',
                'municipality' => 'City of Mati',
                'description' => 'A seven-kilometre stretch of cream-coloured sand facing the Pacific, known for skimboarding, surfing, and sunrise watching.',
                'rating' => 4.7,
                'tags' => ['Beach', 'Surfing', 'Sunrise'],
                'href' => '#',
            ],
            [
                'name' => 'Aliwagwag Falls Eco-Park',
                'municipality' => 'Cateel',
                'description' => 'A multi-tiered stairway of waterfalls cascading down the Cateel River, with a canopy walk and zipline.',
                'rating' => 4.8,
                'tags' => ['Waterfalls', 'Eco-park', 'Zipline'],
                'href' => '#',
            ],
            [
                'name' => 'Pusan Point',
                'municipality' => 'Governor Generoso',
                'description' => 'A cliffside viewpoint over Pujada Bay, known for its rock formations and panoramic sunrise views.',
                'rating' => 4.6,
                'tags' => ['Viewpoint', 'Sunrise'],
                'href' => '#',
            ],
            [
                'name' => 'Cape of San Agustin',
                'municipality' => 'Governor Generoso',
                'description' => 'The easternmost point of Mindanao, marked by a lighthouse where the Pacific meets the Davao Gulf.',
                'rating' => 4.5,
                'tags' => ['Lighthouse', 'Coastline'],
                'href' => '#',
            ],
            [
                'name' => 'Sleeping Dinosaur Island',
                'municipality' => 'Governor Generoso',
                'description' => 'A small islet off Pujada Bay whose silhouette resembles a resting dinosaur, ringed by clear shallow water.',
                'rating' => 4.6,
                'tags' => ['Island', 'Snorkeling'],
                'href' => '#',
            ],
            [
                'name' => 'Subangan Museum',
                'municipality' => 'City of Mati',
                'description' => "The province's heritage museum, tracing Davao Oriental's history, culture, and indigenous communities.",
                'rating' => 4.4,
                'tags' => ['Heritage', 'Museum'],
                'href' => '#',
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, category: string, municipality: string, description: string, rating: float, href: string}>
     */
    private function establishments(): array
    {
        return [
            [
                'name' => 'Botanika Nature Resort',
                'category' => 'Resort',
                'municipality' => 'City of Mati',
                'description' => 'Beachfront resort on Dahican with garden villas, an infinity pool, and a farm-to-table restaurant.',
                'rating' => 4.6,
                'href' => '#',
            ],
            [
                'name' => 'Amihan sa Dahican',
                'category' => 'Accommodation',
                'municipality' => 'City of Mati',
                'description' => 'Beachfront cottages and camping grounds steps from the surf break, run by the local surfing community.',
                'rating' => 4.5,
                'href' => '#',
            ],
            [
                'name' => 'Dahican Surf Lodge & School',
                'category' => 'Adventure Provider',
                'municipality' => 'City of Mati',
                'description' => 'Surf lessons, board rentals, and guided sunrise paddle-outs for first-timers and regulars alike.',
                'rating' => 4.7,
                'href' => '#',
            ],
            [
                'name' => 'Badjao Seafront Restaurant',
                'category' => 'Restaurant',
                'municipality' => 'City of Mati',
                'description' => 'Overwater dining on Pujada Bay serving kinilaw na malasugue, grilled tuna belly, and seaweed salad.',
                'rating' => 4.5,
                'href' => '#',
            ],
            [
                'name' => 'Davao Oriental Pasalubong Center',
                'category' => 'Travel Service',
                'municipality' => 'City of Mati',
                'description' => 'One-stop shop for dagmay textiles, abaca crafts, tablea, and packaged delicacies from all 11 LGUs.',
                'rating' => 4.5,
                'href' => '#',
            ],
        ];
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
