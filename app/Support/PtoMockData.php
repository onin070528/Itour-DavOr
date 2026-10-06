<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Province-wide mock/read data (arrivals, feedback, users, reports) for the PTO dashboard.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
        $arrRows = [
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

        return collect($arrRows)->map(fn ($arrRow, $i) => [
            'id' => 'AR-'.(24801 - $i),
            'date' => $arrRow[0],
            'establishment' => $arrRow[1],
            'municipality' => $arrRow[2],
            'classification' => $arrRow[3],
            'gender' => $arrRow[4],
            'visitors' => $arrRow[5],
        ])->all();
    }

    /**
     * @return array<int, array{name: string, subject: string, date: string, language: string, sentiment: string, polarity: float, text: string}>
     */
    public static function feedback(): array
    {
        $arrRows = [
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

        return collect($arrRows)->map(fn ($arrRow, $i) => [
            'id' => 'FB-'.(3201 - $i),
            'name' => $arrRow[0],
            'subject' => $arrRow[1],
            'date' => $arrRow[2],
            'language' => $arrRow[3],
            'sentiment' => $arrRow[4],
            'polarity' => $arrRow[5],
            'text' => $arrRow[6],
        ])->all();
    }

    /**
     * @return array<int, array{name: string, email: string, role: string, assignment: string, status: string, lastActive: string}>
     */
    public static function users(): array
    {
        return User::query()
            ->orderByDesc('usr_last_login_at')
            ->get()
            ->map(fn ($objUser) => [
                'id' => $objUser->usr_id,
                'name' => $objUser->usr_name,
                'email' => $objUser->usr_email,
                'role' => $objUser->usr_role->title(),
                'roleValue' => $objUser->usr_role->value,
                // Mirrors Pto\UsersController's write-side mapping: the LGU
                // role's meaningful field is usr_organization_subtitle (the
                // municipality), every other role's is usr_organization_name.
                'assignment' => $objUser->usr_role === UserRole::Lgu ? $objUser->usr_organization_subtitle : $objUser->usr_organization_name,
                'municipalityId' => $objUser->mun_id,
                'establishmentId' => $objUser->lst_id,
                'phone' => $objUser->usr_phone,
                'status' => $objUser->usr_status,
                'lastActive' => $objUser->usr_last_login_at?->toDateString() ?? $objUser->usr_created_at->toDateString(),
            ])
            ->all();
    }

    /**
     * The original, hand-authored account list — the seed data
     * database/seeders/UserSeeder.php loads into the real `tbl_users` table.
     * Not used for reads anymore (see users() above).
     *
     * @return array<int, array{name: string, email: string, role: string, assignment: string, status: string, lastActive: string}>
     */
    public static function seedUsers(): array
    {
        $arrRows = [
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

        return collect($arrRows)->map(fn ($arrRow) => [
            'name' => $arrRow[0],
            'email' => $arrRow[1],
            'role' => $arrRow[2],
            'assignment' => $arrRow[3],
            'status' => $arrRow[4],
            'lastActive' => $arrRow[5],
        ])->all();
    }

    /**
     * @return array<int, array{type: string, title: string, description: string, icon: string, time: string}>
     */
    public static function recentActivity(): array
    {
        $arrRows = [
            ['arrival', 'New arrival report submitted', 'Botanika Nature Resort filed 4 new arrivals for August 22.', 'ti-users', '2 hours ago'],
            ['feedback', 'New tourist feedback received', 'A 5-star review was left for Dahican Beach.', 'ti-message-2', '5 hours ago'],
            ['establishment', 'Establishment information updated', 'Badjao Seafront Restaurant updated its operating hours.', 'ti-building-store', 'Yesterday'],
            ['destination', 'Destination information updated', 'Aliwagwag Falls Eco-Park added new trail safety notes.', 'ti-map-pin', 'Yesterday'],
            ['arrival', 'New arrival report submitted', 'Aliwagwag Eco-Lodge filed 6 new arrivals for August 21.', 'ti-users', '2 days ago'],
            ['feedback', 'New tourist feedback received', 'A negative review flagged for Cape of San Agustin needs review.', 'ti-message-2', '2 days ago'],
            ['user', 'New LGU account created', 'A new LGU Tourism Personnel account was created for San Isidro.', 'ti-user-circle', '3 days ago'],
        ];

        return collect($arrRows)->map(fn ($arrRow) => [
            'type' => $arrRow[0],
            'title' => $arrRow[1],
            'description' => $arrRow[2],
            'icon' => $arrRow[3],
            'time' => $arrRow[4],
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
        $arrRows = [
            ['Dahican Beach', 'City of Mati', 42800, 'up'],
            ['Mount Hamiguitan Range Wildlife Sanctuary', 'San Isidro', 31500, 'up'],
            ['Aliwagwag Falls Eco-Park', 'Cateel', 27950, 'flat'],
            ['Pujada Bay', 'City of Mati', 19600, 'up'],
            ['Sleeping Dinosaur Island', 'City of Mati', 15200, 'down'],
            ['Cape of San Agustin', 'Governor Generoso', 12100, 'flat'],
            ['Pusan Point', 'Governor Generoso', 10850, 'up'],
            ['Subangan Museum', 'City of Mati', 6400, 'down'],
        ];

        return collect($arrRows)->values()->map(fn ($arrRow, $i) => [
            'rank' => $i + 1,
            'destination' => $arrRow[0],
            'municipality' => $arrRow[1],
            'visits' => $arrRow[2],
            'trend' => $arrRow[3],
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
     * @return array<int, array{key: string, label: string, description: string, icon: string, filters: array<int, string>}>
     */
    public static function reportTypes(): array
    {
        return [
            ['key' => 'arrivals', 'label' => 'Tourist Arrival Report', 'description' => 'Arrivals by date, establishment, municipality, and visitor classification.', 'icon' => 'ti-users', 'filters' => ['classification', 'gender', 'municipality']],
            ['key' => 'statistics', 'label' => 'Tourism Statistics Report', 'description' => 'Province-wide visitation trends and municipality comparisons.', 'icon' => 'ti-chart-line', 'filters' => []],
            ['key' => 'destinations', 'label' => 'Destination Performance Report', 'description' => 'Ranked destination visits with period-over-period trend.', 'icon' => 'ti-map-pin', 'filters' => ['destination']],
            ['key' => 'establishments', 'label' => 'Establishment Report', 'description' => 'Registered establishments by category and municipality across the province.', 'icon' => 'ti-building-store', 'filters' => ['category', 'municipality']],
            ['key' => 'feedback', 'label' => 'Tourist Feedback Report', 'description' => 'Raw feedback entries with sentiment and polarity scores.', 'icon' => 'ti-message-2', 'filters' => ['sentiment']],
            ['key' => 'experience', 'label' => 'Tourist Experience Analytics Report', 'description' => 'Sentiment breakdown and trends by destination and establishment.', 'icon' => 'ti-heart-handshake', 'filters' => []],
        ];
    }

    /**
     * Previously generated reports, for the Reports page history list.
     *
     * @return array<int, array{name: string, typeKey: string, type: string, range: string, generatedAt: string, generatedBy: string}>
     */
    public static function reportHistory(): array
    {
        return [
            ['name' => 'Tourist Arrival Report — July 2026', 'typeKey' => 'arrivals', 'type' => 'Tourist Arrival Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-02', 'generatedBy' => 'Ma. Elena Bautista'],
            ['name' => 'Tourism Statistics Report — Q2 2026', 'typeKey' => 'statistics', 'type' => 'Tourism Statistics Report', 'range' => 'Apr 1 – Jun 30, 2026', 'generatedAt' => '2026-07-05', 'generatedBy' => 'Ma. Elena Bautista'],
            ['name' => 'Destination Performance Report — June 2026', 'typeKey' => 'destinations', 'type' => 'Destination Performance Report', 'range' => 'Jun 1 – Jun 30, 2026', 'generatedAt' => '2026-07-01', 'generatedBy' => 'Arnel Dizon'],
            ['name' => 'Tourist Feedback Report — July 2026', 'typeKey' => 'feedback', 'type' => 'Tourist Feedback Report', 'range' => 'Jul 1 – Jul 31, 2026', 'generatedAt' => '2026-08-01', 'generatedBy' => 'Ma. Elena Bautista'],
        ];
    }

    /**
     * Pre-shaped content for each report type's preview panel, keyed by
     * report-type key. Drives the Reports page's report preview: summary
     * stat cards, an optional chart, an optional breakdown table, and an
     * optional detailed-records table.
     *
     * @return array<string, array{summary: array<int, array{label: string, value: string}>, chart: array<string, mixed>|null, breakdown: array{label: string, columns: array<int, string>, rows: array<int, array<int, string>>}|null, columns: array<int, string>, rows: array<int, array<int, string>>, filterable: bool, empty: bool}>
     */
    public static function reportPreviewData(): array
    {
        $arrArrivals = self::arrivals();
        $arrDestinations = self::destinationPerformance();
        $arrEstablishments = self::establishmentDirectory();
        $arrFeedback = self::feedback();
        $arrSentiment = self::sentimentBreakdown();
        $intSentimentTotal = array_sum($arrSentiment);

        $intArrivalsTotal = collect($arrArrivals)->sum('visitors');
        $intForeignTotal = collect($arrArrivals)->where('classification', 'Foreign')->sum('visitors');
        $objClassificationTotals = collect($arrArrivals)->groupBy('classification')
            ->map(fn ($objRows) => $objRows->sum('visitors'));

        $intEstablishmentsByMunicipality = collect($arrEstablishments)->groupBy('municipality')->map->count();
        $arrMunicipalityBreakdown = collect($arrArrivals)->groupBy('municipality')
            ->map(fn ($objRows, $objMunicipality) => [
                $objMunicipality,
                number_format($objRows->sum('visitors')),
                (string) ($intEstablishmentsByMunicipality[$objMunicipality] ?? 0),
            ])
            ->sortByDesc(fn ($arrRow) => (int) str_replace(',', '', $arrRow[1]))
            ->values()->all();

        $intCategoryTotals = collect($arrEstablishments)->groupBy('category')->map->count();

        return [
            'arrivals' => [
                'summary' => [
                    ['label' => 'Total Arrivals', 'value' => number_format($intArrivalsTotal)],
                    ['label' => 'Domestic Visitors', 'value' => number_format($intArrivalsTotal - $intForeignTotal)],
                    ['label' => 'Foreign Visitors', 'value' => number_format($intForeignTotal)],
                    ['label' => 'Municipalities Covered', 'value' => (string) collect($arrArrivals)->pluck('municipality')->unique()->count()],
                ],
                'chart' => [
                    'type' => 'bar',
                    'title' => 'Visitor Classification Distribution',
                    'items' => $objClassificationTotals->map(fn ($value, $strLabel) => ['label' => $strLabel, 'value' => $value])->values()->all(),
                ],
                'breakdown' => ['label' => 'Municipality', 'columns' => ['Municipality', 'Arrivals', 'Establishments'], 'rows' => $arrMunicipalityBreakdown],
                'columns' => ['Date', 'Establishment', 'Municipality', 'Classification', 'Gender', 'Visitors'],
                'rows' => collect($arrArrivals)->map(fn ($arrRow) => [
                    Carbon::parse($arrRow['date'])->format('M j, Y'),
                    $arrRow['establishment'], $arrRow['municipality'], $arrRow['classification'], $arrRow['gender'], number_format($arrRow['visitors']),
                ])->all(),
                'filterable' => true,
                'empty' => $intArrivalsTotal === 0,
            ],
            'statistics' => [
                'summary' => [
                    ['label' => 'Tourist Arrivals (YTD)', 'value' => '308,262'],
                    ['label' => 'Registered Municipalities', 'value' => '11'],
                    ['label' => 'Active Destinations', 'value' => (string) count($arrDestinations)],
                ],
                'chart' => [
                    'type' => 'trend',
                    'title' => 'Visitor Trend',
                    'labels' => collect(self::arrivalTrend()['month'])->pluck('label')->all(),
                    'values' => collect(self::arrivalTrend()['month'])->pluck('value')->all(),
                ],
                'breakdown' => ['label' => 'Municipality', 'columns' => ['Municipality', 'Arrivals', 'Establishments'], 'rows' => $arrMunicipalityBreakdown],
                'columns' => [],
                'rows' => [],
                'filterable' => false,
                'empty' => false,
            ],
            'destinations' => [
                'summary' => [
                    ['label' => 'Destinations Tracked', 'value' => (string) count($arrDestinations)],
                    ['label' => 'Top Destination', 'value' => $arrDestinations[0]['destination'] ?? '—'],
                ],
                'chart' => [
                    'type' => 'bar',
                    'title' => 'Visits per Destination',
                    'items' => collect($arrDestinations)->map(fn ($arrRow) => ['label' => $arrRow['destination'], 'value' => $arrRow['visits']])->all(),
                ],
                'breakdown' => null,
                'columns' => ['#', 'Destination', 'Municipality', 'Visits', 'Trend'],
                'rows' => collect($arrDestinations)->map(fn ($arrRow) => [
                    (string) $arrRow['rank'], $arrRow['destination'], $arrRow['municipality'], number_format($arrRow['visits']), ucfirst($arrRow['trend']),
                ])->all(),
                'filterable' => false,
                'empty' => count($arrDestinations) === 0,
            ],
            'establishments' => [
                'summary' => [
                    ['label' => 'Registered Establishments', 'value' => (string) count($arrEstablishments)],
                    ['label' => 'Categories Represented', 'value' => (string) $intCategoryTotals->count()],
                ],
                'chart' => [
                    'type' => 'bar',
                    'title' => 'Establishments by Category',
                    'items' => $intCategoryTotals->map(fn ($value, $strLabel) => ['label' => $strLabel, 'value' => $value])->values()->all(),
                ],
                'breakdown' => null,
                'columns' => ['Establishment', 'Category', 'Municipality'],
                'rows' => collect($arrEstablishments)->map(fn ($arrRow) => [$arrRow['name'], $arrRow['category'], $arrRow['municipality']])->all(),
                'filterable' => false,
                'empty' => count($arrEstablishments) === 0,
            ],
            'feedback' => [
                'summary' => [
                    ['label' => 'Feedback Entries', 'value' => (string) count($arrFeedback)],
                    ['label' => 'Positive', 'value' => (string) collect($arrFeedback)->where('sentiment', 'Positive')->count()],
                    ['label' => 'Negative', 'value' => (string) collect($arrFeedback)->where('sentiment', 'Negative')->count()],
                    ['label' => 'Positive Share', 'value' => $intSentimentTotal ? round((collect($arrFeedback)->where('sentiment', 'Positive')->count() / max(count($arrFeedback), 1)) * 100).'%' : '—'],
                ],
                'chart' => ['type' => 'donut', 'positive' => $arrSentiment['positive'], 'neutral' => $arrSentiment['neutral'], 'negative' => $arrSentiment['negative']],
                'breakdown' => null,
                'columns' => ['Date', 'Subject', 'Sentiment', 'Feedback'],
                'rows' => collect($arrFeedback)->map(fn ($arrRow) => [
                    Carbon::parse($arrRow['date'])->format('M j, Y'),
                    $arrRow['subject'], $arrRow['sentiment'], Str::limit($arrRow['text'], 70),
                ])->all(),
                'filterable' => true,
                'empty' => count($arrFeedback) === 0,
            ],
            'experience' => [
                'summary' => [
                    ['label' => 'Feedback Analyzed', 'value' => number_format($intSentimentTotal)],
                    ['label' => 'Positive Share', 'value' => $intSentimentTotal ? round(($arrSentiment['positive'] / $intSentimentTotal) * 100).'%' : '—'],
                    ['label' => 'Negative Entries', 'value' => number_format($arrSentiment['negative'])],
                ],
                'chart' => ['type' => 'donut', 'positive' => $arrSentiment['positive'], 'neutral' => $arrSentiment['neutral'], 'negative' => $arrSentiment['negative']],
                'breakdown' => null,
                'columns' => [],
                'rows' => [],
                'filterable' => false,
                'empty' => $intSentimentTotal === 0,
            ],
        ];
    }
}
