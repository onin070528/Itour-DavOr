<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Support\EstablishmentMockData;
use Illuminate\Database\Seeder;

class ArrivalSeeder extends Seeder
{
    /**
     * Moves Botanika Nature Resort's demo front-desk arrivals — the only
     * establishment App\Support\EstablishmentMockData::seedArrivals() has
     * rows for — into the real `arrivals` table (source: staff), verbatim.
     */
    public function run(): void
    {
        $listing = Listing::query()->where('name', 'Botanika Nature Resort')->first();

        if (! $listing) {
            return;
        }

        $listing->arrivals()->where('source', 'staff')->delete();

        $listing->arrivals()->createMany(
            collect(EstablishmentMockData::seedArrivals($listing->name))->map(fn (array $row) => [
                'source' => 'staff',
                'date' => $row['date'],
                'visitor_name' => $row['visitorName'],
                'gender' => $row['gender'],
                'classification' => $row['classification'],
                'remarks' => $row['remarks'],
                'status' => $row['status'],
                'party_size' => 1,
            ])->all()
        );
    }
}
