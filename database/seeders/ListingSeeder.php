<?php

namespace Database\Seeders;

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
     * Moves every destination/establishment already authored in
     * App\Support\TourismCatalog::seedData() into the real `listings`
     * table, verbatim — TourismCatalog itself stays as the single source
     * of content, this just makes it durable/editable instead of static.
     *
     * Status mirrors the exact pending/inactive id lists that
     * App\Support\LguMockData::establishments() has been hardcoding at
     * read time — now a real, editable column instead.
     */
    public function run(): void
    {
        $pending = ['dahican-surf-guides', 'delicacies-hub'];
        $inactive = ['tourist-transport-terminal'];

        foreach (TourismCatalog::seedData() as $listing) {
            $status = match (true) {
                $listing['category'] !== 'destinations' && in_array($listing['id'], $pending, true) => 'Pending Review',
                $listing['category'] !== 'destinations' && in_array($listing['id'], $inactive, true) => 'Inactive',
                default => 'Active',
            };

            $model = Listing::query()->updateOrCreate(
                ['lst_slug' => $listing['id']],
                [
                    'lst_name' => $listing['name'],
                    'lst_category' => $listing['category'],
                    'lst_municipality' => $listing['municipality'],
                    'lst_barangay' => $listing['barangay'],
                    'lst_lat' => $listing['lat'] ?? null,
                    'lst_lng' => $listing['lng'] ?? null,
                    'lst_description' => $listing['description'],
                    'lst_rating' => $listing['rating'],
                    'lst_tags' => $listing['tags'],
                    'lst_image' => $listing['image'],
                    'lst_contact_office' => $listing['contactOffice'],
                    'lst_contact_phone' => $listing['contactPhone'],
                    'lst_hours' => $listing['hours'],
                    'lst_status' => $status,
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

        $extras = array_values(array_diff(self::GALLERY_EXTRAS, [$listing->lst_image]));

        $listing->images()->createMany([
            ['lsi_path' => $listing->lst_image, 'lsi_caption' => 'Featured photo', 'lsi_is_primary' => true, 'lsi_sort_order' => 0],
            ['lsi_path' => $extras[0], 'lsi_caption' => 'Grounds & surroundings', 'lsi_is_primary' => false, 'lsi_sort_order' => 1],
            ['lsi_path' => $extras[1], 'lsi_caption' => 'Nearby view', 'lsi_is_primary' => false, 'lsi_sort_order' => 2],
        ]);
    }
}
