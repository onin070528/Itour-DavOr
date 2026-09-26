<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Single source of truth for tourism destinations/establishments shown on the public site.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

/**
 * Shared mock tourism data for the public site.
 *
 * This is the single source of truth for destinations and tourism
 * establishments used by both the landing page preview sections and the
 * consolidated /explore hub. Swapping this for real Eloquent-backed data
 * later only means replacing the bodies of these static methods.
 */
class TourismCatalog
{
    /**
     * The six listing categories used to filter the Explore hub.
     *
     * @return array<int, array{slug: string, label: string, icon: string}>
     */
    public static function categories(): array
    {
        return [
            ['slug' => 'destinations', 'label' => 'Tourist Destinations', 'icon' => 'ti-map-pin'],
            ['slug' => 'accommodation', 'label' => 'Accommodation', 'icon' => 'ti-bed'],
            ['slug' => 'restaurants', 'label' => 'Restaurants', 'icon' => 'ti-tools-kitchen-2'],
            ['slug' => 'transportation', 'label' => 'Transportation', 'icon' => 'ti-bus'],
            ['slug' => 'tour-guides', 'label' => 'Tour Guides', 'icon' => 'ti-compass'],
            ['slug' => 'local-delicacies', 'label' => 'Local Delicacies', 'icon' => 'ti-gift'],
        ];
    }

    /**
     * The display label for a category slug (e.g. "accommodation" → "Accommodation").
     */
    public static function categoryLabel(string $slug): string
    {
        return collect(self::categories())->firstWhere('slug', $slug)['label'] ?? $slug;
    }

    /**
     * The province's 11 municipalities, with an illustrative (not-to-scale)
     * position used to plot markers on the Explore hub's placeholder map.
     *
     * @return array<int, array{name: string, top: int, left: int}>
     */
    public static function municipalities(): array
    {
        return [
            ['name' => 'Boston', 'top' => 8, 'left' => 45],
            ['name' => 'Cateel', 'top' => 18, 'left' => 42],
            ['name' => 'Baganga', 'top' => 28, 'left' => 48],
            ['name' => 'Caraga', 'top' => 40, 'left' => 52],
            ['name' => 'Manay', 'top' => 50, 'left' => 55],
            ['name' => 'City of Mati', 'top' => 60, 'left' => 62],
            ['name' => 'Tarragona', 'top' => 68, 'left' => 58],
            ['name' => 'San Isidro', 'top' => 72, 'left' => 50],
            ['name' => 'Governor Generoso', 'top' => 82, 'left' => 60],
            ['name' => 'Lupon', 'top' => 88, 'left' => 45],
            ['name' => 'Banaybanay', 'top' => 94, 'left' => 40],
        ];
    }

    /**
     * Every destination and tourism establishment, in one unified shape,
     * read from the real `listings` table (App\Models\Listing) — the DB
     * rows are seeded verbatim from self::seedData() by ListingSeeder, so
     * every existing caller of listings() keeps working unchanged against
     * real, editable data instead of this static array.
     *
     * @return array<int, array{
     *     id: string, name: string, category: string, municipality: string, barangay: string,
     *     lat: ?float, lng: ?float,
     *     description: ?string, rating: ?float, tags: array<int, string>, image: ?string,
     *     contactOffice: ?string, contactPhone: ?string, hours: ?string, href: string,
     *     status: string, email: ?string, website: ?string
     * }>
     */
    public static function listings(): array
    {
        return \App\Models\Listing::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($listing) => [
                'id' => $listing->slug,
                'name' => $listing->name,
                'category' => $listing->category,
                'municipality' => $listing->municipality,
                'barangay' => $listing->barangay,
                'lat' => $listing->lat,
                'lng' => $listing->lng,
                'description' => $listing->description,
                'rating' => $listing->rating !== null ? (float) $listing->rating : null,
                'tags' => $listing->tags ?? [],
                'image' => $listing->image,
                'contactOffice' => $listing->contact_office,
                'contactPhone' => $listing->contact_phone,
                'hours' => $listing->hours,
                'href' => '#',
                'status' => $listing->status,
                'email' => $listing->email,
                'website' => $listing->website,
            ])
            ->all();
    }

    /**
     * The original, hand-authored listing content — the seed data
     * ListingSeeder loads into the `listings` table. Not used for reads
     * anymore (see listings() above); kept here so the content itself has
     * exactly one authored source.
     *
     * @return array<int, array{
     *     id: string, name: string, category: string, municipality: string, barangay: string,
     *     description: string, rating: float, tags: array<int, string>, image: string,
     *     contactOffice: string, contactPhone: string, hours: string, href: string
     * }>
     */
    public static function seedData(): array
    {
        return [
            [
                'id' => 'dahican-beach',
                'name' => 'Dahican Beach',
                'category' => 'destinations',
                'municipality' => 'City of Mati',
                'barangay' => 'Brgy. Dahican',
                'lat' => 6.9578,
                'lng' => 126.2478,
                'description' => 'A seven-kilometre stretch of cream-coloured sand facing the Pacific, known for skimboarding, surfing, and sunrise watching.',
                'rating' => 4.7,
                'tags' => ['Beach', 'Surfing', 'Sunrise'],
                'image' => 'dahican.jpg',
                'contactOffice' => 'Mati City Tourism Office',
                'contactPhone' => '(087) 388 3021',
                'hours' => 'Open 24 hours · Lifeguards 6:00 AM–6:00 PM',
                'href' => '#',
            ],
            [
                'id' => 'aliwagwag-falls',
                'name' => 'Aliwagwag Falls Eco-Park',
                'category' => 'destinations',
                'municipality' => 'Cateel',
                'barangay' => 'Brgy. Aliwagwag',
                'lat' => 7.7947,
                'lng' => 126.3550,
                'description' => 'A multi-tiered stairway of waterfalls cascading down the Cateel River, with a canopy walk and zipline.',
                'rating' => 4.8,
                'tags' => ['Waterfalls', 'Eco-park', 'Zipline'],
                'image' => 'aliwagwag.jpg',
                'contactOffice' => 'Cateel MTO',
                'contactPhone' => '(087) 400 1188',
                'hours' => '6:00 AM – 5:00 PM daily',
                'href' => '#',
            ],
            [
                'id' => 'hamiguitan',
                'name' => 'Mount Hamiguitan Range Wildlife Sanctuary',
                'category' => 'destinations',
                'municipality' => 'San Isidro',
                'barangay' => 'Brgy. La Union',
                'lat' => 6.7419,
                'lng' => 126.1725,
                'description' => "The Philippines' sixth UNESCO World Heritage Site — a pygmy forest of century-old bonsai trees, pitcher plants, and rare wildlife.",
                'rating' => 4.9,
                'tags' => ['UNESCO', 'Trekking', 'Wildlife'],
                'image' => 'hamiguitan.jpg',
                'contactOffice' => 'PENRO Davao Oriental',
                'contactPhone' => '(087) 811 1445',
                'hours' => 'Permit required · Trek 5:00 AM assembly',
                'href' => '#',
            ],
            [
                'id' => 'pusan-point',
                'name' => 'Pusan Point',
                'category' => 'destinations',
                'municipality' => 'Governor Generoso',
                'barangay' => 'Brgy. Lavigan',
                'lat' => 6.6667,
                'lng' => 126.1667,
                'description' => 'A cliffside viewpoint over Pujada Bay, known for its rock formations and panoramic sunrise views.',
                'rating' => 4.6,
                'tags' => ['Viewpoint', 'Sunrise'],
                'image' => 'sunrise-point.jpg',
                'contactOffice' => 'Gov. Generoso MTO',
                'contactPhone' => '(087) 350 2210',
                'hours' => 'Open 24 hours',
                'href' => '#',
            ],
            [
                'id' => 'cape-san-agustin',
                'name' => 'Cape of San Agustin',
                'category' => 'destinations',
                'municipality' => 'Governor Generoso',
                'barangay' => 'Brgy. Lavigan',
                'lat' => 6.2680,
                'lng' => 126.1841,
                'description' => 'The easternmost point of Mindanao, marked by a lighthouse where the Pacific meets the Davao Gulf.',
                'rating' => 4.5,
                'tags' => ['Lighthouse', 'Coastline'],
                'image' => 'lighthouse.jpg',
                'contactOffice' => 'Gov. Generoso MTO',
                'contactPhone' => '(087) 350 2210',
                'hours' => '7:00 AM – 5:00 PM daily',
                'href' => '#',
            ],
            [
                'id' => 'sleeping-dinosaur-island',
                'name' => 'Sleeping Dinosaur Island',
                'category' => 'destinations',
                'municipality' => 'City of Mati',
                'barangay' => 'Brgy. Badas',
                'lat' => 6.8833,
                'lng' => 126.3167,
                'description' => 'A small islet off Pujada Bay whose silhouette resembles a resting dinosaur, ringed by clear shallow water.',
                'rating' => 4.6,
                'tags' => ['Island', 'Snorkeling'],
                'image' => 'cove.jpg',
                'contactOffice' => 'Mati City Tourism Office',
                'contactPhone' => '(087) 388 3021',
                'hours' => '5:00 AM – 7:00 PM daily',
                'href' => '#',
            ],
            [
                'id' => 'pujada-bay',
                'name' => 'Pujada Bay',
                'category' => 'destinations',
                'municipality' => 'City of Mati',
                'barangay' => 'Brgy. Badas',
                'lat' => 6.8500,
                'lng' => 126.3000,
                'description' => 'A protected seascape of mangroves and coral gardens, ringed by the very shoreline where the Philippines meets the Pacific.',
                'rating' => 4.7,
                'tags' => ['Protected Seascape', 'Mangroves'],
                'image' => 'pujada-bay.jpg',
                'contactOffice' => 'Mati City Tourism Office',
                'contactPhone' => '(087) 388 3021',
                'hours' => 'Open 24 hours',
                'href' => '#',
            ],
            [
                'id' => 'subangan-museum',
                'name' => 'Subangan Museum',
                'category' => 'destinations',
                'municipality' => 'City of Mati',
                'barangay' => 'Brgy. Central',
                'lat' => 6.9530,
                'lng' => 126.2140,
                'description' => "The province's heritage museum, tracing Davao Oriental's history, culture, and indigenous communities.",
                'rating' => 4.4,
                'tags' => ['Heritage', 'Museum'],
                'image' => 'museum.jpg',
                'contactOffice' => 'Provincial Tourism Office',
                'contactPhone' => '(087) 388 3611',
                'hours' => '8:00 AM – 5:00 PM · Closed Mondays',
                'href' => '#',
            ],
            [
                'id' => 'botanika-nature-resort',
                'name' => 'Botanika Nature Resort',
                'category' => 'accommodation',
                'municipality' => 'City of Mati',
                'barangay' => 'Brgy. Dahican',
                'lat' => 6.9600,
                'lng' => 126.2500,
                'description' => 'Beachfront resort on Dahican with garden villas, an infinity pool, and a farm-to-table restaurant.',
                'rating' => 4.6,
                'tags' => ['Resort', 'Beachfront'],
                'image' => 'resort.jpg',
                'contactOffice' => 'Mati City Tourism Office',
                'contactPhone' => '(087) 388 3021',
                'hours' => 'Check-in 2:00 PM · Check-out 12:00 NN',
                'href' => '#',
            ],
            [
                'id' => 'badjao-seafront',
                'name' => 'Badjao Seafront Restaurant',
                'category' => 'restaurants',
                'municipality' => 'City of Mati',
                'barangay' => 'Brgy. Dahican',
                'lat' => 6.9550,
                'lng' => 126.2450,
                'description' => 'Overwater dining on Pujada Bay serving kinilaw na malasugue, grilled tuna belly, and seaweed salad.',
                'rating' => 4.5,
                'tags' => ['Seafood', 'Bay View'],
                'image' => 'restaurant.jpg',
                'contactOffice' => 'Mati City Tourism Office',
                'contactPhone' => '(087) 388 3021',
                'hours' => '10:00 AM – 9:00 PM daily',
                'href' => '#',
            ],
            [
                'id' => 'pasalubong-center',
                'name' => 'Davao Oriental Pasalubong Center',
                'category' => 'local-delicacies',
                'municipality' => 'City of Mati',
                'barangay' => 'Brgy. Central',
                'lat' => 6.9520,
                'lng' => 126.2130,
                'description' => 'One-stop shop for dagmay textiles, abaca crafts, tablea, and packaged delicacies from all 11 LGUs.',
                'rating' => 4.5,
                'tags' => ['Souvenirs', 'Crafts'],
                'image' => 'pasalubong.jpg',
                'contactOffice' => 'Provincial Tourism Office',
                'contactPhone' => '(087) 388 3611',
                'hours' => '8:00 AM – 6:00 PM daily',
                'href' => '#',
            ],
            [
                'id' => 'dahican-surf-guides',
                'name' => 'Dahican Surf Guides & Tours',
                'category' => 'tour-guides',
                'municipality' => 'City of Mati',
                'barangay' => 'Brgy. Dahican',
                'lat' => 6.9585,
                'lng' => 126.2470,
                'description' => 'Surf lessons, board rentals, and guided sunrise paddle-outs led by the local surfing community.',
                'rating' => 4.7,
                'tags' => ['Surfing', 'Guided Tours'],
                'image' => 'guides.jpg',
                'contactOffice' => 'Mati City Tourism Office',
                'contactPhone' => '(087) 388 3021',
                'hours' => 'Advance booking required',
                'href' => '#',
            ],
            [
                'id' => 'delicacies-hub',
                'name' => 'Davao Oriental Delicacies Hub',
                'category' => 'local-delicacies',
                'municipality' => 'City of Mati',
                'barangay' => 'Brgy. Central',
                'lat' => 6.9525,
                'lng' => 126.2135,
                'description' => 'Home-made tablea, bibingka, and native kakanin sourced from cooperatives across the province.',
                'rating' => 4.4,
                'tags' => ['Delicacies', 'Local Products'],
                'image' => 'delicacies.jpg',
                'contactOffice' => 'Provincial Tourism Office',
                'contactPhone' => '(087) 388 3611',
                'hours' => '8:00 AM – 6:00 PM daily',
                'href' => '#',
            ],
            [
                'id' => 'tourist-transport-terminal',
                'name' => 'Provincial Tourist Transport Terminal',
                'category' => 'transportation',
                'municipality' => 'City of Mati',
                'barangay' => 'Brgy. Central',
                'lat' => 6.9500,
                'lng' => 126.2100,
                'description' => 'Vans and buses connecting Mati to every municipality in the province, plus routes to Davao City.',
                'rating' => 4.3,
                'tags' => ['Vans', 'Bus Routes'],
                'image' => 'transport.jpg',
                'contactOffice' => 'Provincial Tourism Office',
                'contactPhone' => '(087) 388 3611',
                'hours' => '4:00 AM – 10:00 PM daily',
                'href' => '#',
            ],
        ];
    }

    /**
     * The first N destinations, for the homepage preview. Only used by the
     * fully-public landing page, so — unlike listings() itself, which
     * LGU/PTO management tables also read — archived listings are filtered
     * out here.
     */
    public static function featuredDestinations(int $limit = 8): array
    {
        return collect(self::listings())
            ->where('category', 'destinations')
            ->where('status', 'Active')
            ->take($limit)
            ->all();
    }

    /**
     * The first N tourism establishments (everything but destinations), for
     * the homepage preview. Only shows Active ones — Pending Review/
     * Inactive/Archived establishments aren't yet meant to be publicly
     * visible (see Lgu\DirectoryController@verifyEstablishment).
     */
    public static function featuredEstablishments(int $limit = 6): array
    {
        return collect(self::listings())
            ->where('category', '!=', 'destinations')
            ->where('status', 'Active')
            ->take($limit)
            ->all();
    }

    /**
     * A hand-picked mix of the province's flagship destinations and
     * establishments for the landing page's single "Signature Experiences"
     * showcase — deliberately curated by id (not just the first N by
     * insertion order), so the homepage always leads with the most
     * recognizable places rather than whatever happens to be seeded first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function signatureExperiences(): array
    {
        $order = [
            'dahican-beach',
            'aliwagwag-falls',
            'hamiguitan',
            'botanika-nature-resort',
            'badjao-seafront',
            'pasalubong-center',
        ];

        $byId = collect(self::listings())
            ->where('status', 'Active')
            ->keyBy('id');

        return collect($order)
            ->map(fn (string $id) => $byId->get($id))
            ->filter()
            ->values()
            ->all();
    }
}
