<?php

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;

test('the login page links to forgot password', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Forgot password?')
        ->assertSee(route('password.request'));
});

test('the forgot password page renders', function () {
    $this->get(route('password.request'))->assertOk()->assertSee('Email Reset Link');
});

test('a reset link is emailed to an existing account', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('an unknown email gets the same confirmation, so accounts cannot be discovered', function () {
    Notification::fake();

    $this->post(route('password.email'), ['email' => 'nobody@example.test'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

test('the reset link page renders with the email pre-filled', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee($user->email)
            ->assertSeeInOrder(['New Password', 'Password requirements:', 'At least 12 characters', 'At least 1 special character', 'Confirm New Password'])
            ->assertDontSee('at least 8 characters');

        return true;
    });
});

test('a new password can be set with a valid token and then used to sign in', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'my-new-password1',
            'password_confirmation' => 'my-new-password1',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        return true;
    });

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'my-new-password1'])
        ->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('an invalid token is rejected', function () {
    $user = User::factory()->create();

    $this->post(route('password.store'), [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'my-new-password1',
        'password_confirmation' => 'my-new-password1',
    ])->assertSessionHasErrors('email');
});

test('a newly registered establishment account cannot sign in with a default password but can set one via forgot password', function () {
    Notification::fake();
    $mati = Municipality::query()->firstOrCreate(['code' => 'MATI'], ['name' => 'City of Mati']);
    $lgu = User::factory()->create([
        'role' => UserRole::Lgu,
        'organization_subtitle' => 'City of Mati',
        'municipality_id' => $mati->id,
    ]);

    $listing = Listing::query()->create([
        'slug' => 'first-login-inn',
        'name' => 'First Login Inn',
        'category' => 'accommodation',
        'municipality' => $mati->name,
        'municipality_id' => $mati->id,
        'barangay' => 'Dahican',
        'status' => 'DRAFT',
    ]);

    $this->actingAs($lgu)->post(route('lgu.directory.establishments.switchToOnline', $listing), [
        'account_name' => 'Maria Santos',
        'account_email' => 'owner@firstlogininn.test',
    ])->assertSessionHasNoErrors();
    auth()->logout();

    $this->post(route('login.store'), ['email' => 'owner@firstlogininn.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->post(route('password.email'), ['email' => 'owner@firstlogininn.test']);
    $owner = User::query()->where('email', 'owner@firstlogininn.test')->first();
    Notification::assertSentTo($owner, ResetPassword::class);
});

test('a mail server failure shows a friendly error instead of crashing', function () {
    $user = User::factory()->create();

    Password::shouldReceive('sendResetLink')
        ->once()
        ->andThrow(new TransportException('Connection could not be established'));

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHasErrors('email');
});

test('a too-short password is rejected when resetting', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'short11111',
            'password_confirmation' => 'short11111',
        ])->assertSessionHasErrors('password');

        return true;
    });
});

test('a password without a number is rejected when resetting', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'onlylettershere',
            'password_confirmation' => 'onlylettershere',
        ])->assertSessionHasErrors('password');

        return true;
    });
});

test('a used reset link cannot be reused', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $payload = [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'my-new-password1',
            'password_confirmation' => 'my-new-password1',
        ];

        $this->post(route('password.store'), $payload)->assertSessionHasNoErrors();

        // Same token, a second time — must fail, not silently re-apply.
        $this->post(route('password.store'), array_merge($payload, [
            'password' => 'another-password2',
            'password_confirmation' => 'another-password2',
        ]))->assertSessionHasErrors('email');

        return true;
    });
});

test('an expired reset link is rejected', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->travel(61)->minutes();

        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'my-new-password1',
            'password_confirmation' => 'my-new-password1',
        ])->assertSessionHasErrors('email');

        return true;
    });
});

test('resetting a password logs out every existing session for that account', function () {
    Notification::fake();
    $user = User::factory()->create();

    config(['session.driver' => 'database']);
    DB::table('sessions')->insert([
        ['id' => 'stale-session-a', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'stale-session-b', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
    ]);

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'my-new-password1',
            'password_confirmation' => 'my-new-password1',
        ]);

        return true;
    });

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

test('resetting a password records security log entries for the request and the completion', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'my-new-password1',
            'password_confirmation' => 'my-new-password1',
        ]);

        return true;
    });

    expect(SecurityLog::where('user_id', $user->id)->where('event_type', 'password_reset_requested')->exists())->toBeTrue();
    expect(SecurityLog::where('user_id', $user->id)->where('event_type', 'password_reset_completed')->exists())->toBeTrue();
});
