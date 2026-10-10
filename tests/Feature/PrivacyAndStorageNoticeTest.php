<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — privacy and storage notice.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\ReportingMethod;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Str;

test('the privacy notice page loads and covers every required section', function () {
    test()->get(route('privacy'))
        ->assertOk()
        ->assertSee('Privacy Notice')
        ->assertSee('Who we are')
        ->assertSee('What we collect')
        ->assertSee('Why we collect it')
        ->assertSee('Who sees it')
        ->assertSee('Third parties')
        ->assertSee('Mapbox')
        ->assertSee('How long we keep it')
        ->assertSee('Not yet confirmed.')
        ->assertSee('Your rights')
        ->assertSee('Data Privacy Act')
        ->assertSee('Contact');
});

test('the privacy notice discloses translation of non-English feedback and claims no other AI use', function () {
    // Objective 4: OpenAI receives only non-English feedback text, for
    // translation; scoring uses the fixed word list, not AI. Ori (the
    // chatbot) is still a client-side placeholder and is not listed as a
    // third party.
    test()->get(route('privacy'))
        ->assertSee('OpenAI (translation service)')
        ->assertSee('external translation service')
        ->assertSee('not in English')
        ->assertSee('Your name, visit date, and IP address are not sent with it.')
        ->assertSee('fixed tourism word list')
        ->assertDontSee('<strong>Ori', false);
});

test('the public footer links to the privacy notice', function () {
    test()->get(route('home'))
        ->assertOk()
        ->assertSee(route('privacy'), false);
});

test('the storage and cache usage notice markup appears on a public page', function () {
    test()->get(route('home'))
        ->assertOk()
        ->assertSee('id="storage-notice"', false)
        ->assertSee('Storage &amp; Cache Usage', false)
        ->assertSee('id="storage-notice-dismiss"', false)
        ->assertSee('id="storage-notice-understand"', false)
        ->assertSee(route('privacy'), false);
});

test('the storage and cache usage notice does not appear inside a portal', function () {
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    test()->actingAs($pto)->get(route('pto.dashboard'))
        ->assertOk()
        ->assertDontSee('id="storage-notice"', false);
});

test('the QR arrival form links to the privacy notice above its submit button', function () {
    $category = Category::query()->firstOrCreate(
        ['cat_name' => 'Accommodation'],
        ['cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]
    );

    $listing = Listing::query()->create([
        'lst_slug' => Str::slug('privacy-qr-fixture-'.Str::random(6)),
        'lst_name' => 'Privacy QR Fixture Inn',
        'lst_category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'lst_municipality' => 'City of Mati',
        'lst_barangay' => 'Dahican',
        'lst_status' => 'PUBLISHED',
    ]);
    $listing->forceFill(['lst_reporting_mode' => ReportingMethod::OnlineItour])->save();
    User::factory()->create([
        'usr_role' => UserRole::Establishment,
        'usr_organization_name' => $listing->lst_name,
        'usr_organization_subtitle' => 'Brgy. Dahican, City of Mati',
        'lst_id' => $listing->lst_id,
    ]);

    test()->get(route('lgu.establishmentQr', $listing->lst_uuid))
        ->assertOk()
        ->assertSee('Provincial Tourism Office of Davao Oriental')
        ->assertSee(route('privacy'), false);
});
