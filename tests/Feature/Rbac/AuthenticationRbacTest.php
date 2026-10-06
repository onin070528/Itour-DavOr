<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — authentication rbac.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('a suspended (Inactive) user is blocked from logging in', function () {
    $user = User::factory()->create(['usr_role' => UserRole::PtoAdministrator, 'usr_status' => 'Inactive']);

    $response = test()->post('/login', [
        'email' => $user->usr_email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    test()->assertGuest();
});

test('a suspended account and a wrong password show the identical error message', function () {
    $suspended = User::factory()->create(['usr_role' => UserRole::PtoAdministrator, 'usr_status' => 'Inactive']);
    $active = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);
    $message = 'These credentials do not match our records.';

    // Same wording in both cases — a suspended/disabled account must not be
    // distinguishable from a plain wrong password.
    test()->post('/login', ['email' => $suspended->usr_email, 'password' => 'password'])
        ->assertInvalid(['email' => $message]);

    test()->post('/login', ['email' => $active->usr_email, 'password' => 'not-the-right-password'])
        ->assertInvalid(['email' => $message]);
});

test('a suspended account is still blocked even with the correct password, and it is security-logged', function () {
    $user = User::factory()->create(['usr_role' => UserRole::PtoAdministrator, 'usr_status' => 'Inactive']);

    test()->post('/login', ['email' => $user->usr_email, 'password' => 'password'])
        ->assertInvalid(['email' => 'These credentials do not match our records.']);

    $log = SecurityLog::where('usr_id', $user->usr_id)->where('sec_event_type', 'login_failed')->first();
    expect($log)->not->toBeNull();
    expect($log->sec_details)->toBe(['reason' => 'account_suspended']);
});

test('a successful login updates last_login_at and records a security log entry', function () {
    $user = User::factory()->create(['usr_role' => UserRole::PtoAdministrator, 'usr_last_login_at' => null]);

    test()->post('/login', [
        'email' => $user->usr_email,
        'password' => 'password',
    ])->assertRedirect(route('pto.dashboard'));

    expect($user->fresh()->usr_last_login_at)->not->toBeNull();
    expect(SecurityLog::where('usr_id', $user->usr_id)->where('sec_event_type', 'login_success')->exists())->toBeTrue();
});

test('a failed login attempt records a security log entry without ever logging the password', function () {
    $user = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    test()->post('/login', [
        'email' => $user->usr_email,
        'password' => 'definitely-wrong',
    ])->assertSessionHasErrors('email');

    $log = SecurityLog::where('usr_id', $user->usr_id)->where('sec_event_type', 'login_failed')->first();
    expect($log)->not->toBeNull();
    expect($log->sec_attempted_email)->toBeNull();
    expect(json_encode($log->sec_details ?? []))->not->toContain('definitely-wrong');
});

test('a failed login for an unknown email creates a security log with user_id null and attempted_email set', function () {
    test()->post('/login', [
        'email' => 'nobody-registered@example.test',
        'password' => 'whatever-they-typed',
    ])->assertSessionHasErrors('email');

    $log = SecurityLog::where('sec_event_type', 'login_failed')->where('sec_attempted_email', 'nobody-registered@example.test')->first();
    expect($log)->not->toBeNull();
    expect($log->usr_id)->toBeNull();
    expect(json_encode($log->toArray()))->not->toContain('whatever-they-typed');
});

test('logout invalidates the session and records a security log entry', function () {
    $user = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    test()->actingAs($user)->post('/logout')->assertRedirect('/');

    test()->assertGuest();
    expect(SecurityLog::where('usr_id', $user->usr_id)->where('sec_event_type', 'logout')->exists())->toBeTrue();
});

test('login is throttled after repeated failed attempts', function () {
    RateLimiter::clear('login');

    $user = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    for ($i = 0; $i < 5; $i++) {
        test()->post('/login', ['email' => $user->usr_email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
    }

    // The 6th attempt within the window is rate-limited (429), not a normal
    // validation failure — proves the throttle:login middleware is wired up.
    $response = test()->post('/login', ['email' => $user->usr_email, 'password' => 'wrong']);
    $response->assertStatus(429);

    RateLimiter::clear('login');
});
