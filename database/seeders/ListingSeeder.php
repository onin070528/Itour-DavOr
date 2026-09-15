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
                ['slug' => $listing['id']],
                [
                    'name' => $listing['name'],
                    'category' => $listing['category'],
                    'municipality' => $listing['municipality'],
                    'barangay' => $listing['barangay'],
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
