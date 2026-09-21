<?php

use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use Database\Seeders\MunicipalitySeeder;
use Database\Seeders\RbacDemoAccountSeeder;

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
