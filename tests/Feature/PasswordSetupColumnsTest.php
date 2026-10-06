<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Sub-stage A verification — the new users columns exist with safe defaults.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\User;

test('a freshly created user defaults to usr_must_change_password false', function () {
    // The factory never sets these columns, so only the DB's own default
    // is in play — read back with fresh() rather than the in-memory model,
    // since Eloquent doesn't refetch unset attributes after an insert.
    $user = User::factory()->create(['usr_role' => UserRole::PtoAdministrator])->fresh();

    expect($user->usr_must_change_password)->toBeFalse();
    expect($user->mustChangePassword())->toBeFalse();
    expect($user->usr_password_changed_at)->toBeNull();
});

test('an existing account logs in normally and is not treated as needing a password change', function () {
    $user = User::factory()->create([
        'usr_role' => UserRole::PtoAdministrator,
        'usr_password' => 'a-fine-existing-password-123!',
    ]);

    test()->actingAs($user)->get(route('pto.dashboard'))->assertOk();
    expect($user->fresh()->mustChangePassword())->toBeFalse();
});
