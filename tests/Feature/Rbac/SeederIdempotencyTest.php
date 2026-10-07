<?php

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use Database\Seeders\MunicipalitySeeder;
use Database\Seeders\RbacDemoAccountSeeder;
use Illuminate\Support\Facades\Hash;

test('MunicipalitySeeder is idempotent', function () {
    (new MunicipalitySeeder)->run();
    $first = Municipality::count();

    (new MunicipalitySeeder)->run();
    $second = Municipality::count();

    expect($first)->toBe(11);
    expect($second)->toBe(11);
});

test('RbacDemoAccountSeeder is idempotent', function () {
    (new MunicipalitySeeder)->run();

    $emails = ['tourism@itourdavor.gov.ph', 'tourism.mati@itourdavor.gov.ph', 'establishments@itourdavor.gov.ph'];

    (new RbacDemoAccountSeeder)->run();
    $first = User::whereIn('email', $emails)->count();
    $firstListings = Listing::where('slug', 'like', 'itour-demo-establishment-%')->count();

    (new RbacDemoAccountSeeder)->run();
    $second = User::whereIn('email', $emails)->count();
    $secondListings = Listing::where('slug', 'like', 'itour-demo-establishment-%')->count();

    expect($first)->toBe(3);
    expect($second)->toBe(3);
    expect($firstListings)->toBe(2);
    expect($secondListings)->toBe(2);
});

test('RbacDemoAccountSeeder refuses to run in production', function () {
    (new MunicipalitySeeder)->run();

    $this->app->instance('env', 'production');

    (new RbacDemoAccountSeeder)->run();

    $this->app->instance('env', 'testing');

    expect(User::where('email', 'tourism@itourdavor.gov.ph')->exists())->toBeFalse();
});

test('RbacDemoAccountSeeder creates one active LGU Tourism Admin per municipality with a hashed temporary password', function () {
    (new MunicipalitySeeder)->run();
    (new RbacDemoAccountSeeder)->run();

    $strTemporaryPassword = env('SEED_DEMO_PASSWORD');

    foreach (RbacDemoAccountSeeder::lguEmailsByMunicipalityCode() as $strCode => $strEmail) {
        $objUser = User::query()->where('email', $strEmail)->firstOrFail();

        expect($strEmail)->toMatch('/^tourism\.[a-z]+@itourdavor\.gov\.ph$/');
        expect($objUser->role)->toBe(UserRole::Lgu);
        expect($objUser->status)->toBe('Active');
        expect($objUser->municipality_id)->toBe(Municipality::query()->where('code', $strCode)->value('id'));
        expect($objUser->organization_subtitle)->toBe($objUser->municipality->name);
        expect($objUser->getRawOriginal('password'))->not->toBe($strTemporaryPassword);
        expect(Hash::check($strTemporaryPassword, $objUser->password))->toBeTrue();
        expect($objUser->mustChangePassword())->toBeTrue();
    }

    expect(User::query()->where('role', UserRole::Lgu)->count())->toBe(Municipality::count());
});

test('RbacDemoAccountSeeder adds the secondary PTO account without changing the primary one', function () {
    (new MunicipalitySeeder)->run();
    (new RbacDemoAccountSeeder)->run();

    $objSecondary = User::query()->where('email', 'tourism.admin2@itourdavor.gov.ph')->firstOrFail();
    $objPrimary = User::query()->where('email', 'tourism@itourdavor.gov.ph')->firstOrFail();

    expect($objSecondary->role)->toBe(UserRole::PtoAdministrator);
    expect($objSecondary->municipality_id)->toBeNull();
    expect($objSecondary->status)->toBe('Active');
    expect($objSecondary->mustChangePassword())->toBeTrue();
    expect(Hash::check(env('SEED_DEMO_PASSWORD'), $objSecondary->password))->toBeTrue();

    expect($objPrimary->role)->toBe(UserRole::PtoAdministrator);
    expect($objPrimary->mustChangePassword())->toBeFalse();
    expect(User::query()->where('role', UserRole::PtoAdministrator)->count())->toBe(2);
});

test('running RbacDemoAccountSeeder twice creates no duplicate users or municipalities', function () {
    (new MunicipalitySeeder)->run();

    (new RbacDemoAccountSeeder)->run();
    $intUsersAfterFirstRun = User::count();

    (new RbacDemoAccountSeeder)->run();

    expect(User::count())->toBe($intUsersAfterFirstRun);
    expect(User::query()->select('email')->groupBy('email')->havingRaw('COUNT(*) > 1')->count())->toBe(0);
    expect(Municipality::count())->toBe(11);
});

test('re-seeding never resets a password the account holder already changed', function () {
    (new MunicipalitySeeder)->run();
    (new RbacDemoAccountSeeder)->run();

    $objLgu = User::query()->where('email', 'tourism.baganga@itourdavor.gov.ph')->firstOrFail();
    $objLgu->forceFill([
        'password' => 'A-new-personal-passw0rd!',
        'usr_must_change_password' => false,
        'usr_password_changed_at' => now(),
    ])->save();

    (new RbacDemoAccountSeeder)->run();
    $objLgu->refresh();

    expect(Hash::check('A-new-personal-passw0rd!', $objLgu->password))->toBeTrue();
    expect($objLgu->mustChangePassword())->toBeFalse();
});

test('RbacDemoAccountSeeder aborts without creating anything when a municipality is missing', function () {
    (new MunicipalitySeeder)->run();
    Municipality::query()->where('code', 'TAR')->delete();

    expect(fn () => (new RbacDemoAccountSeeder)->run())->toThrow(RuntimeException::class, 'TAR');

    expect(User::count())->toBe(0);
    expect(Municipality::count())->toBe(10);
});

test('RbacDemoAccountSeeder aborts when another active LGU account already holds a municipality', function () {
    (new MunicipalitySeeder)->run();
    User::factory()->create([
        'role' => UserRole::Lgu,
        'municipality_id' => Municipality::query()->where('code', 'CAT')->value('id'),
        'status' => 'Active',
    ]);

    expect(fn () => (new RbacDemoAccountSeeder)->run())->toThrow(RuntimeException::class, 'already hold a municipality');

    expect(User::count())->toBe(1);
});

test('db:seed output never contains the temporary password', function () {
    (new MunicipalitySeeder)->run();

    $this->artisan('db:seed', ['--class' => RbacDemoAccountSeeder::class])
        ->doesntExpectOutputToContain(env('SEED_DEMO_PASSWORD'))
        ->assertSuccessful();
});
