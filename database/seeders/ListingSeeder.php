<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Seeds the destination and establishment listings (and gallery rows) from TourismCatalog.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
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
     * here as real tbl_listing_images rows so the Establishment Profile
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
     * App\Support\TourismCatalog::seedData() into the real `tbl_listings`
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
        $arrDraft = ['dahican-surf-guides', 'delicacies-hub'];
        $arrUnpublished = ['tourist-transport-terminal'];

        foreach (TourismCatalog::seedData() as $arrListing) {
            $strStatus = match (true) {
                $arrListing['category'] === 'destinations' => 'Active',
                in_array($arrListing['id'], $arrDraft, true) => 'DRAFT',
                in_array($arrListing['id'], $arrUnpublished, true) => 'UNPUBLISHED',
                default => 'PUBLISHED',
            };

            // Summary comment: type and reporting method apply to establishments only.
            $arrClassification = [];

            if ($arrListing['category'] !== 'destinations') {
                $blnIsManualPaper = in_array($arrListing['id'], self::MANUAL_PAPER_SLUGS, true);

                $arrClassification = [
                    'lst_type' => self::ESTABLISHMENT_TYPES[$arrListing['id']] ?? null,
                    'lst_reporting_mode' => $blnIsManualPaper ? ReportingMethod::ManualPaper : ReportingMethod::OnlineItour,
                ];
            }

            $objModel = Listing::query()->updateOrCreate(
                ['lst_slug' => $arrListing['id']],
                [
                    ...$arrClassification,
                    'lst_name' => $arrListing['name'],
                    'lst_category' => $arrListing['category'],
                    'lst_municipality' => $arrListing['municipality'],
                    'lst_barangay' => $arrListing['barangay'],
                    'lst_lat' => $arrListing['lat'] ?? null,
                    'lst_lng' => $arrListing['lng'] ?? null,
                    'lst_description' => $arrListing['description'],
                    'lst_rating' => $arrListing['rating'],
                    'lst_tags' => $arrListing['tags'],
                    'lst_image' => $arrListing['image'],
                    'lst_contact_office' => $arrListing['contactOffice'],
                    'lst_contact_phone' => $arrListing['contactPhone'],
                    'lst_hours' => $arrListing['hours'],
                    'lst_status' => $strStatus,
                ]
            );

            if ($arrListing['category'] !== 'destinations') {
                $this->seedGallery($objModel);
            }
        }
    }

    private function seedGallery(Listing $objListing): void
    {
        if ($objListing->images()->exists()) {
            return;
        }

        $arrExtras = array_values(array_diff(self::GALLERY_EXTRAS, [$objListing->lst_image]));

        $objListing->images()->createMany([
            ['lsi_path' => $objListing->lst_image, 'lsi_caption' => 'Featured photo', 'lsi_is_primary' => true, 'lsi_sort_order' => 0],
            ['lsi_path' => $arrExtras[0], 'lsi_caption' => 'Grounds & surroundings', 'lsi_is_primary' => false, 'lsi_sort_order' => 1],
            ['lsi_path' => $arrExtras[1], 'lsi_caption' => 'Nearby view', 'lsi_is_primary' => false, 'lsi_sort_order' => 2],
        ]);
    }
}
