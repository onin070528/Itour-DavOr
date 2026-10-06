<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
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

test('a successful login updates usr_last_login_at and records an audit log entry', function () {
    $user = User::factory()->create(['usr_role' => UserRole::PtoAdministrator, 'usr_last_login_at' => null]);

    test()->post('/login', [
        'email' => $user->usr_email,
        'password' => 'password',
    ])->assertRedirect(route('pto.dashboard'));

    expect($user->fresh()->usr_last_login_at)->not->toBeNull();
    expect(AuditLog::where('usr_id', $user->usr_id)->where('aud_action', 'login.success')->exists())->toBeTrue();
});

test('a failed login attempt records an audit log entry without ever logging the password', function () {
    $user = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    test()->post('/login', [
        'email' => $user->usr_email,
        'password' => 'definitely-wrong',
    ])->assertSessionHasErrors('email');

    $log = AuditLog::where('usr_id', $user->usr_id)->where('aud_action', 'login.failed')->first();
    expect($log)->not->toBeNull();
    expect(json_encode($log->aud_metadata ?? []))->not->toContain('definitely-wrong');
});

test('logout invalidates the session and records an audit log entry', function () {
    $user = User::factory()->create(['usr_role' => UserRole::PtoAdministrator]);

    test()->actingAs($user)->post('/logout')->assertRedirect('/');

    test()->assertGuest();
    expect(AuditLog::where('usr_id', $user->usr_id)->where('aud_action', 'logout')->exists())->toBeTrue();
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
