<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\Turnstile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

// SessionController::store() skips Turnstile verification when
// app()->environment('testing') — there is no real widget in the automated
// suite to produce a token — so these tests exercise App\Rules\Turnstile
// directly against a faked Cloudflare siteverify response instead of going
// through the full /login endpoint.

test('the Turnstile rule passes when Cloudflare siteverify returns success', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true]),
    ]);

    $validator = Validator::make(
        ['cf-turnstile-response' => 'a-valid-token'],
        ['cf-turnstile-response' => ['required', 'string', new Turnstile('127.0.0.1')]]
    );

    expect($validator->passes())->toBeTrue();
});

test('the Turnstile rule fails when Cloudflare siteverify returns failure', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]),
    ]);

    $validator = Validator::make(
        ['cf-turnstile-response' => 'a-bad-token'],
        ['cf-turnstile-response' => ['required', 'string', new Turnstile('127.0.0.1')]]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('cf-turnstile-response'))->toBe('Verification check failed. Please try again.');
});

test('the Turnstile rule fails closed when the siteverify request itself errors', function () {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([], 500),
    ]);

    $validator = Validator::make(
        ['cf-turnstile-response' => 'a-token'],
        ['cf-turnstile-response' => ['required', 'string', new Turnstile('127.0.0.1')]]
    );

    expect($validator->fails())->toBeTrue();
});

test('an empty Turnstile token is rejected without calling Cloudflare', function () {
    Http::fake();

    $validator = Validator::make(
        ['cf-turnstile-response' => ''],
        ['cf-turnstile-response' => ['required', 'string', new Turnstile('127.0.0.1')]]
    );

    expect($validator->fails())->toBeTrue();
    Http::assertNothingSent();
});

test('the login page renders the Turnstile widget with the configured site key, not the secret', function () {
    config([
        'services.turnstile.site_key' => 'test-site-key-123',
        'services.turnstile.secret_key' => 'super-secret-value-456',
    ]);

    $response = test()->get(route('login'));

    $response->assertOk();
    $response->assertSee('cf-turnstile', false);
    $response->assertSee('test-site-key-123');
    $response->assertDontSee('super-secret-value-456');
});

test('login still works end-to-end in the automated test suite (Turnstile is bypassed only for APP_ENV=testing)', function () {
    $user = User::factory()->create(['role' => UserRole::PtoAdministrator]);

    test()->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('pto.dashboard'));

    test()->assertAuthenticatedAs($user);
});
