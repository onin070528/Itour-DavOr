<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Single source of the establishment Category -> Establishment Type list (form, filters, validation).
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Establishment types per category
    |--------------------------------------------------------------------------
    |
    | Keyed by tblcategories.cat_name (never by cat_id, which differs per
    | environment). Every key must match a seeded category name exactly
    | (Database\Seeders\CategorySeeder). "Tourist Destinations" is
    | deliberately absent: it is not an establishment category, so it has no
    | establishment types. Read through App\Models\Category::
    | establishmentTypes() and validated by App\Rules\
    | EstablishmentTypeBelongsToCategory — never copy this list elsewhere.
    |
    */

    'types' => [
        'Accommodation' => ['Hotel', 'Resort', 'Homestay', 'Inn / Lodge', 'Apartelle', 'Other Accommodation'],
        'Food & Dining' => ['Restaurant', 'Cafe', 'Restobar', 'Eatery', 'Catering Service', 'Other Food & Dining'],
        'Farm & Agri-Tourism' => ['Farm Tourism Site', 'Agri-Tourism Farm', 'Farm Resort', 'Other Agri-Tourism'],
        'Wellness & Spa' => ['Spa', 'Wellness Center', 'Massage / Wellness Service', 'Other Wellness & Spa'],
        'Travel & Tours' => ['Travel Agency', 'Tour Operator', 'Tour Guide Service', 'Other Travel & Tours'],
        'Tourist Transport' => ['Van / Shuttle Service', 'Boat / Water Transport', 'Car Rental', 'Motorcycle Rental', 'Other Tourist Transport'],
        'Recreation & Activities' => ['Adventure Park', 'Diving / Water Activity', 'Outdoor Recreation', 'Recreational Facility', 'Other Recreation & Activities'],
        'MICE & Events' => ['Convention Center', 'Function Hall', 'Events Venue', 'Conference Facility', 'Other MICE & Events'],
        'Others' => ['Other Tourism Establishment'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tour guide type
    |--------------------------------------------------------------------------
    |
    | The one type that is list-only: no map pin, no QR code, no arrival
    | records, and a license number is required (App\Models\Listing::
    | isTourGuide()). Must be one of the 'Travel & Tours' types above.
    |
    */

    'tour_guide_type' => 'Tour Guide Service',

];
