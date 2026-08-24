<?php

namespace App\Support;

/**
 * Mock data for the PTO (Provincial Tourism Office) workspace.
 *
 * Everything here is static frontend data shaped like what the eventual
 * Eloquent models / API resources will return (tourist arrivals, sentiment
 * analysis results, submitted reports, system users). Swapping this for
 * real data later means replacing the body of each method, not the pages
 * that consume it.
 */
class PtoMockData
{
    /**
     * @return array<int, array{name: string, municipality: string, category: string}>
     */
    public static function establishmentDirectory(): array
    {
        return [
            ['name' => 'Botanika Nature Resort', 'municipality' => 'City of Mati', 'category' => 'Resort'],
            ['name' => 'Badjao Seafront Restaurant', 'municipality' => 'City of Mati', 'category' => 'Restaurant'],
            ['name' => 'Davao Oriental Pasalubong Center', 'municipality' => 'City of Mati', 'category' => 'Travel Service'],
            ['name' => 'Dahican Surf Guides & Tours', 'municipality' => 'City of Mati', 'category' => 'Adventure Provider'],
            ['name' => 'Mati City Pension House', 'municipality' => 'City of Mati', 'category' => 'Accommodation'],
            ['name' => 'Aliwagwag Eco-Lodge', 'municipality' => 'Cateel', 'category' => 'Accommodation'],
            ['name' => 'Baganga Surf Camp', 'municipality' => 'Baganga', 'category' => 'Adventure Provider'],
            ['name' => 'Pujada Bay View Inn', 'municipality' => 'Governor Generoso', 'category' => 'Accommodation'],
            ['name' => 'Hamiguitan Base Camp Lodge', 'municipality' => 'San Isidro', 'category' => 'Accommodation'],
            ['name' => 'Cateel Riverside Diner', 'municipality' => 'Cateel', 'category' => 'Restaurant'],
        ];
    }

    /**
     * @return array<int, array{id: string, date: string, establishment: string, municipality: string, classification: string, gender: string, visitors: int}>
     */
    public static function arrivals(): array
    {
        $rows = [
            ['2026-08-22', 'Botanika Nature Resort', 'City of Mati', 'Foreign', 'Female', 4],
            ['2026-08-22', 'Badjao Seafront Restaurant', 'City of Mati', 'Domestic (Other Province)', 'Male', 6],
            ['2026-08-21', 'Aliwagwag Eco-Lodge', 'Cateel', 'Local (Same Province)', 'Female', 3],
            ['2026-08-21', 'Dahican Surf Guides & Tours', 'City of Mati', 'Foreign', 'Male', 2],
            ['2026-08-20', 'Baganga Surf Camp', 'Baganga', 'Domestic (Other Province)', 'Male', 5],
            ['2026-08-20', 'Mati City Pension House', 'City of Mati', 'Local (Same Province)', 'Female', 2],
            ['2026-08-19', 'Pujada Bay View Inn', 'Governor Generoso', 'Domestic (Other Province)', 'Female', 4],
            ['2026-08-19', 'Botanika Nature Resort', 'City of Mati', 'Foreign', 'Male', 3],
            ['2026-08-18', 'Hamiguitan Base Camp Lodge', 'San Isidro', 'Domestic (Other Province)', 'Male', 6],
            ['2026-08-18', 'Cateel Riverside Diner', 'Cateel', 'Local (Same Province)', 'Female', 5],
            ['2026-08-17', 'Davao Oriental Pasalubong Center', 'City of Mati', 'Domestic (Other Province)', 'Female', 8],
            ['2026-08-17', 'Badjao Seafront Restaurant', 'City of Mati', 'Foreign', 'Male', 2],
            ['2026-08-16', 'Aliwagwag Eco-Lodge', 'Cateel', 'Domestic (Other Province)', 'Male', 4],
            ['2026-08-16', 'Dahican Surf Guides & Tours', 'City of Mati', 'Local (Same Province)', 'Female', 3],
            ['2026-08-15', 'Baganga Surf Camp', 'Baganga', 'Foreign', 'Male', 2],
            ['2026-08-15', 'Botanika Nature Resort', 'City of Mati', 'Domestic (Other Province)', 'Female', 5],
            ['2026-08-14', 'Pujada Bay View Inn', 'Governor Generoso', 'Local (Same Province)', 'Male', 3],
            ['2026-08-14', 'Mati City Pension House', 'City of Mati', 'Domestic (Other Province)', 'Female', 4],
            ['2026-08-13', 'Hamiguitan Base Camp Lodge', 'San Isidro', 'Foreign', 'Male', 7],
            ['2026-08-13', 'Cateel Riverside Diner', 'Cateel', 'Domestic (Other Province)', 'Female', 3],
            ['2026-08-12', 'Botanika Nature Resort', 'City of Mati', 'Local (Same Province)', 'Male', 2],
            ['2026-08-12', 'Badjao Seafront Restaurant', 'City of Mati', 'Foreign', 'Female', 4],
            ['2026-08-11', 'Aliwagwag Eco-Lodge', 'Cateel', 'Domestic (Other Province)', 'Female', 6],
            ['2026-08-11', 'Baganga Surf Camp', 'Baganga', 'Local (Same Province)', 'Male', 3],
        ];

        return collect($rows)->map(fn ($row, $i) => [
            'id' => 'AR-'.(24801 - $i),
            'date' => $row[0],
            'establishment' => $row[1],
            'municipality' => $row[2],
            'classification' => $row[3],
            'gender' => $row[4],
            'visitors' => $row[5],
        ])->all();
    }

    /**
     * @return array<int, array{name: string, subject: string, date: string, language: string, sentiment: string, polarity: float, text: string}>
     */
    public static function feedback(): array
    {
        $rows = [
            ['Rica M.', 'Dahican Beach', '2026-08-22', 'English', 'Positive', 0.82, 'Woke up early for the sunrise and had the whole shoreline to myself. The sand is so fine and the skimboard rentals right on the beach made it an easy first try.'],
            ['Josel T.', 'Aliwagwag Falls Eco-Park', '2026-08-21', 'English', 'Positive', 0.76, "Genuinely one of the most beautiful falls I've hiked to in Mindanao. The canopy walk gives you a view of nearly every tier."],
            ['Grace A.', 'Mount Hamiguitan Range Wildlife Sanctuary', '2026-08-20', 'English', 'Neutral', 0.15, 'The pygmy forest at the summit is unreal. Trek is long, so book a guide through the tourism office in advance.'],
            ['Marco D.', 'Botanika Nature Resort', '2026-08-19', 'English', 'Positive', 0.88, 'Stayed two nights and did not want to leave. Garden villas were quiet and the farm-to-table breakfast was a highlight.'],
            ['Kim Soo-jin', 'Botanika Nature Resort', '2026-08-18', 'English', 'Positive', 0.71, 'Beautiful sunrise from the room and the staff prepared a birthday surprise for my mother. Very warm hospitality.'],
            ['Marites A.', 'Dahican Beach', '2026-08-17', 'Filipino', 'Positive', 0.68, 'Napakaganda ng dagat, malinis at tahimik. Napakabait ng mga tao dito sa Dahican.'],
            ['Anonymous', 'Aliwagwag Falls Eco-Park', '2026-08-16', 'English', 'Neutral', 0.05, 'The falls are incredibly beautiful but the road going there is very difficult, with many potholes.'],
            ['Junjun P.', 'Badjao Seafront Restaurant', '2026-08-16', 'Bisaya', 'Positive', 0.6, 'Lami kaayo ang kinilaw ug ang tan-aw sa dagat gikan sa restaurant. Balikan gyud.'],
            ['Hannah R.', 'Cape of San Agustin', '2026-08-15', 'English', 'Negative', -0.42, 'The lighthouse was closed when we arrived with no signage about visiting hours. Wasted almost an hour driving out there.'],
            ['Elena V.', 'Pusan Point', '2026-08-14', 'English', 'Positive', 0.55, 'Perfect spot to catch the first sunrise in the Philippines. Bring a jacket, it gets windy on the cliff.'],
            ['Noel S.', 'Baganga Surf Camp', '2026-08-13', 'English', 'Neutral', 0.1, 'Waves were decent but the camp ran out of boards on a busy weekend. Staff were apologetic and helpful though.'],
            ['Camille F.', 'Sleeping Dinosaur Island', '2026-08-12', 'English', 'Positive', 0.64, 'Snorkeling here was amazing, super clear water. Would love more boat schedules though.'],
            ['Rey J.', 'Dahican Surf Guides & Tours', '2026-08-11', 'English', 'Negative', -0.31, 'Our lesson started 40 minutes late and felt rushed afterward. The instructor was friendly but the scheduling needs work.'],
            ['Aira L.', 'Pujada Bay', '2026-08-10', 'Filipino', 'Positive', 0.73, 'Napakapayapa ng Pujada Bay, sulit ang island hopping tour. Sana mas maraming signage sa mga bangka.'],
            ['Chris O.', 'Subangan Museum', '2026-08-09', 'English', 'Positive', 0.5, 'Small but well-curated museum. Good introduction to the province before heading out to the beaches.'],
        ];

        return collect($rows)->map(fn ($row, $i) => [
            'id' => 'FB-'.(3201 - $i),
            'name' => $row[0],
            'subject' => $row[1],
            'date' => $row[2],
            'language' => $row[3],
            'sentiment' => $row[4],
            'polarity' => $row[5],
            'text' => $row[6],
        ])->all();
    }

    /**
     * @return array<int, array{name: string, email: string, role: string, assignment: string, status: string, lastActive: string}>
     */
    public static function users(): array
    {
        $rows = [
            ['Ma. Elena Bautista', 'ebautista@davaooriental.gov.ph', 'PTO Administrator', 'Provincial Tourism Office', 'Active', '2026-08-22'],
            ['Arnel Dizon', 'adizon@mati.gov.ph', 'LGU Tourism Personnel', 'City of Mati', 'Active', '2026-08-22'],
            ['Front Desk Account', 'frontdesk@botanikaresort.ph', 'Tourism Establishment', 'Botanika Nature Resort', 'Active', '2026-08-21'],
            ['Jonas Reyes', 'jreyes@cateel.gov.ph', 'LGU Tourism Personnel', 'Cateel', 'Active', '2026-08-20'],
            ['Front Desk Account', 'frontdesk@badjaoseafront.ph', 'Tourism Establishment', 'Badjao Seafront Restaurant', 'Active', '2026-08-19'],
            ['Liza Mangubat', 'lmangubat@baganga.gov.ph', 'LGU Tourism Personnel', 'Baganga', 'Active', '2026-08-18'],
            ['Front Desk Account', 'frontdesk@aliwagwageco.ph', 'Tourism Establishment', 'Aliwagwag Eco-Lodge', 'Active', '2026-08-16'],
            ['Reuben Castillo', 'rcastillo@govgeneroso.gov.ph', 'LGU Tourism Personnel', 'Governor Generoso', 'Inactive', '2026-06-02'],
            ['Front Desk Account', 'frontdesk@dahicansurf.ph', 'Tourism Establishment', 'Dahican Surf Guides & Tours', 'Active', '2026-08-11'],
            ['Patricia Uy', 'puy@sanisidro.gov.ph', 'LGU Tourism Personnel', 'San Isidro', 'Active', '2026-08-13'],
        ];

        return collect($rows)->map(fn ($row) => [
            'name' => $row[0],
            'email' => $row[1],
            'role' => $row[2],
            'assignment' => $row[3],
            'status' => $row[4],
            'lastActive' => $row[5],
        ])->all();
    }

    /**
     * @return array<int, array{type: string, title: string, description: string, icon: string, time: string}>
     */
    public static function recentActivity(): array
    {
        $rows = [
            ['arrival', 'New arrival report submitted', 'Botanika Nature Resort filed 4 new arrivals for August 22.', 'ti-users', '2 hours ago'],
            ['feedback', 'New tourist feedback received', 'A 5-star review was left for Dahican Beach.', 'ti-message-2', '5 hours ago'],
            ['establishment', 'Establishment information updated', 'Badjao Seafront Restaurant updated its operating hours.', 'ti-building-store', 'Yesterday'],
            ['destination', 'Destination information updated', 'Aliwagwag Falls Eco-Park added new trail safety notes.', 'ti-map-pin', 'Yesterday'],
            ['arrival', 'New arrival report submitted', 'Aliwagwag Eco-Lodge filed 6 new arrivals for August 21.', 'ti-users', '2 days ago'],
            ['feedback', 'New tourist feedback received', 'A negative review flagged for Cape of San Agustin needs review.', 'ti-message-2', '2 days ago'],
            ['user', 'New LGU account created', 'A new LGU Tourism Personnel account was created for San Isidro.', 'ti-user-circle', '3 days ago'],
        ];

        return collect($rows)->map(fn ($row) => [
            'type' => $row[0],
            'title' => $row[1],
            'description' => $row[2],
            'icon' => $row[3],
            'time' => $row[4],
        ])->all();
    }

    /**
     * Dashboard summary KPI cards.
     *
     * @return array<int, array{label: string, value: string, delta: string, tone: string}>
     */
    public static function dashboardSummary(): array
    {
        return [
            ['label' => 'Tourist Arrivals (YTD)', 'value' => '308,262', 'delta' => '+12.4% vs 2025', 'tone' => 'success'],
            ['label' => 'Active Destinations', 'value' => '46', 'delta' => 'Across 11 municipalities', 'tone' => 'neutral'],
            ['label' => 'Tourism Establishments', 'value' => '191', 'delta' => '+8 this quarter', 'tone' => 'success'],
            ['label' => 'Registered Municipalities', 'value' => '11', 'delta' => 'All reporting', 'tone' => 'neutral'],
            ['label' => 'Tourist Feedback', 'value' => '1,344', 'delta' => '+251 this month', 'tone' => 'success'],
        ];
    }

    /**
     * Arrival trend series keyed by period filter.
     *
     * @return array<string, array<int, array{label: string, value: int}>>
     */
    public static function arrivalTrend(): array
    {
        return [
            'today' => [
                ['label' => '6am', 'value' => 12], ['label' => '9am', 'value' => 28], ['label' => '12pm', 'value' => 41],
                ['label' => '3pm', 'value' => 35], ['label' => '6pm', 'value' => 22], ['label' => '9pm', 'value' => 9],
            ],
            'week' => [
                ['label' => 'Mon', 'value' => 640], ['label' => 'Tue', 'value' => 705], ['label' => 'Wed', 'value' => 690],
                ['label' => 'Thu', 'value' => 760], ['label' => 'Fri', 'value' => 890], ['label' => 'Sat', 'value' => 1120],
                ['label' => 'Sun', 'value' => 980],
            ],
            'month' => [
                ['label' => 'Wk 1', 'value' => 6800], ['label' => 'Wk 2', 'value' => 7200],
                ['label' => 'Wk 3', 'value' => 7900], ['label' => 'Wk 4', 'value' => 8450],
            ],
            'year' => [
                ['label' => 'Sep', 'value' => 18400], ['label' => 'Oct', 'value' => 19800], ['label' => 'Nov', 'value' => 21200],
                ['label' => 'Dec', 'value' => 27600], ['label' => 'Jan', 'value' => 24100], ['label' => 'Feb', 'value' => 22300],
                ['label' => 'Mar', 'value' => 23900], ['label' => 'Apr', 'value' => 26500], ['label' => 'May', 'value' => 25200],
                ['label' => 'Jun', 'value' => 27100], ['label' => 'Jul', 'value' => 29800], ['label' => 'Aug', 'value' => 31260],
            ],
        ];
    }

    /**
     * @return array<int, array{rank: int, destination: string, municipality: string, visits: int, trend: string}>
     */
    public static function destinationPerformance(): array
    {
        $rows = [
            ['Dahican Beach', 'City of Mati', 42800, 'up'],
            ['Mount Hamiguitan Range Wildlife Sanctuary', 'San Isidro', 31500, 'up'],
            ['Aliwagwag Falls Eco-Park', 'Cateel', 27950, 'flat'],
            ['Pujada Bay', 'City of Mati', 19600, 'up'],
            ['Sleeping Dinosaur Island', 'City of Mati', 15200, 'down'],
            ['Cape of San Agustin', 'Governor Generoso', 12100, 'flat'],
            ['Pusan Point', 'Governor Generoso', 10850, 'up'],
            ['Subangan Museum', 'City of Mati', 6400, 'down'],
        ];

        return collect($rows)->values()->map(fn ($row, $i) => [
            'rank' => $i + 1,
            'destination' => $row[0],
            'municipality' => $row[1],
            'visits' => $row[2],
            'trend' => $row[3],
        ])->all();
    }

    /**
     * @return array<int, array{municipality: string, visits: int}>
     */
    public static function municipalityComparison(): array
    {
        return [
            ['municipality' => 'City of Mati', 'visits' => 128420],
            ['municipality' => 'Baganga', 'visits' => 34200],
            ['municipality' => 'Cateel', 'visits' => 29850],
            ['municipality' => 'Governor Generoso', 'visits' => 26100],
            ['municipality' => 'San Isidro', 'visits' => 22400],
            ['municipality' => 'Caraga', 'visits' => 15600],
            ['municipality' => 'Manay', 'visits' => 12300],
            ['municipality' => 'Tarragona', 'visits' => 9800],
            ['municipality' => 'Boston', 'visits' => 8100],
            ['municipality' => 'Lupon', 'visits' => 7400],
            ['municipality' => 'Banaybanay', 'visits' => 6200],
        ];
    }

    /**
     * Positive-sentiment share over time, for the Experience Analytics trend chart.
     *
     * @return array<string, array<int, array{label: string, value: int}>>
     */
    public static function sentimentTrend(): array
    {
        return [
            'week' => [
                ['label' => 'Mon', 'value' => 68], ['label' => 'Tue', 'value' => 71], ['label' => 'Wed', 'value' => 65],
                ['label' => 'Thu', 'value' => 74], ['label' => 'Fri', 'value' => 78], ['label' => 'Sat', 'value' => 70],
                ['label' => 'Sun', 'value' => 73],
            ],
            'month' => [
                ['label' => 'Wk 1', 'value' => 69], ['label' => 'Wk 2', 'value' => 72],
                ['label' => 'Wk 3', 'value' => 70], ['label' => 'Wk 4', 'value' => 75],
            ],
            'year' => [
                ['label' => 'Sep', 'value' => 64], ['label' => 'Oct', 'value' => 66], ['label' => 'Nov', 'value' => 63],
                ['label' => 'Dec', 'value' => 68], ['label' => 'Jan', 'value' => 70], ['label' => 'Feb', 'value' => 69],
                ['label' => 'Mar', 'value' => 71], ['label' => 'Apr', 'value' => 70], ['label' => 'May', 'value' => 72],
                ['label' => 'Jun', 'value' => 71], ['label' => 'Jul', 'value' => 73], ['label' => 'Aug', 'value' => 72],
            ],
        ];
    }

    /**
     * Overall sentiment split, used by the dashboard and Experience Analytics.
     *
     * @return array{positive: int, neutral: int, negative: int}
     */
    public static function sentimentBreakdown(): array
    {
        return ['positive' => 967, 'neutral' => 258, 'negative' => 119];
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, icon: string}>
     */
    public static function reportTypes(): array
    {
        return [
            ['key' => 'arrivals', 'label' => 'Tourist Arrival Report', 'description' => 'Arrivals by date, establishment, municipality, and visitor classification.', 'icon' => 'ti-users'],
            ['key' => 'statistics', 'label' => 'Tourism Statistics Report', 'description' => 'Province-wide visitation trends and municipality comparisons.', 'icon' => 'ti-chart-line'],
            ['key' => 'destinations', 'label' => 'Destination Performance Report', 'description' => 'Ranked destination visits with period-over-period trend.', 'icon' => 'ti-map-pin'],
            ['key' => 'feedback', 'label' => 'Tourist Feedback Report', 'description' => 'Raw feedback entries with sentiment and polarity scores.', 'icon' => 'ti-message-2'],
            ['key' => 'experience', 'label' => 'Tourist Experience Analytics Report', 'description' => 'Sentiment breakdown and trends by destination and establishment.', 'icon' => 'ti-heart-handshake'],
        ];
    }

    /**
     * Previously generated reports, for the Reports page history list.
     *
     * @return array<int, array{name: string, type: string, range: string, generatedAt: string, status: string}>
     */
    public static function reportHistory(): array
    {
        return [
            ['name' => 'Tourist Arrival Report — July 2026', 'type' => 'Tourist Arrival Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-02', 'status' => 'Ready'],
            ['name' => 'Tourism Statistics Report — Q2 2026', 'type' => 'Tourism Statistics Report', 'range' => 'Apr 1 – Jun 30, 2026', 'generatedAt' => '2026-07-05', 'status' => 'Ready'],
            ['name' => 'Destination Performance Report — June 2026', 'type' => 'Destination Performance Report', 'range' => 'Jun 1 – Jun 30, 2026', 'generatedAt' => '2026-07-01', 'status' => 'Ready'],
            ['name' => 'Tourist Feedback Report — July 2026', 'type' => 'Tourist Feedback Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-01', 'status' => 'Ready'],
        ];
    }
}
