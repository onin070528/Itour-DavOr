<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Seeds the destinations and establishments authored in App\Support\TourismCatalog into `listings`.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

namespace Database\Seeders;

use App\Enums\ReportingMethod;
use App\Models\Listing;
use App\Support\TourismCatalog;
use Illuminate\Database\Seeder;

class ListingSeeder extends Seeder
{
    /**
     * The same synthetic 3-photo gallery App\Support\EstablishmentMockData
     * ::galleryImages() used to build on the fly for every establishment
     * profile (featured photo + 2 filler shots from this pool) — seeded
     * here as real listing_images rows so the Establishment Profile
     * gallery isn't empty the moment it becomes DB-backed.
     */
    private const GALLERY_EXTRAS = ['dahican.jpg', 'pujada-bay.jpg', 'sunrise-point.jpg', 'cove.jpg'];

    /**
     * Establishment type per seeded establishment (config/
     * establishment_categories.php), matching the approved Phase 1
     * backfill and the confirmed dahican-surf-guides classification.
     *
     * @var array<string, string>
     */
    private const ESTABLISHMENT_TYPES = [
        'botanika-nature-resort' => 'Resort',
        'badjao-seafront' => 'Restaurant',
        'dahican-surf-guides' => 'Diving / Water Activity',
        'pasalubong-center' => 'Other Food & Dining',
        'delicacies-hub' => 'Other Food & Dining',
        'tourist-transport-terminal' => 'Van / Shuttle Service',
    ];

    /**
     * Seeded establishments that report on paper through their LGU (no
     * linked account). Every other seeded establishment reports online.
     *
     * @var array<int, string>
     */
    private const MANUAL_PAPER_SLUGS = ['pasalubong-center', 'delicacies-hub', 'tourist-transport-terminal'];

    /**
     * Moves every destination/establishment already authored in
     * App\Support\TourismCatalog::seedData() into the real `listings`
     * table, verbatim — TourismCatalog itself stays as the single source
     * of content, this just makes it durable/editable instead of static.
     *
     * Status mirrors the exact draft/unpublished id lists that
     * App\Support\LguMockData::establishments() has been hardcoding at
     * read time — now a real, editable column instead. Establishments use
     * the publish-workflow vocabulary (App\Services\ListingPublishWorkflow);
     * destinations keep the unchanged Active/Suspended/Archived one.
     */
    public function run(): void
    {
        $draft = ['dahican-surf-guides', 'delicacies-hub'];
        $unpublished = ['tourist-transport-terminal'];

        foreach (TourismCatalog::seedData() as $listing) {
            $status = match (true) {
                $listing['category'] === 'destinations' => 'Active',
                in_array($listing['id'], $draft, true) => 'DRAFT',
                in_array($listing['id'], $unpublished, true) => 'UNPUBLISHED',
                default => 'PUBLISHED',
            };

            // Summary comment: type and reporting method apply to establishments only.
            $arrClassification = [];

            if ($listing['category'] !== 'destinations') {
                $blnIsManualPaper = in_array($listing['id'], self::MANUAL_PAPER_SLUGS, true);

                $arrClassification = [
                    'type' => self::ESTABLISHMENT_TYPES[$listing['id']] ?? null,
                    'reporting_mode' => $blnIsManualPaper ? ReportingMethod::ManualPaper : ReportingMethod::OnlineItour,
                ];
            }

            $model = Listing::query()->updateOrCreate(
                ['slug' => $listing['id']],
                [
                    ...$arrClassification,
                    'name' => $listing['name'],
                    'category' => $listing['category'],
                    'municipality' => $listing['municipality'],
                    'barangay' => $listing['barangay'],
                    'lat' => $listing['lat'] ?? null,
                    'lng' => $listing['lng'] ?? null,
                    'description' => $listing['description'],
                    'rating' => $listing['rating'],
                    'tags' => $listing['tags'],
                    'image' => $listing['image'],
                    'contact_office' => $listing['contactOffice'],
                    'contact_phone' => $listing['contactPhone'],
                    'hours' => $listing['hours'],
                    'status' => $status,
                ]
            );

            if ($listing['category'] !== 'destinations') {
                $this->seedGallery($model);
            }
        }
    }

    private function seedGallery(Listing $listing): void
    {
        if ($listing->images()->exists()) {
            return;
        }

        $extras = array_values(array_diff(self::GALLERY_EXTRAS, [$listing->image]));

        $listing->images()->createMany([
            ['path' => $listing->image, 'caption' => 'Featured photo', 'is_primary' => true, 'sort_order' => 0],
            ['path' => $extras[0], 'caption' => 'Grounds & surroundings', 'is_primary' => false, 'sort_order' => 1],
            ['path' => $extras[1], 'caption' => 'Nearby view', 'is_primary' => false, 'sort_order' => 2],
        ]);
    }
}
