<?php

use App\Enums\UserRole;
use App\Models\SecurityLog;
use App\Models\User;
use App\Support\SessionSecurity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function makeSettingsPto(): User
{
    return User::factory()->create(['role' => UserRole::PtoAdministrator]);
}

test('an 11-character password is rejected when changing password', function () {
    $user = makeSettingsPto();

    $response = test()->actingAs($user)->post(route('pto.settings.password'), [
        'current_password' => 'password',
        'password' => 'short11111',
        'password_confirmation' => 'short11111',
    ]);

    $response->assertSessionHasErrors('password');
    expect(Hash::check('short11111', $user->fresh()->password))->toBeFalse();
});

test('a password without a number is rejected when changing password', function () {
    $user = makeSettingsPto();

    $response = test()->actingAs($user)->post(route('pto.settings.password'), [
        'current_password' => 'password',
        'password' => 'onlylettershere',
        'password_confirmation' => 'onlylettershere',
    ]);

    $response->assertSessionHasErrors('password');
});

test('a 12-character password with letters and numbers is accepted when changing password', function () {
    $user = makeSettingsPto();

    $response = test()->actingAs($user)->post(route('pto.settings.password'), [
        'current_password' => 'password',
        'password' => 'validpass123',
        'password_confirmation' => 'validpass123',
    ]);

    $response->assertSessionHasNoErrors();
    expect(Hash::check('validpass123', $user->fresh()->password))->toBeTrue();
});

test('changing password never stores it in plain text', function () {
    $user = makeSettingsPto();

    test()->actingAs($user)->post(route('pto.settings.password'), [
        'current_password' => 'password',
        'password' => 'validpass123',
        'password_confirmation' => 'validpass123',
    ]);

    expect($user->fresh()->password)->not->toBe('validpass123');
    expect($user->fresh()->password)->toStartWith('$2y$');
});

// UpdatesAccountSettings::updatePassword and NewPasswordController::store
// both delegate session invalidation to SessionSecurity — tested directly
// here since the test suite runs with SESSION_DRIVER=array (phpunit.xml),
// so a full HTTP request in a feature test never touches the `sessions`
// table SessionSecurity operates on.
test('SessionSecurity invalidates every other session for a user but keeps the excepted one', function () {
    config(['session.driver' => 'database']);
    $user = makeSettingsPto();
    $otherUser = makeSettingsPto();

    DB::table('sessions')->insert([
        ['id' => 'session-a', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'session-b', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'session-c', 'user_id' => $otherUser->id, 'payload' => '', 'last_activity' => now()->timestamp],
    ]);

    SessionSecurity::invalidateOtherSessionsFor($user, 'session-b');

    expect(DB::table('sessions')->where('id', 'session-a')->exists())->toBeFalse();
    expect(DB::table('sessions')->where('id', 'session-b')->exists())->toBeTrue();
    expect(DB::table('sessions')->where('id', 'session-c')->exists())->toBeTrue();
});

test('SessionSecurity invalidates all sessions for a user when no exception is given', function () {
    config(['session.driver' => 'database']);
    $user = makeSettingsPto();

    DB::table('sessions')->insert([
        ['id' => 'session-a', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'session-b', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
    ]);

    SessionSecurity::invalidateOtherSessionsFor($user);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

test('changing password records a security log entry', function () {
    $user = makeSettingsPto();

    test()->actingAs($user)->post(route('pto.settings.password'), [
        'current_password' => 'password',
        'password' => 'validpass123',
        'password_confirmation' => 'validpass123',
    ]);

    expect(SecurityLog::where('user_id', $user->id)->where('event_type', 'password_changed')->exists())->toBeTrue();
});
