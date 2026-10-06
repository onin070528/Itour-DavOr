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
        $listing = Listing::query()->where('lst_name', 'Botanika Nature Resort')->first();

        if (! $listing) {
            return;
        }

        $listing->arrivals()->where('arr_source', 'staff')->delete();

        $listing->arrivals()->createMany(
            collect(EstablishmentMockData::seedArrivals($listing->lst_name))->map(fn (array $row) => [
                'arr_source' => 'staff',
                'arr_date' => $row['date'],
                'arr_visitor_name' => $row['visitorName'],
                'arr_gender' => $row['gender'],
                'arr_classification' => $row['classification'],
                'arr_remarks' => $row['remarks'],
                'arr_status' => $row['status'],
                'arr_party_size' => 1,
            ])->all()
        );
    }
}
