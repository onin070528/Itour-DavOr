<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — pto category settings.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\OperationLog;
use App\Models\User;

test('a PTO administrator can toggle a category QR switch, and it is audit-logged with old and new values', function () {
    $pto = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $category = Category::query()->create([
        'cat_name' => 'Accommodation', 'cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true,
    ]);

    $response = test()->actingAs($pto)->put(route('pto.settings.categories.toggleQr', $category));

    $response->assertRedirect();
    expect($category->fresh()->cat_is_qr_enabled)->toBeFalse();

    $log = OperationLog::where('opl_entity_type', 'category')->where('opl_entity_id', $category->cat_id)->latest('opl_id')->first();
    expect($log)->not->toBeNull();
    expect($log->opl_old_values)->toBe(['cat_is_qr_enabled' => true]);
    expect($log->opl_new_values)->toBe(['cat_is_qr_enabled' => false]);

    // Toggling back on re-activates the exact same category row — nothing
    // about establishments, QR codes, or arrival history was touched.
    test()->actingAs($pto)->put(route('pto.settings.categories.toggleQr', $category))->assertRedirect();
    expect($category->fresh()->cat_is_qr_enabled)->toBeTrue();
});

test('a non-PTO user cannot toggle a category QR switch', function () {
    $lgu = User::factory()->create(['usr_role' => UserRole::Lgu]);
    $category = Category::query()->create(['cat_name' => 'Accommodation', 'cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]);

    test()->actingAs($lgu)->put(route('pto.settings.categories.toggleQr', $category))->assertForbidden();
});
