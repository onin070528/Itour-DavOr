<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Single source of truth for tourism destinations/establishments shown on the public site.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Models\Listing;

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
    public static function categoryLabel(string $strSlug): string
    {
        // Summary comment: slugs for the newer categories (e.g.
        // recreation-activities) only exist in exploreCategories().
        return collect(self::categories())->firstWhere('slug', $strSlug)['label']
            ?? collect(self::exploreCategories())->firstWhere('slug', $strSlug)['label']
            ?? $strSlug;
    }

    /**
     * The Tabler icon class for a category slug — reused as the public
     * placeholder (7E: "a neutral icon ... we own") when a listing has no
     * cover photo at all.
     */
    public static function categoryIcon(string $strSlug): string
    {
        return collect(self::categories())->firstWhere('slug', $strSlug)['icon']
            ?? collect(self::exploreCategories())->firstWhere('slug', $strSlug)['icon']
            ?? 'ti-photo';
    }

    /**
     * Explore-hub-only category chips — a relabeling of the same underlying
     * `category` slugs used everywhere else (categories() above, still used
     * by directory pages and the establishment registration forms; slugs
     * and the `listings` data are untouched). Four of these ten have no
     * matching slug at all yet (farm-agri-tourism, wellness-spa,
     * recreation-activities, mice-events) — real establishment types this
     * app has no data or field for, so those chips will show zero results
     * until such listings exist. "Destinations" is kept as its own chip
     * even though it's outside the establishment grouping the other nine
     * belong to, since destinations are most of today's dataset.
     *
     * @return array<int, array{slug: string, label: string, icon: string}>
     */
    public static function exploreCategories(): array
    {
        return [
            ['slug' => 'destinations', 'label' => 'Tourist Destinations', 'icon' => 'ti-map-pin'],
            ['slug' => 'accommodation', 'label' => 'Accommodation', 'icon' => 'ti-bed'],
            ['slug' => 'restaurants', 'label' => 'Food & Dining', 'icon' => 'ti-tools-kitchen-2'],
            ['slug' => 'farm-agri-tourism', 'label' => 'Farm & Agri-Tourism', 'icon' => 'ti-plant-2'],
            ['slug' => 'wellness-spa', 'label' => 'Wellness & Spa', 'icon' => 'ti-flower'],
            ['slug' => 'tour-guides', 'label' => 'Travel & Tours', 'icon' => 'ti-compass'],
            ['slug' => 'transportation', 'label' => 'Tourist Transport', 'icon' => 'ti-bus'],
            ['slug' => 'recreation-activities', 'label' => 'Recreation & Activities', 'icon' => 'ti-ball-basketball'],
            ['slug' => 'mice-events', 'label' => 'MICE & Events', 'icon' => 'ti-presentation'],
            ['slug' => 'local-delicacies', 'label' => 'Others', 'icon' => 'ti-gift'],
        ];
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
     * The barangays of each of the province's 11 municipalities, per the
     * Wikipedia "Barangays" sections (PSA-based). Names are bare; use
     * barangaysFor() for the "Brgy. X" form stored on listings.
     *
     * @return array<string, array<int, string>>
     */
    public static function barangays(): array
    {
        return [
            'Boston' => ['Caatihan', 'Cabasagan', 'Carmen', 'Cauwayanan', 'Poblacion', 'San Jose', 'Sibajay', 'Simulao'],
            'Cateel' => ['Abihod', 'Alegria', 'Aliwagwag', 'Aragon', 'Baybay', 'Maglahus', 'Mainit', 'Malibago', 'San Alfonso', 'San Antonio', 'San Miguel', 'San Rafael', 'San Vicente', 'Santa Filomena', 'Taytayan', 'Poblacion'],
            'Baganga' => ['Baculin', 'Ban-ao', 'Batawan', 'Batiano', 'Binondo', 'Bobonao', 'Campawan', 'Central', 'Dapnan', 'Kinablangan', 'Lambajon', 'Lucod', 'Mahan-ub', 'Mikit', 'Salingcomot', 'San Isidro', 'San Victor', 'Saoquigue'],
            'Caraga' => ['Alvar', 'Caningag', 'Don Leon Balante', 'Lamiawan', 'Manorigao', 'Mercedes', 'Palma Gil', 'Pichon', 'Poblacion', 'San Antonio', 'San Jose', 'San Luis', 'San Miguel', 'San Pedro', 'Santa Fe', 'Santiago', 'P.M. Sobrecarey'],
            'Manay' => ['Capasnan', 'Cayawan', 'Central', 'Concepcion', 'Del Pilar', 'Guza', 'Holy Cross', 'Lambog', 'Mabini', 'Manreza', 'New Taokanga', 'Old Macopa', 'Rizal', 'San Fermin', 'San Ignacio', 'San Isidro', 'Zaragosa'],
            'City of Mati' => ['Badas', 'Bobon', 'Buso', 'Cabuaya', 'Central', 'Culian', 'Dahican', 'Danao', 'Dawan', 'Don Enrique Lopez', 'Don Martin Marundan', 'Don Salvador Lopez Sr.', 'Langka', 'Lawigan', 'Libudon', 'Luban', 'Macambol', 'Mamali', 'Matiao', 'Mayo', 'Sainz', 'Sanghay', 'Tagabakid', 'Tagbinonga', 'Taguibo', 'Tamisan'],
            'Tarragona' => ['Cabagayan', 'Central', 'Dadong', 'Jovellar', 'Limot', 'Lucatan', 'Maganda', 'Ompao', 'Tomoaong', 'Tubaon'],
            'San Isidro' => ['Baon', 'Batobato', 'Bitaogan', 'Cambaleon', 'Dugmanon', 'Iba', 'La Union', 'Lapu-lapu', 'Maag', 'Manikling', 'Maputi', 'San Miguel', 'San Roque', 'Santo Rosario', 'Sudlon', 'Talisay'],
            'Governor Generoso' => ['Anitap', 'Crispin Dela Cruz', 'Don Aurelio Chicote', 'Lavigan', 'Luzon', 'Magdug', 'Manuel Roxas', 'Montserrat', 'Nangan', 'Oregon', 'Poblacion', 'Pundaguitan', 'Sergio Osmeña', 'Surop', 'Tagabebe', 'Tamban', 'Tandang Sora', 'Tibanban', 'Tiblawan', 'Upper Tibanban'],
            'Lupon' => ['Bagumbayan', 'Cabadiangan', 'Calapagan', 'Cocornon', 'Corporacion', 'Don Mariano Marcos', 'Ilangay', 'Langka', 'Lantawan', 'Limbahan', 'Macangao', 'Magsaysay', 'Mahayahay', 'Maragatas', 'Marayag', 'New Visayas', 'Poblacion', 'San Isidro', 'San Jose', 'Tagboa', 'Tagugpo'],
            'Banaybanay' => ['Cabangcalan', 'Caganganan', 'Calubihan', 'Causwagan', 'Mahayag', 'Maputi', 'Mogbongcogon', 'Panikian', 'Pintatagan', 'Piso Proper', 'Poblacion', 'Punta Linao', 'Rang-ay', 'San Vicente'],
        ];
    }

    /**
     * The "Brgy. X" values for one municipality's dropdown (and validation);
     * empty for an unknown municipality.
     *
     * @return array<int, string>
     */
    public static function barangaysFor(string $strMunicipality): array
    {
        return array_map(
            fn (string $strName) => "Brgy. {$strName}",
            self::barangays()[$strMunicipality] ?? []
        );
    }

    /**
     * The municipal tourism office's display name — "City of Mati" →
     * "Mati City Tourism Office", "Cateel" → "Cateel Tourism Office".
     */
    public static function tourismOfficeName(string $strMunicipality): string
    {
        if (str_starts_with($strMunicipality, 'City of ')) {
            return substr($strMunicipality, 8).' City Tourism Office';
        }

        return "{$strMunicipality} Tourism Office";
    }

    /**
     * Every destination and tourism establishment, in one unified shape,
     * read from the real `tbl_listings` table (App\Models\Listing) — the DB
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
        return Listing::query()
            ->with(['establishmentImages' => fn ($objQuery) => $objQuery->where('img_status', 'PUBLISHED')])
            ->orderBy('lst_id')
            ->get()
            ->map(fn (Listing $objListing) => self::catalogEntry($objListing))
            ->all();
    }

    /**
     * One listing in the catalog shape listings() returns — also what the
     * server-paginated Explore directory renders its cards and table rows
     * from, so both pages share one shape. Load `establishmentImages`
     * (PUBLISHED only) first to avoid a query per listing.
     *
     * @return array<string, mixed>
     */
    public static function catalogEntry(Listing $objListing): array
    {
        return [
            'id' => $objListing->lst_slug,
            'name' => $objListing->lst_name,
            'category' => $objListing->lst_category,
            'destinationType' => $objListing->isDestinationOnly() ? $objListing->lst_type : null,
            'municipality' => $objListing->lst_municipality,
            'barangay' => $objListing->lst_barangay,
            'lat' => $objListing->lst_lat,
            'lng' => $objListing->lst_lng,
            'description' => $objListing->lst_description,
            // Card excerpt of the description (presentation only; the stored description is unchanged).
            'summary' => TextSummary::excerpt($objListing->lst_description),
            // Public yes/no only — the stored accreditation text never reaches the page.
            'isDotAccredited' => $objListing->isDotAccredited(),
            'rating' => $objListing->lst_rating !== null ? (float) $objListing->lst_rating : null,
            'tags' => $objListing->lst_tags ?? [],
            'image' => $objListing->lst_image,
            'displayImageUrl' => $objListing->publicCoverImageUrl(),
            'categoryIcon' => self::categoryIcon($objListing->lst_category),
            'contactOffice' => $objListing->lst_contact_office,
            'contactPhone' => $objListing->lst_contact_phone,
            'hours' => $objListing->lst_hours,
            'href' => route('listings.show', $objListing->lst_slug),
            'status' => $objListing->lst_status,
            'isPubliclyVisible' => $objListing->isPubliclyVisible(),
            'email' => $objListing->lst_email,
            'website' => $objListing->lst_website,
        ];
    } // end catalogEntry

    /**
     * The original, hand-authored listing content — the seed data
     * ListingSeeder loads into the `tbl_listings` table. Not used for reads
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
                'category' => 'recreation-activities',
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
    public static function featuredDestinations(int $intLimit = 8): array
    {
        return collect(self::listings())
            ->where('category', 'destinations')
            ->where('isPubliclyVisible', true)
            ->take($intLimit)
            ->all();
    }

    /**
     * The first N tourism establishments (everything but destinations), for
     * the homepage preview. Only shows publicly visible ones — DRAFT/
     * FOR_PTO_REVIEW/UNPUBLISHED/Archived establishments aren't yet meant to
     * be publicly visible (see App\Services\ListingPublishWorkflow).
     */
    public static function featuredEstablishments(int $intLimit = 6): array
    {
        return collect(self::listings())
            ->where('category', '!=', 'destinations')
            ->where('isPubliclyVisible', true)
            ->take($intLimit)
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
        $arrOrder = [
            'dahican-beach',
            'aliwagwag-falls',
            'hamiguitan',
            'botanika-nature-resort',
            'badjao-seafront',
            'pasalubong-center',
        ];

        $objById = collect(self::listings())
            ->where('isPubliclyVisible', true)
            ->keyBy('id');

        return collect($arrOrder)
            ->map(fn (string $strId) => $objById->get($strId))
            ->filter()
            ->values()
            ->all();
    }
}
