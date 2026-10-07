<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — pto hotlines.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Hotline;
use App\Models\User;

function makePtoForHotlines(): User
{
    return User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
}

test('a PTO administrator can create a hotline', function () {
    $pto = makePtoForHotlines();

    $response = test()->actingAs($pto)->post(route('pto.hotlines.store'), [
        'hot_agency_name' => 'Mati City Police Station',
        'hot_agency_type' => 'Police',
        'hot_contact_number' => '(087) 388 1234',
    ]);

    $response->assertSessionHasNoErrors();
    $hotline = Hotline::query()->where('hot_agency_name', 'Mati City Police Station')->first();
    expect($hotline)->not->toBeNull();
    expect($hotline->hot_scope)->toBe('Province-wide');
    expect($hotline->hot_is_active)->toBeTrue();
});

test('creating a hotline requires a name, agency type, and contact number', function () {
    $pto = makePtoForHotlines();

    test()->actingAs($pto)->post(route('pto.hotlines.store'), [])
        ->assertSessionHasErrors(['hot_agency_name', 'hot_agency_type', 'hot_contact_number']);
});

test('deactivating a hotline hides it without deleting it, and reactivating restores it', function () {
    $pto = makePtoForHotlines();
    $hotline = Hotline::query()->create([
        'hot_agency_name' => 'Coast Guard Mati', 'hot_agency_type' => 'Coast Guard',
        'hot_contact_number' => '0917 000 0000', 'hot_sort_order' => 1,
    ]);

    test()->actingAs($pto)->put(route('pto.hotlines.deactivate', $hotline))->assertRedirect();
    expect($hotline->fresh()->hot_is_active)->toBeFalse();
    expect(Hotline::query()->whereKey($hotline->hot_id)->exists())->toBeTrue();

    test()->actingAs($pto)->put(route('pto.hotlines.deactivate', $hotline))->assertRedirect();
    expect($hotline->fresh()->hot_is_active)->toBeTrue();
});

test('reordering swaps sort order with the adjacent hotline', function () {
    $pto = makePtoForHotlines();
    $first = Hotline::query()->create(['hot_agency_name' => 'A', 'hot_agency_type' => 'Police', 'hot_contact_number' => '1', 'hot_sort_order' => 1]);
    $second = Hotline::query()->create(['hot_agency_name' => 'B', 'hot_agency_type' => 'Fire', 'hot_contact_number' => '2', 'hot_sort_order' => 2]);

    test()->actingAs($pto)->put(route('pto.hotlines.reorder', $first), ['direction' => 'down'])->assertRedirect();

    expect($first->fresh()->hot_sort_order)->toBe(2);
    expect($second->fresh()->hot_sort_order)->toBe(1);
});

test('a non-PTO user cannot manage hotlines', function () {
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu]);
    $hotline = Hotline::query()->create(['hot_agency_name' => 'A', 'hot_agency_type' => 'Police', 'hot_contact_number' => '1', 'hot_sort_order' => 1]);

    test()->actingAs($lgu)->get(route('pto.hotlines.index'))->assertForbidden();
    test()->actingAs($lgu)->post(route('pto.hotlines.store'), [])->assertForbidden();
    test()->actingAs($lgu)->put(route('pto.hotlines.deactivate', $hotline))->assertForbidden();
});

test('the public hotlines page groups active hotlines by agency type and hides deactivated ones', function () {
    Hotline::query()->create(['hot_agency_name' => 'Mati PNP', 'hot_agency_type' => 'Police', 'hot_contact_number' => '117', 'hot_sort_order' => 1, 'hot_is_active' => true]);
    Hotline::query()->create(['hot_agency_name' => 'Retired Office', 'hot_agency_type' => 'Other', 'hot_contact_number' => '0', 'hot_sort_order' => 2, 'hot_is_active' => false]);

    $response = test()->get(route('hotlines'));

    $response->assertOk();
    $response->assertSee('Mati PNP');
    $response->assertDontSee('Retired Office');
});
