<?php

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

test('the privacy notice page does not claim an AI backend receives data', function () {
    // No OpenAI/AI backend exists today (Ori's replies are a client-side
    // placeholder) — the page must not claim otherwise.
    test()->get(route('privacy'))->assertDontSee('OpenAI');
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
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);

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
        'slug' => Str::slug('privacy-qr-fixture-'.Str::random(6)),
        'name' => 'Privacy QR Fixture Inn',
        'category' => 'accommodation',
        'cat_id' => $category->cat_id,
        'municipality' => 'City of Mati',
        'barangay' => 'Dahican',
        'status' => 'PUBLISHED',
    ]);

    test()->get(route('lgu.establishmentQr', $listing->uuid))
        ->assertOk()
        ->assertSee('Provincial Tourism Office of Davao Oriental')
        ->assertSee(route('privacy'), false);
});
