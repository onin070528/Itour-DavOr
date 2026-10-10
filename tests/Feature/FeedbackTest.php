<?php

/**
 * iTOUR — Davao Oriental Tourism Information System
 *
 * Purpose: Tests — public tourist feedback submission and the LGU feedback pages.
 * Programmer/s: iTOUR Development Team
 * Copyright (c) 2026 iTOUR Development Team. All rights reserved.
 */

use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\QrFeedback;
use App\Models\User;
use Illuminate\Support\Str;

function feedbackListingFixture(string $strMunicipality = 'City of Mati', string $strCode = 'MATI', array $arrOverrides = []): Listing
{
    $objMunicipality = Municipality::query()->firstOrCreate(['mun_code' => $strCode], ['mun_name' => $strMunicipality]);

    return Listing::query()->create(array_merge([
        'lst_slug' => Str::slug('feedback-fixture-'.Str::random(6)),
        'lst_name' => 'Feedback Fixture Resort',
        'lst_barangay' => 'Brgy. Dahican',
        'lst_category' => 'accommodation',
        'lst_municipality' => $strMunicipality,
        'mun_id' => $objMunicipality->mun_id,
        'lst_status' => 'PUBLISHED',
    ], $arrOverrides));
}

function feedbackLguFixture(Listing $objListing): User
{
    return User::factory()->create([
        'usr_role' => UserRole::Lgu,
        'usr_organization_subtitle' => $objListing->lst_municipality,
        'mun_id' => $objListing->mun_id,
    ]);
}

test('a tourist can leave feedback on a public listing and its sentiment is stored', function () {
    $objListing = feedbackListingFixture();

    test()->post(route('listings.feedback.store', $objListing), [
        'rating' => 5,
        'comment' => 'Amazing place, the staff were very friendly.',
        'name' => 'Ana',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $objFeedback = QrFeedback::query()->firstOrFail();

    expect($objFeedback->lst_id)->toBe($objListing->lst_id)
        ->and($objFeedback->fbk_sentiment)->toBe('Positive')
        ->and($objFeedback->fbk_language)->toBe('English')
        ->and($objFeedback->fbk_name)->toBe('Ana');
});

test('feedback needs a rating and a comment', function () {
    $objListing = feedbackListingFixture();

    test()->post(route('listings.feedback.store', $objListing), ['comment' => 'ok'])
        ->assertSessionHasErrors(['rating', 'comment']);

    expect(QrFeedback::query()->count())->toBe(0);
});

test('feedback cannot be left on a listing that is not public', function () {
    $objListing = feedbackListingFixture(arrOverrides: ['lst_status' => 'DRAFT']);

    test()->post(route('listings.feedback.store', $objListing), ['rating' => 5, 'comment' => 'Lovely place'])
        ->assertNotFound();
});

test('the public listing page shows the feedback form', function () {
    $objListing = feedbackListingFixture();

    test()->get(route('listings.show', $objListing))
        ->assertOk()
        ->assertSee('Share your experience')
        ->assertSee(route('listings.feedback.store', $objListing), false);
});

test("the LGU feedback pages show only its own municipality's feedback with the analyzed sentiment", function () {
    $objMatiListing = feedbackListingFixture('City of Mati', 'MATI');
    $objBagangaListing = feedbackListingFixture('Baganga', 'BAG', ['lst_name' => 'Baganga Only Resort']);
    $objLgu = feedbackLguFixture($objMatiListing);

    QrFeedback::factory()->create(['lst_id' => $objMatiListing->lst_id, 'fbk_text' => 'Mati visitor loved it']);
    QrFeedback::factory()->negative()->create(['lst_id' => $objMatiListing->lst_id, 'fbk_text' => 'Mati visitor hated it']);
    QrFeedback::factory()->create(['lst_id' => $objBagangaListing->lst_id, 'fbk_text' => 'Baganga visitor comment']);

    test()->actingAs($objLgu)->get(route('lgu.feedback.index'))
        ->assertOk()
        ->assertSee('Mati visitor loved it')
        ->assertSee('Mati visitor hated it')
        ->assertDontSee('Baganga visitor comment');

    test()->actingAs($objLgu)->get(route('lgu.feedback.analytics'))
        ->assertOk()
        ->assertSee('2 feedback entries analyzed');
});

test('with no feedback the LGU pages show their empty states', function () {
    $objLgu = feedbackLguFixture(feedbackListingFixture());

    test()->actingAs($objLgu)->get(route('lgu.feedback.index'))->assertOk()->assertSee('No feedback for City of Mati yet');
    test()->actingAs($objLgu)->get(route('lgu.feedback.analytics'))->assertOk()->assertSee('No feedback recorded');
});
