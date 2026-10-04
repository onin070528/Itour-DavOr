<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\OperationLog;
use App\Models\User;

test('a PTO administrator can toggle a category QR switch, and it is audit-logged with old and new values', function () {
    $pto = User::factory()->create(['role' => UserRole::PtoAdministrator]);
    $category = Category::query()->create([
        'cat_name' => 'Accommodation', 'cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true,
    ]);

    $response = test()->actingAs($pto)->put(route('pto.settings.categories.toggleQr', $category));

    $response->assertRedirect();
    expect($category->fresh()->cat_is_qr_enabled)->toBeFalse();

    $log = OperationLog::where('entity_type', 'category')->where('entity_id', $category->cat_id)->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->old_values)->toBe(['cat_is_qr_enabled' => true]);
    expect($log->new_values)->toBe(['cat_is_qr_enabled' => false]);

    // Toggling back on re-activates the exact same category row — nothing
    // about establishments, QR codes, or arrival history was touched.
    test()->actingAs($pto)->put(route('pto.settings.categories.toggleQr', $category))->assertRedirect();
    expect($category->fresh()->cat_is_qr_enabled)->toBeTrue();
});

test('a non-PTO user cannot toggle a category QR switch', function () {
    $lgu = User::factory()->create(['role' => UserRole::Lgu]);
    $category = Category::query()->create(['cat_name' => 'Accommodation', 'cat_sort_order' => 1, 'cat_is_active' => true, 'cat_is_qr_enabled' => true]);

    test()->actingAs($lgu)->put(route('pto.settings.categories.toggleQr', $category))->assertForbidden();
});
