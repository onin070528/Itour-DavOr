<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — pto announcements.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;

function makePtoForAnnouncements(): User
{
    return User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
}

test('a PTO administrator can create an announcement, which starts as an unpublished draft', function () {
    $pto = makePtoForAnnouncements();

    $response = test()->actingAs($pto)->post(route('pto.announcements.store'), [
        'ann_title' => 'Dahican Surf Festival',
        'ann_body' => 'Join us for the annual surf festival this weekend.',
        'ann_type' => 'Event',
        'ann_start_date' => '2026-11-01',
        'ann_end_date' => '2026-11-03',
    ]);

    $response->assertSessionHasNoErrors();
    $announcement = Announcement::query()->where('ann_title', 'Dahican Surf Festival')->first();
    expect($announcement)->not->toBeNull();
    expect($announcement->ann_is_published)->toBeFalse();
});

test('creating an announcement requires a title, body, and type', function () {
    $pto = makePtoForAnnouncements();

    test()->actingAs($pto)->post(route('pto.announcements.store'), [])
        ->assertSessionHasErrors(['ann_title', 'ann_body', 'ann_type']);
});

test('the end date cannot be before the start date', function () {
    $pto = makePtoForAnnouncements();

    test()->actingAs($pto)->post(route('pto.announcements.store'), [
        'ann_title' => 'Bad Dates', 'ann_body' => 'x', 'ann_type' => 'Advisory',
        'ann_start_date' => '2026-11-10', 'ann_end_date' => '2026-11-01',
    ])->assertSessionHasErrors('ann_end_date');
});

test('publishing then unpublishing an announcement toggles its visibility on the landing page', function () {
    $pto = makePtoForAnnouncements();
    $announcement = Announcement::query()->create([
        'ann_title' => 'Road Advisory', 'ann_body' => 'Road closed for repairs.', 'ann_type' => 'Advisory',
        'ann_is_published' => false,
    ]);

    test()->get(route('home'))->assertDontSee('Road Advisory');

    test()->actingAs($pto)->put(route('pto.announcements.togglePublish', $announcement))->assertRedirect();
    expect($announcement->fresh()->ann_is_published)->toBeTrue();
    test()->get(route('home'))->assertSee('Road Advisory');

    test()->actingAs($pto)->put(route('pto.announcements.togglePublish', $announcement))->assertRedirect();
    expect($announcement->fresh()->ann_is_published)->toBeFalse();
    test()->get(route('home'))->assertDontSee('Road Advisory');
});

test('a non-PTO user cannot manage announcements', function () {
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu]);

    test()->actingAs($lgu)->get(route('pto.announcements.index'))->assertForbidden();
    test()->actingAs($lgu)->post(route('pto.announcements.store'), [])->assertForbidden();
});
