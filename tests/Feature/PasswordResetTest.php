<?php

use App\Enums\UserRole;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
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
            ->assertSee($user->email);

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
            'password' => 'my-new-password',
            'password_confirmation' => 'my-new-password',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        return true;
    });

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'my-new-password'])
        ->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('an invalid token is rejected', function () {
    $user = User::factory()->create();

    $this->post(route('password.store'), [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'my-new-password',
        'password_confirmation' => 'my-new-password',
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

    $this->actingAs($lgu)->post(route('lgu.users.store'), [
        'name' => 'First Login Inn',
        'category' => 'accommodation',
        'barangay' => 'Dahican',
        'ownerName' => 'Maria Santos',
        'contactPhone' => '09170000000',
        'email' => 'owner@firstlogininn.test',
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
